# Xogoria operational-readiness audit

Audit date: 2026-07-14  
Scope: the active site in `html/`, including public pages, API endpoints, Twitch login, the interaction panel, admin controls, clip review, configuration, persistence, and external integrations.  
Change policy: this audit did not change application code or configuration. This document is the only file created.

## Executive summary

The site is not ready to call fully operational yet. PHP syntax and checked JSON files are valid, but several user-facing paths are definitely broken from static tracing alone. The highest-priority launch blockers are:

1. The clip collection discards every clip returned by its own API.
2. A logged-in user can call currency mutation actions against their own balance without an admin/API authorization check.
3. The live-status banner calls a request type that the API does not implement.
4. Collection and command search/pagination controls are disconnected from their JavaScript.
5. The interaction panel reports failures as successes, calculates cost differently from the server, and has no server-side cooldown enforcement.
6. Twitch chat sending always interprets the normal Twitch response incorrectly and returns failure.
7. Publicly reachable `admin/PHPInfo.php` discloses server configuration.
8. Several active-looking integration classes call methods/classes that do not exist.

There are also important items that cannot be verified in this workspace because it has no MySQL `mysqli` extension, Redis extension/server, JavaScript runtime/browser automation, production database, OAuth account, Twitch credentials, Backblaze account, or game-panel endpoint. Those items have explicit manual tests below.

## What was verified here

- All PHP files under `html/` pass `php -l` with PHP 8.4.12.
- All application JSON files checked by PowerShell decode successfully.
- PHP has `curl`, `openssl`, `json`, `mbstring`, and PDO, but not `mysqli` or `redis`.
- Node.js is not installed, so JavaScript could not be syntax-checked with Node.
- Routes, rendered controls, JavaScript selectors, request payloads, worker selection, authorization checks, database calls, and external-service code were traced statically.
- The smoke test was inspected but not executed because constructing its services would write to application logs in this environment. It also does not exercise pages, controls, a database, Redis, Twitch, clips, or the game panel.

## Confirmed launch blockers

### OR-001 — Clip collection always filters out approved clips

Severity: blocker  
Status: confirmed by API/JavaScript contract mismatch

What happens: `/api/clips/clipsApi.php` returns approved clips but does not include `review_status`. `brbClips.js` then requires `c.review_status === 1`, so every returned clip is removed and the page/overlay has an empty clip list.

Intended behavior: the API already filters for approved and enabled clips; those clips should populate the grid/player and rotate when autoplay is enabled.

Relevant files:

- `html/api/clips/clipsApi.php`
- `html/assets/js/brbClips.js` (the filter near lines 407–414)
- `html/clips.php`

Best fix: use one naming contract. Prefer removing the redundant client approval check because the public API already enforces it, or return a documented camelCase/snake_case approval field and test it consistently.

Manual test: approve and enable one clip in admin, open `/clips.php`, verify at least one card and player appear, then test `?overlay=1`, recent/top modes, autoplay on/off, and an API response with zero clips.

### OR-002 — Clip play counts use a removed route

Severity: high  
Status: confirmed

What happens: play-count beacon/fallback requests go to `/api/clips_play.php`, which does not exist. The endpoint is `/api/clips/clipsPlay.php`.

Intended behavior: a completed/started clip event should increment `play_count` once through the existing endpoint.

Relevant files: `html/assets/js/brbClips.js`, `html/api/clips/clipsPlay.php`.

Best fix/test: change both beacon and fetch fallback to the real route. In browser DevTools verify a 200 response and confirm exactly one database increment per intended play (including beacon fallback and page close).

### OR-003 — Any logged-in user can mutate their own currency balance

Severity: blocker/security  
Status: confirmed from authorization flow

What happens: `CurrencyWorker` permits `setUserBalance`, `addToUser`, and `deductCost` for any identified session user. `ApiController` only requires the private API key when the request supplies a different external identity. A normal logged-in browser session can therefore POST `request=currency&action=addToUser` or `setUserBalance` for itself.

Intended behavior: balance awards/administrative sets must require a trusted server API key or admin authorization. Viewer-initiated spending should only occur as part of a validated interaction, not through a freely callable generic deduction.

Relevant files:

- `html/_core/workers/CurrencyWorker.php`
- `html/_core/_init/ApiController.php`
- `html/_core/contexts/InputDataContext.php`
- `html/api/xogoriaApi.php`

Best fix: define authorization per action, not only per identity source. Separate trusted award/admin operations from viewer read/spend operations. Do not trust client-provided amount/cost.

Manual test: while logged in as a non-admin, send each currency action from DevTools/curl. Mutation actions must return 401/403 and leave the DB unchanged; `checkBalance` may remain readable for the session user.

### OR-004 — Live stream status has no matching API request

Severity: high  
Status: confirmed

What happens: the banner calls `/api/xogoriaApi.php?type=streamStatus`. `ApiController` requires `request`, and `ServiceFactory` has no `streamStatus` worker. The request returns `missing_request`; the banner displays “Could not check if Xogue is live.”

Intended behavior: return a stable JSON status (`checking`, `live`, or `offline`) and show/hide the interaction call-to-action.

Relevant files: `html/includes/partials/liveBanner.php`, `html/assets/js/common/ui.js`, `html/_core/_init/ApiController.php`, `html/_core/_init/ServiceFactory.php`, `html/_core/helpers/StreamStatus.php`.

Best fix: add one supported status endpoint/worker and update the banner to call it. Do not use the current `StreamStatus` class unchanged; it references missing `PrivateLoader`, `CurlResponse`, and `TwitchBridge`, uses obsolete curl calls, and constructs `DataStore` without its required context.

Manual test: test live, offline, Twitch timeout, expired token, Redis unavailable, and repeated concurrent requests. The banner must stop polling and fail softly without a PHP error.

### OR-005 — Collection search, clear, favorites, and pagination controls do not work

Severity: high  
Status: confirmed from missing asset and selector mismatch

What happens:

- `collections.php` does not load `collections.js`.
- Even if loaded, the JavaScript looks for `#quotesSearch`, `#objectivesSearch`, `#monstersSearch`, `.qsClear`, `.objClear`, `.monClear`, and custom pager IDs/classes.
- The active templates render generic `.collectionSearch`, `.clearSearch`, and page-level `.pageButton`/`.pageInfo` controls.
- Therefore search, clear, Favorites Only, Prev, and Next have no compatible handlers.

Intended behavior: each collection tab filters only its own table, favorites limits quotes, and pagination updates visible rows and page state.

Relevant files: `html/collections.php`, `html/assets/js/collections.js`, and `html/libs/templates/minecraft/collection/{quote,objective,monster}/*`.

Best fix: define one reusable collection-control contract and load the asset. Scope controls and pager to the nearest collection container instead of unique, mismatched IDs.

Manual test: use more than ten records in every collection; test typing, case-insensitive matching, no results, clear, favorite toggle, next/previous boundaries, tab switching, and combined search/favorite filtering.

### OR-006 — Command search/clear/pagination controls do not work

Severity: high  
Status: confirmed

What happens: command templates render `.collectionSearch` and `.clearSearch`; `commands.js` only handles category/permission tabs. The page’s Prev/Next controls also have no handler. AJAX replacement does not attach a compatible handler either.

Intended behavior: search within the currently filtered command list and page long results.

Relevant files: `html/commands.php`, `html/assets/js/commands.js`, `html/libs/AjaxCommandList.php`, `html/libs/templates/minecraft/collection/command/*`.

Best fix/test: implement the same scoped collection controller used above, then test every category/permission combination, search, clear, empty results, and 11+ results.

### OR-007 — Interaction UI reports failed actions as successful

Severity: blocker for paid interactions  
Status: confirmed

What happens: `InteractCard.activate()` awaits `send()` but ignores its returned success/failure object. It always displays “Sent!”, deducts the displayed balance, applies success styling, and starts a cooldown even after a 4xx/5xx response, wrong-game rejection, insufficient funds, invalid JSON, panel failure, or network failure.

Intended behavior: only the server’s successful `WorkerResult` should trigger success state, balance update, and cooldown. Failures should show the returned domain message and leave displayed balance unchanged.

Relevant files: `html/assets/js/libs/InteractCard.js`, `html/_core/workers/InteractionWorker.php`, `html/_core/workers/_WorkerResult.php`.

Best fix: make `send()` throw or return a single documented result contract; branch on both HTTP status and result success. Return the authoritative new balance from the server.

Manual test: test success, insufficient balance, logged out, wrong profile/game, disabled action, panel 401/500/timeout, malformed response, and offline browser.

### OR-008 — Interaction price and cooldown shown to viewers do not match server behavior

Severity: high  
Status: confirmed

What happens:

- The browser applies a special nonlinear duration cost after duration 10; the server charges `base cost × duration`.
- The browser calculates a changed cooldown but `startCooldown()` uses the original `initCooldown`.
- The server accepts the client cooldown field but never enforces a cooldown.
- `CooldownManager` is not connected to `InteractionWorker`/`ServiceFactory`, so direct or repeated API calls bypass browser-only cooldowns.

Intended behavior: the server owns price and cooldown calculations; the UI displays those same values and cannot bypass them.

Relevant files: `html/assets/js/libs/InteractCard.js`, `html/_core/workers/InteractionWorker.php`, `html/_core/managers/CooldownManager.php`, `html/_core/_init/ServiceFactory.php`.

Best fix: calculate price/cooldown from server-side interaction configuration, enforce cooldown before deduction/panel calls, and return authoritative price, balance, and remaining cooldown.

Manual test: compare displayed cost, actual DB deduction, and cooldown at durations 1, 10, 11, and maximum; repeat through DevTools during cooldown and from a second tab.

### OR-009 — Interaction profile restrictions are rendered incorrectly

Severity: high  
Status: confirmed

What happens: tab buttons use allowed simple types, but the page renders every interaction in every simple type from the active game and does not filter cards to the profile’s allowed actions. Disallowed controls may be visible and then fail server-side. The server check is the only reliable restriction.

Intended behavior: render only types/actions allowed by the active profile, while retaining server-side enforcement.

Relevant files: `html/interact.php`, `html/_core/elements/Profile.php`, `html/_core/workers/InteractionWorker.php`.

Best fix/test: derive buttons and cards from the same profile permission set. Test profiles with zero, one, and several allowed actions and verify forged API requests remain rejected.

### OR-010 — Special interaction controls are effectively dead

Severity: high if specials are launch scope  
Status: confirmed

What happens: `interact.php` checks `$profileInteractions`, but that variable is never defined, so spawn/bat-claim sections do not render. The active `assets/js/libs/interactPanel.js` has no handlers for `#spawnBtn`, amount +/- controls, or bat claim. Only the unused `interactPanel.old.js` contains older handlers. `InteractionManager::viewerBatClaimed()` still sends `say This command needs to be updated`.

Intended behavior: permitted special controls render, validate the current Twitch user/profile, invoke a supported worker action, and show the real result.

Relevant files: `html/interact.php`, `html/assets/js/libs/interactPanel.js`, `html/assets/js/interactPanel.old.js` (reference only), `html/_core/managers/InteractionManager.php`, special-interaction classes/templates.

Best fix/test: decide whether specials are version-one scope. If yes, expose them through the current worker/result flow and implement active handlers; if no, remove/hide the incomplete markup. Test every button, minimum/maximum amount, permissions, insufficient funds, and panel failures.

### OR-011 — Game-panel command success is determined from response-body emptiness

Severity: blocker for reliable paid actions  
Status: confirmed

What happens: `InteractionManager::sendCommands()` treats any non-empty response as failure and an empty response as success, while `CurlController` does not expose HTTP status to the caller. A legitimate JSON success body is refunded/reported failed; an empty HTTP error can be treated as success. If an early command succeeds and a later one fails, the full price is refunded although the action partially occurred.

Intended behavior: use documented HTTP status/response semantics and distinguish full success, full failure, and partial execution.

Relevant files: `html/_core/managers/InteractionManager.php`, `html/_core/tools/CurlController.php`.

Best fix: return a typed HTTP result containing status, body, and transport error. Define idempotency/partial-failure policy before issuing multi-command actions.

Manual test: mock or stage 200 empty, 200 JSON, 400 JSON, 401, 500, timeout, connection refusal, and failure on command 2 of 3; verify currency behavior for each.

### OR-012 — Twitch chat sending always rejects the normal response shape

Severity: high if chat output is launch scope  
Status: confirmed

What happens: after sending a chat message, `TwitchUserBridge::sendChatMessage()` requires an `access_token` field in the chat-send response. Twitch’s chat message response is message data, not a token response, so this check causes false failure before `is_sent` is evaluated.

Intended behavior: accept a successful HTTP response with a sent message result and return true.

Relevant files: `html/_core/bridges/twitch/TwitchUserBridge.php`, `html/_core/tools/CurlController.php`.

Best fix/test: inspect HTTP status and the chat endpoint’s documented response fields. Test a valid token, missing scope, expired token, wrong sender/broadcaster, rate limit, and successful message in a non-production/test channel.

### OR-013 — Public PHP information disclosure

Severity: blocker/security  
Status: confirmed

What happens: `html/admin/PHPInfo.php` contains only `phpinfo()` and performs no session/admin authorization. If served, anyone can inspect PHP version, extensions, paths, environment/configuration details, and server variables.

Intended behavior: production must not expose phpinfo publicly.

Best fix/test: remove it from the document root or protect it outside the application with strict admin/network controls. From a logged-out private browser, `/admin/PHPInfo.php` must return 404 (preferred) or 403, never the PHP information page.

### OR-014 — OAuth return target permits a scheme-relative external redirect

Severity: high/security  
Status: confirmed

What happens: `forceRelativeUrl()` strips hosts only from strings beginning `http://` or `https://`, but returns `//attacker.example/path` unchanged. That value can be stored as `returnTo` and later placed in a `Location` header after login.

Intended behavior: post-login redirects must be local application paths only.

Relevant files: `html/_core/helpers/SiteHelpers.php`, `html/_core/bridges/twitch/auth/TwitchAuthStart.php`, `html/_core/bridges/twitch/TwitchAppBridge.php`, `html/_core/bridges/twitch/auth/TwitchCallback.php`.

Best fix/test: accept only a path beginning with exactly one `/`, reject backslashes/control characters/schemes, and optionally allowlist destinations. Test `//host`, encoded variants, `https://host`, backslashes, CR/LF, a valid path, and a path with query string.

## Other confirmed defects and stability risks

### OR-015 — OAuth session is not rotated or explicitly hardened

No `session_regenerate_id(true)` occurs after login, and the code does not explicitly set `Secure`, `HttpOnly`, or `SameSite` cookie options. Production php.ini may compensate, but the application does not guarantee it. The Twitch access token is stored in the PHP session. Relevant files: `html/includes/bootstrap.php`, `TwitchCallback.php`, `TwitchUserBridge.php`. Rotate the session after successful OAuth and explicitly configure secure cookie parameters before `session_start()`. Verify cookies over HTTPS and test fixation with a pre-login session ID.

### OR-016 — Stored/admin-editable content is inserted into public HTML without escaping

Collection, lore, feature, interaction, and nav values are assembled with `str_replace`/raw `echo`. An admin-entered quote, command, lore body, game label, interaction description, or modified display name containing markup can become stored XSS. Relevant files include `CollectionManager.php`, `Command.php`, `Quote.php`, `LoreChapter.php`, `Interaction.php`, `commands.php`, `collections.php`, `lore.php`, `features.php`, `interact.php`, and `includes/partials/nav.php`. Escape by output context; if lore deliberately supports markup, sanitize it with a documented allowlist. Test `<img src=x onerror=alert(1)>`, quotes, ampersands, Unicode, and intended lore formatting.

### OR-017 — Disabled commands and inactive objectives are shown publicly

`sql.json` selects all command rows regardless of `enabled` and all objectives regardless of `active`; assemblers do not filter them. This makes the admin toggles ineffective for public listing (random objectives do filter active). Add the intended predicates or filter in the domain layer. Test toggling each flag in admin and refreshing public pages/API consumers.

### OR-018 — “Everyone” command permission filter actually includes every permission

`AjaxCommandList.php` omits the permission filter when `perms=everyone`, so the “Everyone” tab includes VIP/mod-only rows. It also reads `$_GET['category']` and `$_GET['perms']` without defaults, causing warnings on direct access when error display is enabled. Decide whether “Everyone” means exact public permissions or all commands, label it accurately, validate allowed filter values, and test direct/malformed queries.

### OR-019 — Multiple lore attachments can overwrite one another in memory

`LoreChapter::addAudio()` keys audio only by chapter ID, so only the last audio row for a chapter survives. `addStream()` keys by chapter ID plus date, so two recordings on the same date collide. Use attachment IDs/sort order as keys. Test two audio records and two same-date stream records on one chapter and confirm all render in order.

### OR-020 — Public mojibake is already present

`about.php` and `features.php` contain visible sequences such as `â€”` and `â€™`. They will remain corrupted even though UTF-8 response headers are correct. Replace corrupted source text with actual Unicode characters and verify editor/file/server UTF-8 handling. Search the rendered site and repository for `â`, `Ã`, and replacement characters.

### OR-021 — Features includes an unreachable “coming soon” section

`features.php` contains a permanently hidden Chaos Crucible section with no active control to reveal it. Either remove it from version one or connect it intentionally. Verify every visible feature description matches currently operational behavior, especially how gems are earned; that earning pipeline is not implemented in this web repository and must be checked in the bot/event system.

### OR-022 — Admin interaction selector reloads even when save fails

The Set & Refresh handler in `assets/js/libs/interactPanel.js` does not validate HTTP/result success before reloading. `ConfigureWorker` says moderator access is required but actually permits only `admin`/`owner`. The select also does not mark the current profile selected, so it can display the first option rather than active state. Check the result, align wording/roles, render selected state, and test admin, moderator, ordinary user, invalid game/profile, failed config write, and concurrent admins.

### OR-023 — Active game/profile writes lack the admin config manager’s safety

`ConfigureWorker` writes `core.json` through `ConfigStore`, bypassing the admin editor’s backups and broader validation. The direct write is not the same atomic/backed-up path used by `AdminConfigManager`. A selector change is site-global. Route all config mutations through one validated, locked/backed-up service. Test filesystem denial, simultaneous saves, invalid profile, and recovery from the newest backup.

### OR-024 — Admin configuration validation is shallow

The JSON editor validates JSON shape and only a few top-level keys. It does not verify referenced profiles/types, required interaction fields, numeric ranges, URLs, template availability, or whether `core.activeProfile` belongs to `core.activeGame`. A syntactically valid save can break page construction. Add schema/domain validation and offer a validation-only preview. Test missing/unknown types, negative cost/duration, malformed command counts, nonexistent profile, and rollback.

### OR-025 — Admin CRUD depends on an unverified production schema

`adminResources.json` hard-codes current table/column assumptions, but no schema or migration exists in this workspace. The local runtime lacks `mysqli`, so list/add/edit/delete and foreign-key behavior could not be exercised. Before launch, compare every allowlisted field to production, confirm generated IDs, nullable/default fields, unique constraints, foreign keys, character set, and transactions. Back up the database, then test create/edit/delete for every resource with representative Unicode and boundary values.

### OR-026 — Admin delete can remove referenced records without dependency guidance

Generic CRUD sends direct deletes and does not explain or manage lore audio/stream dependencies, user references, or other foreign keys. Depending on schema, the operation will fail generically or orphan/cascade records. Define deletion rules and give the admin a specific error/confirmation. Test a chapter with attachments and any referenced rows.

### OR-027 — Clip deletion is only a request/manual queue

Request deletion changes DB state and records Twitch/Backblaze links; it does not call either provider to delete. This is acceptable only if the UI clearly means “request/manual queue.” Never report deletion complete until providers confirm. Test queue file write permissions, repeat requests, missing local URL, failed DB update, manual completion workflow, and preservation of both links.

### OR-028 — Clip approval does not upload to Backblaze

The current `ClipReviewManager::approve()` only stores metadata in MySQL. `BackblazeBridge` is not part of this flow and is itself obsolete/broken: it references missing `PrivateLoader` and nonexistent `CurlController::get()`, `postJson()`, and `postRaw()`. If local/Backblaze playback is required for version one, it is not operational. Otherwise label Twitch-hosted playback as the supported first-version behavior.

### OR-029 — Obsolete Twitch clip bridge is instantiated in the active controller

`TwitchController` constructs `TwitchClipBridge`, although the current clip feature uses `TwitchClipService`. The bridge calls nonexistent curl methods (`get`, `download`) and references undeclared/uninitialized dependencies. Remove it from the active object graph or migrate any genuinely needed behavior to the current service. Test Twitch login after removal because `TwitchController` is used by OAuth.

### OR-030 — Twitch clip credentials appear to rely on a manually stored token

`TwitchClipService` reads a private Twitch token and does not acquire/refresh an app token. It will fall back to stored clips when refresh fails, but admin cannot discover new clips after expiration. Confirm what token is stored, its scopes/type/expiry, and implement refresh if recent-clip discovery is required. Test expired/revoked credentials and Twitch 401/429.

### OR-031 — Sound queue has hard runtime dependencies and no graceful fallback

`DataStore` directly instantiates `Redis`; the extension is absent locally. Connection/auth/TLS failures are not caught. `soundQueue/pop.php` requires the private API key and can long-poll up to 300 seconds. Verify the production Redis extension/server, host/port/firewall, authentication if required, worker concurrency, timeout limits, and web-server request timeouts. Test empty queue, FIFO order, Unicode value, Redis restart, wrong key, missing key, and 300-second polling without exhausting PHP workers.

### OR-032 — MySQL failures become empty content in many public paths

The local environment has `mysqlnd` but no `mysqli`. `MySqlBridge` logs and returns false; managers frequently convert that to empty arrays/default balance. This can make database failure look like “no content” rather than a service error. Production must install `mysqli`; add health checks and user-safe failure states. Test wrong credentials, unavailable host, missing table/column, charset, and recovery.

### OR-033 — Database writes are not transactional for paid actions

Currency deduction, multiple panel commands, and refund are separate operations. Concurrent requests can also read the same balance and overwrite it. Use an atomic conditional balance update/transaction and define external-call compensation/idempotency. Load-test simultaneous interactions from two tabs and verify balance never goes negative or receives an incorrect refund.

### OR-034 — Curl wrapper hides HTTP failures

`CurlController::send()` logs only cURL transport errors and returns the raw body. Callers cannot reliably distinguish HTTP 401/404/500 from success. It also does not explicitly close the cURL handle. Return status/body/error in a value object, log safe URL/context, and close resources. Do not log tokens or secrets. Test all HTTP and transport cases.

### OR-035 — Asset lookup is ambiguous by filename

`FileMap` recursively returns the first matching filename. Assets/templates are selected by basename rather than an explicit path; duplicate names can silently resolve to the wrong directory as the project grows. It also assumes a found path in `findRelativeFilepath()`. Prefer explicit manifest keys/paths and fail clearly on duplicates/missing files. Add a validation test that all requested assets/templates resolve uniquely.

### OR-036 — Old/orphan JavaScript can mislead future deployment

`assets/js/common/AdminOverlayManager.js` calls nonexistent `/api/AdminDbUpdate.php`, and `interactPanel.old.js`, `commands.old.js`, `collections.old.js`, and `features.old.js` are not the active implementations. They are not confirmed user-facing because active pages do not load them, but accidental bundling/reference would reintroduce broken behavior. Remove or move dead browser assets outside the document root after confirming no external overlay references them.

## Control-by-control manual acceptance matrix

Use a clean production-like staging copy with browser DevTools open. Test desktop and mobile widths, keyboard-only navigation, and at least Chrome plus Firefox/Safari-equivalent.

| Area/control | Expected result | Current assessment / test needed |
|---|---|---|
| Header Twitch/YouTube/Patreon/Discord links | Correct official destination in a safe new tab | Not externally verified; links use plain HTTP for Twitch/YouTube/Patreon and should be changed/redirect-tested with HTTPS. Add `rel="noopener"` consistently. |
| Header Clips button | Opens clips and shows active state | Route exists; active-class comparison appears to use `clips.php` while helper normally returns `clips`, so visually verify active state. |
| Main nav links | Correct page and active item | Routes exist; visually/keyboard test every page. |
| Twitch Login | OAuth state validated, user synchronized, safe local redirect | Requires real Twitch and DB; test success, deny, state mismatch, expired code, DB failure, duplicate/new user, return URL attacks. |
| Logout | Session/token removed and user returns to About | Code exists; test cookie deletion under real cookie path/domain and back-button/cache behavior. Prefer POST+CSRF for logout if threat model requires it. |
| Live badge/banner | Accurate live/offline state, safe failure | Confirmed broken (OR-004). Query override `?streamStatus=live/offline` only tests presentation, not integration. |
| About Quick/Long tabs | Switch body and heading | Handler exists; browser/layout/keyboard test needed. |
| Features Gems/Interacting tabs | Switch content and reveal game shelf | Handler exists; test with every configured game and no games. |
| Feature game/type pills | Reveal correct type content | Handler exists; first-type logic assumes available children and CSS state; test every config. |
| Lore chapter/subchapter toggles | One section expands/collapses and scrolls safely | Handler exists; DB/render/browser test needed, including empty lore. |
| Lore audio button | Play/stop one recording; recover from blocked/missing audio | Handler exists; multiple audio rendering is broken (OR-019). Test CORS/MIME/range requests and mobile autoplay policy. |
| Lore stream links | Open correct recording | Requires DB URLs and external verification. |
| Collections tabs | Show quote/objective/monster section | Common handler exists; test title and state. |
| Collection search/clear/favorites/pagers | Scoped filtering and paging | Confirmed broken (OR-005). |
| Command category/permission tabs | Correct filtered records | AJAX handler exists, but permission behavior has OR-018. Test URL encoding, DB failure, and injection-like text. |
| Command search/clear/pagers | Scoped filtering and paging | Confirmed broken (OR-006). |
| Clip Latest/Most Viewed | Reload and sort approved clips | UI handler exists, but no clips survive OR-001. `range` is accepted/returned but not used for filtering server-side; verify intended date-range behavior. |
| Clip cards/player | Select/play correct clip and update metadata | Blocked by OR-001; must browser-test Twitch embed parent/CSP/autoplay, local URL fallback, offset/max duration, and removed clips. |
| Clip overlay/rotation | OBS-friendly playback without interaction | Cannot test without browser/OBS and clips. Test query parameters, audio policy, network loss, long-running memory, and scene reload. |
| Interaction login/balance display | Correct identity and authoritative balance | Requires OAuth/DB; raw output escaping issue exists. |
| Interaction type tabs | Only permitted types/cards | Confirmed rendering mismatch (OR-009). |
| Interaction duration +/- | Bounded duration, matching price/cooldown | Confirmed client/server mismatch (OR-008). |
| Interaction activate | Real success/failure and exactly-once charge | Confirmed unreliable (OR-007/011/033). |
| Admin Set & Refresh | Authorized global game/profile save | Error handling/selected state problems (OR-022/023). |
| Special spawn/bat controls | Validated action or intentionally absent | Confirmed dead/incomplete (OR-010). |
| Admin sidebar sections | Show correct resource/config/clip section | Handler appears present; browser/a11y test required. |
| Admin Add/Edit/Save/Cancel/Delete | Validated CRUD with accurate errors | Cannot test without schema/DB; see OR-025/026. Include cancel-after-edit behavior and immutable fields. |
| Admin config Save | Validate, back up, atomically replace, remain bootable | Basic JSON/backups exist; shallow validation OR-024. Test same-second saves (backup filename can collide), permission failure, and rollback. |
| Clip Review filter/list | Show pending/ignored/deletion/all and selectable preview | Requires Twitch/DB/browser; test Twitch unavailable fallback and stored-only records. |
| Clip Approve/Ignore/Delete Request | Correct distinct lifecycle state | Requires DB/Twitch; deletion is manual queue, not provider deletion. |
| Clip library metadata Save | Persist title/favorite/enabled/timing/local fields | Requires schema; validate max duration/offset ranges and URL safety. |
| Deletion list links | Preserve and open Twitch/Backblaze targets | File write/browser test required; no completion action currently visible. |
| Sound queue pop | Authenticated FIFO dequeue/long poll | Requires Redis and trusted client; see OR-031. |

## Items that cannot be tested from this workspace

These are not assertions that the feature is broken. They are release tests that require infrastructure or credentials unavailable here.

1. Production MySQL connectivity, schema compatibility, data integrity, indexes, foreign keys, transactions, backups, and every DB-backed page/control.
2. Redis connectivity, queue behavior, cooldown design, persistence, and concurrent long polling.
3. Twitch OAuth redirect registration, real login/scopes, token expiry/revocation, chat send, clips API, rate limits, deleted clips, and embed restrictions.
4. Backblaze authentication/upload/delete and whether stored file URLs are publicly playable. Current active approval does not attempt upload.
5. Game/panel API authentication, exact response contract, command correctness for Minecraft/Hytale, partial execution, and timeouts.
6. OBS browser-source behavior, autoplay/audio restrictions, overlay transparency, long-running rotation, GPU/media errors, and scene transitions.
7. Browser JavaScript syntax/runtime, responsive layout, CSS stacking, focus management, keyboard use, screen-reader labels, color contrast, reduced motion, and cross-browser media behavior.
8. Web-server routing/case sensitivity. Windows is case-insensitive; production Linux is not. Verify every requested path with exact case.
9. HTTPS, HSTS, CSP, frame/media/connect policies, Referrer-Policy, Permissions-Policy, compression/cache headers, upload/body limits, and PHP-FPM/web-server timeouts.
10. Production filesystem ownership/permissions for logs, config backups, atomic rename, and `clipDeletionQueue.json`.
11. External social/video/audio links and all database-controlled URLs.
12. The off-site bot/event system that awards gems for chat, follows, donations, and subscriptions; this repository only describes those awards.

## Recommended stabilization order

1. Close OR-003, OR-013, OR-014, OR-015, and OR-016 before exposing the site publicly.
2. Repair clips (OR-001/002), live status (OR-004), and collection/command controls (OR-005/006/017/018).
3. Do not enable paid interactions until OR-007/008/009/011/033 are fixed and staged against a non-production panel.
4. Decide version-one scope for special interactions, Backblaze, sound queue, Twitch chat output, and live status. Remove/hide incomplete scope instead of presenting controls that cannot succeed.
5. Establish the production DB schema/migration and run the complete admin CRUD matrix on a disposable database copy.
6. Add integration tests with fake Twitch/panel HTTP servers and a disposable MySQL/Redis environment. The current smoke test is useful but far too narrow for release confidence.
7. Run the browser matrix, security-header scan, OAuth tests, OBS soak test, and backup/restore drill.

## Minimum go-live checklist

- [ ] All blockers/high issues above are fixed, deliberately removed from scope, or explicitly accepted.
- [ ] `mysqli` and Redis (if queue/status/cooldowns use it) are installed and health-checked in production.
- [ ] Production DB schema is versioned, backed up, restored in a drill, and tested through every admin resource.
- [ ] Secrets exist only in `private/privateConfig.json`/server secret storage, are excluded from web access/backups/logs, and have been rotated for launch.
- [ ] `PHPInfo.php` and obsolete public endpoints/assets are removed or denied.
- [ ] HTTPS/session/security headers and local-only OAuth redirects are verified.
- [ ] Twitch OAuth, clips, chat, rate-limit, and expired-token paths are tested with real credentials.
- [ ] Interaction success/failure/currency/cooldown/concurrency behavior is verified against staging services.
- [ ] All forms, buttons, selects, tabs, search fields, media controls, links, and error states in the matrix pass in real browsers.
- [ ] PHP lint, optimized Composer autoload, the smoke test, new integration tests, and JavaScript lint/tests pass in the deployment environment.
- [ ] Logs are writable, do not contain credentials/tokens, rotate correctly, and provide actionable failures without exposing internals to users.
- [ ] A rollback package and database/config rollback procedure are prepared and tested.

## Suggested evidence to retain for the release

Keep a short release folder containing: exact commit/archive hash, deployment config checksum (excluding secrets), DB migration version, passing test output, screenshots of the browser/control matrix, OAuth/Twitch test results, OBS soak duration, security-header scan, backup/restore result, and a list of deliberately deferred features. This will make the “first fully operational version” reproducible rather than dependent on memory.
