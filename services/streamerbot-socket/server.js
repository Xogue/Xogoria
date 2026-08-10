"use strict";

const crypto = require( "node:crypto" );
const http = require( "node:http" );
const { WebSocketServer, WebSocket } = require( "ws" );

const host = process.env.SOCKET_HOST || "127.0.0.1";
const port = parseInteger( process.env.SOCKET_PORT, 8787 );
const socketPath = normalizePath( process.env.SOCKET_PATH || "/streamerbot" );
const apiKey = process.env.STREAMERBOT_SOCKET_API_KEY || "";
const authenticationTimeoutMs = parseInteger( process.env.AUTH_TIMEOUT_MS, 10000 );
const heartbeatIntervalMs = parseInteger( process.env.HEARTBEAT_INTERVAL_MS, 30000 );
const maxPayloadBytes = parseInteger( process.env.MAX_PAYLOAD_BYTES, 20 * 1024 * 1024 );

if ( apiKey.length < 32 ) {
    process.stderr.write(
        "STREAMERBOT_SOCKET_API_KEY must be configured with at least 32 characters.\n",
    );
    process.exit( 1 );
}

const connections = new Map( );

const httpServer = http.createServer( ( request, response ) => {
    const url = new URL( request.url || "/", `http://${request.headers.host || "localhost"}` );

    if ( request.method === "GET" && url.pathname === "/health" ) {
        sendJson( response, 200, {
            ok: true,
            service: "xogoria-streamerbot-socket",
            authenticatedConnections: connections.size,
            uptimeSeconds: Math.floor( process.uptime( ) ),
        } );
        return;
    }

    sendJson( response, 404, { ok: false, error: "Not found." } );
} );

const websocketServer = new WebSocketServer( {
    noServer: true,
    maxPayload: maxPayloadBytes,
    perMessageDeflate: false,
} );

httpServer.on( "upgrade", ( request, socket, head ) => {
    let pathname = "";

    try {
        pathname = new URL(
            request.url || "/",
            `http://${request.headers.host || "localhost"}`,
        ).pathname;
    } catch {
        socket.destroy( );
        return;
    }

    if ( pathname !== socketPath ) {
        socket.write( "HTTP/1.1 404 Not Found\r\nConnection: close\r\n\r\n" );
        socket.destroy( );
        return;
    }

    websocketServer.handleUpgrade( request, socket, head, ( websocket ) => {
        websocketServer.emit( "connection", websocket, request );
    } );
} );

websocketServer.on( "connection", ( websocket, request ) => {
    const connectionId = crypto.randomUUID( );
    const remoteAddress = getRemoteAddress( request );
    websocket.isAlive = true;
    websocket.isAuthenticated = false;
    websocket.instanceId = "";

    log( "info", "connection_opened", { connectionId, remoteAddress } );

    const authenticationTimer = setTimeout( ( ) => {
        if ( !websocket.isAuthenticated ) {
            send( websocket, { type: "auth_error", error: "Authentication timed out." } );
            websocket.close( 4401, "Authentication required" );
        }
    }, authenticationTimeoutMs );

    websocket.on( "pong", ( ) => {
        websocket.isAlive = true;
    } );

    websocket.on( "message", ( raw, isBinary ) => {
        if ( isBinary ) {
            websocket.close( 4400, "Text JSON messages are required" );
            return;
        }

        let message;
        try {
            message = JSON.parse( raw.toString( "utf8" ) );
        } catch {
            send( websocket, { type: "protocol_error", error: "Valid JSON is required." } );
            return;
        }

        if ( !websocket.isAuthenticated ) {
            authenticate( websocket, message, connectionId, remoteAddress );
            return;
        }

        handleMessage( websocket, message, connectionId );
    } );

    websocket.on( "close", ( code, reason ) => {
        clearTimeout( authenticationTimer );
        if (
            websocket.instanceId !== "" &&
            connections.get( websocket.instanceId ) === websocket
        ) {
            connections.delete( websocket.instanceId );
        }
        log( "info", "connection_closed", {
            connectionId,
            instanceId: websocket.instanceId,
            code,
            reason: reason.toString( ),
        } );
    } );

    websocket.on( "error", ( error ) => {
        log( "error", "connection_error", {
            connectionId,
            instanceId: websocket.instanceId,
            error: error.message,
        } );
    } );
} );

const heartbeat = setInterval( ( ) => {
    for ( const websocket of websocketServer.clients ) {
        if ( websocket.isAlive === false ) {
            websocket.terminate( );
            continue;
        }
        websocket.isAlive = false;
        websocket.ping( );
    }
}, heartbeatIntervalMs );

httpServer.listen( port, host, ( ) => {
    log( "info", "server_started", { host, port, socketPath } );
} );

function authenticate( websocket, message, connectionId, remoteAddress ) {
    const providedKey = typeof message.api_key === "string" ? message.api_key : "";
    const instanceId = sanitizeInstanceId( message.instance_id );

    if (
        message.type !== "authenticate" ||
        instanceId === "" ||
        !safeEqual( providedKey, apiKey )
    ) {
        log( "warn", "authentication_failed", { connectionId, remoteAddress, instanceId } );
        send( websocket, { type: "auth_error", error: "Authentication failed." } );
        websocket.close( 4401, "Authentication failed" );
        return;
    }

    const existing = connections.get( instanceId );
    if ( existing && existing !== websocket && existing.readyState === WebSocket.OPEN ) {
        existing.close( 4409, "Replaced by a newer connection" );
    }

    websocket.isAuthenticated = true;
    websocket.instanceId = instanceId;
    connections.set( instanceId, websocket );

    log( "info", "authentication_succeeded", { connectionId, remoteAddress, instanceId } );
    send( websocket, {
        type: "auth_ok",
        instance_id: instanceId,
        server_time_utc: new Date( ).toISOString( ),
    } );
}

function handleMessage( websocket, message, connectionId ) {
    const messageType = typeof message.type === "string" ? message.type : "";
    const requestId = typeof message.request_id === "string" ? message.request_id : "";

    if ( messageType === "ping" ) {
        send( websocket, {
            type: "pong",
            request_id: requestId,
            server_time_utc: new Date( ).toISOString( ),
        } );
        return;
    }

    if ( messageType === "connection_test" ) {
        log( "info", "connection_test_received", {
            connectionId,
            instanceId: websocket.instanceId,
            requestId,
        } );
        send( websocket, {
            type: "connection_test_ack",
            request_id: requestId,
            instance_id: websocket.instanceId,
            server_time_utc: new Date( ).toISOString( ),
        } );
        return;
    }

    if ( messageType === "streamerbot_event" ) {
        log( "info", "streamerbot_event_received", {
            connectionId,
            instanceId: websocket.instanceId,
            eventType: message.event_type || "",
        } );
        send( websocket, {
            type: "event_ack",
            request_id: requestId,
            received_at_utc: new Date( ).toISOString( ),
        } );
        return;
    }

    log( "warn", "unsupported_message", {
        connectionId,
        instanceId: websocket.instanceId,
        messageType,
        requestId,
    } );
    send( websocket, {
        type: "protocol_error",
        request_id: requestId,
        error: "Unsupported message type.",
    } );
}

function send( websocket, payload ) {
    if ( websocket.readyState === WebSocket.OPEN ) {
        websocket.send( JSON.stringify( payload ) );
    }
}

function safeEqual( left, right ) {
    const leftBuffer = Buffer.from( left, "utf8" );
    const rightBuffer = Buffer.from( right, "utf8" );
    return leftBuffer.length === rightBuffer.length &&
        crypto.timingSafeEqual( leftBuffer, rightBuffer );
}

function sanitizeInstanceId( value ) {
    return typeof value === "string"
        ? value.trim( ).replace( /[^a-zA-Z0-9_.-]/g, "" ).slice( 0, 100 )
        : "";
}

function getRemoteAddress( request ) {
    const forwarded = request.headers[ "x-forwarded-for" ];
    if ( typeof forwarded === "string" && forwarded !== "" ) {
        return forwarded.split( "," )[ 0 ].trim( );
    }
    return request.socket.remoteAddress || "unknown";
}

function sendJson( response, status, body ) {
    const json = JSON.stringify( body );
    response.writeHead( status, {
        "Content-Type": "application/json; charset=utf-8",
        "Content-Length": Buffer.byteLength( json ),
        "Cache-Control": "no-store",
    } );
    response.end( json );
}

function parseInteger( value, fallback ) {
    const parsed = Number.parseInt( value, 10 );
    return Number.isFinite( parsed ) && parsed > 0 ? parsed : fallback;
}

function normalizePath( value ) {
    const normalized = `/${String( value ).replace( /^\/+|\/+$/g, "" )}`;
    return normalized === "/" ? "/streamerbot" : normalized;
}

function log( level, event, context = { } ) {
    process.stdout.write( JSON.stringify( {
        timestamp: new Date( ).toISOString( ),
        level,
        event,
        ...context,
    } ) + "\n" );
}

function shutdown( signal ) {
    log( "info", "server_stopping", { signal } );
    clearInterval( heartbeat );
    for ( const websocket of websocketServer.clients ) {
        websocket.close( 1001, "Server shutting down" );
    }
    httpServer.close( ( ) => process.exit( 0 ) );
    setTimeout( ( ) => process.exit( 1 ), 5000 ).unref( );
}

process.on( "SIGTERM", ( ) => shutdown( "SIGTERM" ) );
process.on( "SIGINT", ( ) => shutdown( "SIGINT" ) );
