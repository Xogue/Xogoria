"use strict";

const crypto = require( "node:crypto" );
const fs = require( "node:fs" );
const http = require( "node:http" );
const path = require( "node:path" );
const { WebSocketServer, WebSocket } = require( "ws" );

const host = process.env.SOCKET_HOST || "127.0.0.1";
const port = parseInteger( process.env.SOCKET_PORT, 8787 );
const socketPath = normalizePath( process.env.SOCKET_PATH || "/streamerbot" );
const apiKey = process.env.STREAMERBOT_SOCKET_API_KEY || "";
const authenticationTimeoutMs = parseInteger( process.env.AUTH_TIMEOUT_MS, 10000 );
const heartbeatIntervalMs = parseInteger( process.env.HEARTBEAT_INTERVAL_MS, 30000 );
const maxPayloadBytes = parseInteger( process.env.MAX_PAYLOAD_BYTES, 20 * 1024 * 1024 );
const syncTimeoutMs = parseInteger( process.env.SYNC_TIMEOUT_MS, 60000 );
const captureDirectory = process.env.CAPTURE_DIRECTORY ||
    "/var/www/xogoria.com/storage/command-captures";

if ( apiKey.length < 32 ) {
    process.stderr.write(
        "STREAMERBOT_SOCKET_API_KEY must be configured with at least 32 characters.\n",
    );
    process.exit( 1 );
}

const connections = new Map( );
const pendingSyncs = new Map( );

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

        Promise.resolve( handleMessage( websocket, message, connectionId ) ).catch( ( error ) => {
            log( "error", "message_processing_failed", {
                connectionId,
                instanceId: websocket.instanceId,
                error: error.message,
            } );
            send( websocket, {
                type: "protocol_error",
                request_id: typeof message.request_id === "string" ? message.request_id : "",
                error: "The message could not be processed.",
            } );
        } );
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
    expirePendingSyncs( );
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

async function handleMessage( websocket, message, connectionId ) {
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

    if ( messageType === "streamerbot_api_response" ) {
        await handleStreamerbotApiResponse( websocket, message, connectionId );
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

async function handleStreamerbotApiResponse( websocket, message, connectionId ) {
    const payload = message.payload;
    const responseId = payload && typeof payload.id === "string" ? payload.id : "";
    const match = /^xogoria-sync-([a-zA-Z0-9_-]{1,100})-(commands|actions)$/.exec( responseId );

    if ( !match ) {
        send( websocket, {
            type: "protocol_error",
            error: "The Streamer.bot API response ID is invalid.",
        } );
        return;
    }

    const requestId = match[ 1 ];
    const responseType = match[ 2 ];

    if ( payload.status !== "ok" ) {
        log( "warn", "sync_response_failed", {
            connectionId,
            instanceId: websocket.instanceId,
            requestId,
            responseType,
            status: payload.status || "unknown",
        } );
        sendSyncAcknowledgement( websocket, requestId, false, {
            error: `Streamer.bot returned an error for ${responseType}.`,
        } );
        pendingSyncs.delete( syncKey( websocket.instanceId, requestId ) );
        return;
    }

    const rows = payload[ responseType ];
    if ( !Array.isArray( rows ) ) {
        sendSyncAcknowledgement( websocket, requestId, false, {
            error: `The ${responseType} response did not contain an array.`,
        } );
        pendingSyncs.delete( syncKey( websocket.instanceId, requestId ) );
        return;
    }

    const key = syncKey( websocket.instanceId, requestId );
    const pending = pendingSyncs.get( key ) || {
        instanceId: websocket.instanceId,
        requestId,
        createdAt: Date.now( ),
        commands: null,
        actions: null,
    };
    pending[ responseType ] = payload;
    pendingSyncs.set( key, pending );

    log( "info", "sync_response_received", {
        connectionId,
        instanceId: websocket.instanceId,
        requestId,
        responseType,
        count: rows.length,
    } );

    if ( pending.commands === null || pending.actions === null ) {
        return;
    }

    pendingSyncs.delete( key );

    try {
        const snapshot = buildSnapshot( pending );
        const capture = await storeSnapshot( snapshot );
        log( "info", "sync_snapshot_stored", {
            connectionId,
            instanceId: websocket.instanceId,
            requestId,
            captureId: capture.id,
            bytes: capture.bytes,
            commandCount: snapshot.summary.command_count,
            actionCount: snapshot.summary.action_count,
        } );
        sendSyncAcknowledgement( websocket, requestId, true, {
            capture,
            summary: snapshot.summary,
        } );
    } catch ( error ) {
        log( "error", "sync_snapshot_failed", {
            connectionId,
            instanceId: websocket.instanceId,
            requestId,
            error: error.message,
        } );
        sendSyncAcknowledgement( websocket, requestId, false, {
            error: "The combined snapshot could not be stored.",
        } );
    }
}

function buildSnapshot( pending ) {
    const commands = pending.commands.commands;
    const actions = pending.actions.actions;
    const matchedActionIds = new Set( );
    let inferredSingleCount = 0;
    let inferredMultipleCount = 0;
    let unresolvedCommandCount = 0;
    let needsVerificationCount = 0;

    const commandActionCandidates = commands.map( ( command ) => {
        const commandParts = nameParts( command.name );
        const scored = actions.map( ( action ) => ( {
            action,
            parts: nameParts( action.name ),
            score: nameSimilarity( commandParts, nameParts( action.name ) ),
        } ) );
        const sameWords = scored.filter( ( candidate ) =>
            commandParts.words.length > 0 && arraysEqual( commandParts.words, candidate.parts.words )
        ).sort( ( left, right ) => right.score - left.score );
        const ranked = scored.sort( ( left, right ) => right.score - left.score );
        const selected = sameWords[ 0 ] || ranked[ 0 ] || null;
        const candidates = selected ? [ selected.action ] : [ ];
        for ( const action of candidates ) {
            if ( action.id ) matchedActionIds.add( String( action.id ) );
        }

        let status = "unresolved";
        let method = "no_action_available";
        let score = 0;
        if ( candidates.length === 1 ) {
            score = selected.score;
            const sameWordSet = sameWords.length > 0;
            const sameExtra = sameWordSet && commandParts.extra === selected.parts.extra;
            status = sameExtra ? "inferred_words" : "needs_verification";
            method = sameExtra
                ? "same_words"
                : ( sameWordSet ? "same_words_different_characters" : "fuzzy_name_guess" );
            inferredSingleCount++;
            if ( status === "needs_verification" ) needsVerificationCount++;
        } else if ( candidates.length > 1 ) {
            status = "inferred_multiple";
            inferredMultipleCount++;
        } else {
            unresolvedCommandCount++;
        }

        return {
            command_id: command.id || "",
            action_ids: candidates.map( ( action ) => action.id || "" ),
            mapping: {
                status,
                method,
                verified: false,
                review_required: status === "needs_verification",
                candidate_count: candidates.length,
                score: Number( score.toFixed( 4 ) ),
            },
        };
    } );

    const unmappedActionIds = actions.filter( ( action ) =>
        !matchedActionIds.has( String( action.id || "" ) )
    ).map( ( action ) => action.id || "" );

    return {
        schema_version: 4,
        captured_at_utc: new Date( ).toISOString( ),
        source: "streamer.bot-websocket",
        instance_id: pending.instanceId,
        request_id: pending.requestId,
        mapping_policy: {
            method: "word_set_then_fuzzy_command_name_to_action_name",
            verified: false,
            explanation: "Bracketed text is ignored. Equal word sets are paired regardless of order; differing remaining characters and fuzzy guesses require review.",
        },
        summary: {
            command_count: commands.length,
            action_count: actions.length,
            inferred_single_count: inferredSingleCount,
            inferred_multiple_count: inferredMultipleCount,
            unresolved_command_count: unresolvedCommandCount,
            unmapped_action_count: unmappedActionIds.length,
            needs_verification_count: needsVerificationCount,
        },
        commands,
        actions,
        command_action_candidates: commandActionCandidates,
        unmapped_action_ids: unmappedActionIds,
    };
}

async function storeSnapshot( snapshot ) {
    await fs.promises.mkdir( captureDirectory, { recursive: true, mode: 0o770 } );
    const capturedAt = new Date( );
    const stamp = capturedAt.toISOString( ).replace( /[-:]/g, "" ).slice( 0, 15 );
    const id = `streamerbot-${stamp.slice( 0, 8 )}-${stamp.slice( 9 )}-${crypto.randomBytes( 4 ).toString( "hex" )}.json`;
    const destination = path.join( captureDirectory, id );
    const temporary = path.join(
        captureDirectory,
        `.${id}.${process.pid}.${crypto.randomBytes( 4 ).toString( "hex" )}.tmp`,
    );
    const body = JSON.stringify( snapshot, null, 2 ) + "\n";

    try {
        await fs.promises.writeFile( temporary, body, { encoding: "utf8", mode: 0o640, flag: "wx" } );
        await fs.promises.rename( temporary, destination );
        await fs.promises.chmod( destination, 0o640 );
    } catch ( error ) {
        await fs.promises.unlink( temporary ).catch( ( ) => { } );
        throw error;
    }

    return { id, bytes: Buffer.byteLength( body ), validJson: true };
}

function expirePendingSyncs( ) {
    const now = Date.now( );
    for ( const [ key, pending ] of pendingSyncs ) {
        if ( now - pending.createdAt < syncTimeoutMs ) continue;
        pendingSyncs.delete( key );
        log( "warn", "sync_request_expired", {
            instanceId: pending.instanceId,
            requestId: pending.requestId,
            receivedCommands: pending.commands !== null,
            receivedActions: pending.actions !== null,
        } );
        const websocket = connections.get( pending.instanceId );
        if ( websocket ) {
            sendSyncAcknowledgement( websocket, pending.requestId, false, {
                error: "Timed out waiting for both Streamer.bot API responses.",
            } );
        }
    }
}

function sendSyncAcknowledgement( websocket, requestId, success, details = { } ) {
    send( websocket, {
        type: "sync_ack",
        request_id: requestId,
        success,
        server_time_utc: new Date( ).toISOString( ),
        ...details,
    } );
}

function syncKey( instanceId, requestId ) {
    return `${instanceId}:${requestId}`;
}

function normalizeMappingKey( value ) {
    return typeof value === "string"
        ? value.trim( ).replace( /^!+/, "" ).replace( /[^a-zA-Z0-9]/g, "" ).toLowerCase( )
        : "";
}

function nameParts( value ) {
    const withoutGroups = String( value || "" ).replace( /\[[^\]]*\]|\([^)]*\)|\{[^}]*\}|<[^>]*>/gu, " " ).toLowerCase( );
    const words = withoutGroups.match( /\p{L}+/gu ) || [ ];
    words.sort( );
    return {
        words,
        extra: withoutGroups.replace( /[\p{L}\s]+/gu, "" ),
        plain: `${words.join( " " )}|${withoutGroups.replace( /[\p{L}\s]+/gu, "" )}`,
    };
}

function nameSimilarity( left, right ) {
    const leftWords = [ ...new Set( left.words ) ];
    const rightWords = [ ...new Set( right.words ) ];
    const union = new Set( [ ...leftWords, ...rightWords ] );
    const common = leftWords.filter( ( word ) => rightWords.includes( word ) ).length;
    const wordScore = union.size === 0 ? 0 : common / union.size;
    const length = Math.max( left.plain.length, right.plain.length );
    const textScore = length === 0 ? 0 : 1 - Math.min( 1, levenshtein( left.plain, right.plain ) / length );
    return ( wordScore * 0.75 ) + ( textScore * 0.25 );
}

function arraysEqual( left, right ) {
    return left.length === right.length && left.every( ( value, index ) => value === right[ index ] );
}

function levenshtein( left, right ) {
    const previous = Array.from( { length: right.length + 1 }, ( _, index ) => index );
    for ( let leftIndex = 1; leftIndex <= left.length; leftIndex++ ) {
        const current = [ leftIndex ];
        for ( let rightIndex = 1; rightIndex <= right.length; rightIndex++ ) {
            current[ rightIndex ] = Math.min(
                current[ rightIndex - 1 ] + 1,
                previous[ rightIndex ] + 1,
                previous[ rightIndex - 1 ] + ( left[ leftIndex - 1 ] === right[ rightIndex - 1 ] ? 0 : 1 ),
            );
        }
        previous.splice( 0, previous.length, ...current );
    }
    return previous[ right.length ];
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
