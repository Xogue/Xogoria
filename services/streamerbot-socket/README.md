# Xogoria Streamer.bot WebSocket service

This service provides the authenticated, outbound-only WebSocket target used by
the configured WebSocket Client in Streamer.bot. Node listens on localhost;
Nginx terminates TLS and proxies `wss://socket.xogoria.com/streamerbot` to it.

The initial transport supports:

- first-message API-key authentication;
- one active connection per Streamer.bot instance ID;
- WebSocket ping/pong liveness checks;
- `connection_test` acknowledgements;
- configuration-event acknowledgements;
- an HTTP `/health` endpoint.

Snapshot assembly and persistence are deliberately deferred until the transport
has been verified from Streamer.bot to the VPS. Once enabled, paired
`GetCommands` and `GetActions` responses are assembled by request ID, written
atomically to the existing admin capture directory, and acknowledged with a
`sync_ack` message.

Snapshot schema version 4 stores each action record once. Command/action
candidates and unmapped-action lists reference action IDs instead of embedding
duplicate action objects. Candidate matching ignores bracketed name segments,
accepts equal word sets in any order, and flags differing extra characters or
fuzzy guesses for admin review.

## DNS

Create an `A` record for `socket.xogoria.com` pointing to the VPS public IPv4
address. Only add an `AAAA` record when IPv6 is configured and reachable on the
VPS. Wait until this returns the VPS address:

```bash
getent ahosts socket.xogoria.com
```

## Install

These commands assume the repository is deployed at `/var/www/xogoria.com`:

```bash
node --version
sudo useradd --system --home-dir /opt/xogoria/streamerbot-socket --shell /usr/sbin/nologin xogoria-socket
sudo install -d -o xogoria-socket -g xogoria-socket /opt/xogoria/streamerbot-socket
sudo cp /var/www/xogoria.com/services/streamerbot-socket/server.js /opt/xogoria/streamerbot-socket/
sudo cp /var/www/xogoria.com/services/streamerbot-socket/package.json /opt/xogoria/streamerbot-socket/
sudo cp /var/www/xogoria.com/services/streamerbot-socket/package-lock.json /opt/xogoria/streamerbot-socket/
sudo chown -R xogoria-socket:xogoria-socket /opt/xogoria/streamerbot-socket
sudo -u xogoria-socket npm --prefix /opt/xogoria/streamerbot-socket ci --omit=dev --ignore-scripts
```

Node.js 18 or newer is required. Verify that `command -v node` returns
`/usr/bin/node`; otherwise update `ExecStart` in the systemd unit to the absolute
path returned by that command.

Create a dedicated socket key. Do not reuse or commit the generated value:

```bash
openssl rand -hex 32
sudo install -d -m 0750 -o root -g xogoria-socket /etc/xogoria
sudo nano /etc/xogoria/streamerbot-socket.env
```

Use the following environment file, replacing the key:

```dotenv
SOCKET_HOST=127.0.0.1
SOCKET_PORT=8787
SOCKET_PATH=/streamerbot
STREAMERBOT_SOCKET_API_KEY=replace-with-the-generated-key
AUTH_TIMEOUT_MS=10000
HEARTBEAT_INTERVAL_MS=30000
MAX_PAYLOAD_BYTES=20971520
SYNC_TIMEOUT_MS=60000
CAPTURE_DIRECTORY=/var/www/xogoria.com/storage/command-captures
```

Protect and start it:

```bash
sudo chown root:xogoria-socket /etc/xogoria/streamerbot-socket.env
sudo chmod 0640 /etc/xogoria/streamerbot-socket.env
sudo usermod -aG www-data xogoria-socket
sudo install -d -o www-data -g www-data -m 2770 /var/www/xogoria.com/storage/command-captures
sudo chown www-data:www-data /var/www/xogoria.com/storage/command-captures
sudo chmod 2770 /var/www/xogoria.com/storage/command-captures
sudo cp /var/www/xogoria.com/services/streamerbot-socket/deploy/xogoria-streamerbot-socket.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now xogoria-streamerbot-socket
sudo systemctl status xogoria-streamerbot-socket --no-pager
curl --fail --silent --show-error http://127.0.0.1:8787/health
```

Inspect live service logs with:

```bash
sudo journalctl -u xogoria-streamerbot-socket -f
```

## Nginx and TLS

Install the supplied virtual host and validate before reloading:

```bash
sudo cp /var/www/xogoria.com/services/streamerbot-socket/deploy/nginx-socket.xogoria.com.conf /etc/nginx/sites-available/socket.xogoria.com
sudo ln -s /etc/nginx/sites-available/socket.xogoria.com /etc/nginx/sites-enabled/socket.xogoria.com
sudo nginx -t
sudo systemctl reload nginx
```

After DNS resolves to the VPS, obtain the certificate:

```bash
sudo apt update
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d socket.xogoria.com
sudo nginx -t
curl --fail --silent --show-error https://socket.xogoria.com/health
```

Expected health response:

```json
{"ok":true,"service":"xogoria-streamerbot-socket","authenticatedConnections":0,"uptimeSeconds":123}
```

## Streamer.bot

Create a persisted global named `socketApiKey` containing the dedicated key.
Configure the second WebSocket Client as:

```text
Name: Xogoria
URL: wss://socket.xogoria.com/streamerbot
Auto Connect: enabled
Reconnect: enabled
```

The Streamer.bot `Opened` action must send an `authenticate` message before any
other message. The server replies with `auth_ok`. Send `connection_test` next;
the server replies with `connection_test_ack`, which completes transport
verification.

## Enable WebSocket snapshots

After updating an existing installation, add these values to
`/etc/xogoria/streamerbot-socket.env`:

```dotenv
SYNC_TIMEOUT_MS=60000
CAPTURE_DIRECTORY=/var/www/xogoria.com/storage/command-captures
```

Give the restricted service account access only to the capture directory and
install the updated unit, which explicitly allows that path through its
`ProtectSystem=strict` sandbox:

```bash
sudo usermod -aG www-data xogoria-socket
sudo install -d -o www-data -g www-data -m 2770 /var/www/xogoria.com/storage/command-captures
sudo chown www-data:www-data /var/www/xogoria.com/storage/command-captures
sudo chmod 2770 /var/www/xogoria.com/storage/command-captures
sudo cp /var/www/xogoria.com/services/streamerbot-socket/server.js /opt/xogoria/streamerbot-socket/
sudo cp /var/www/xogoria.com/services/streamerbot-socket/package.json /opt/xogoria/streamerbot-socket/
sudo cp /var/www/xogoria.com/services/streamerbot-socket/package-lock.json /opt/xogoria/streamerbot-socket/
sudo chown -R xogoria-socket:xogoria-socket /opt/xogoria/streamerbot-socket
sudo -u xogoria-socket npm --prefix /opt/xogoria/streamerbot-socket ci --omit=dev --ignore-scripts
sudo cp /var/www/xogoria.com/services/streamerbot-socket/deploy/xogoria-streamerbot-socket.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl restart xogoria-streamerbot-socket
sudo systemctl status xogoria-streamerbot-socket --no-pager
```

Use `streamerbot/CheckSyncTimer.cs` in the existing repeating timer action and
`streamerbot/HandleXogoriaMessage.cs` in the action triggered by messages from
the Xogoria configured WebSocket Client. Retain the previous HTTP code in a
disabled fallback action until the WebSocket path has been verified in
production.
