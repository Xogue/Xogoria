# API response consolidation notes

## Implemented in this pass

The public `xogoriaApi.php` response is normalized by `ApiResponseNormalizer`. Every response from the four worker domains now has these top-level keys, in this order:

- `success`: boolean outcome.
- `code`: stable response code from `responseCodes.json` and `ResponseLibrary`.
- `message`: the rendered, chat-ready message from `responseCodes.json`.
- `value`: the primary result. This remains for compatibility with existing consumers.
- `request`: an object that always contains `name`, `type`, and `action`.
- `meta`: an object containing the named values used to render the message plus any diagnostics explicitly added by the controller.

The obsolete `WorkerInterface::createWorkerResult()` method and its four identical implementations were removed. They had no callers. Simple interactions and power spawning now share `InteractionWorker::chargeAndSend()`, so balance validation, charging, relay failure handling, refunds, and successful balance metadata have one implementation.

Collection results are reduced from a database-shaped one-row list to the selected row itself. For example, `value` for a quote is now `{ "text": "..." }`, while `meta.quote` contains the immediately usable chat value.

## Editing or adding a response

1. Edit `html/_core/_init/config/responseCodes.json`. Message-only edits take effect immediately because the catalog is loaded at runtime; constant regeneration is not required.
2. Each new non-section record needs a unique code, a unique `constantName`, `success`, `httpStatus`, and `messageTemplate`.
3. Add every variable used by `messageTemplate` to `replaceMap`. The map value is the key the worker must place in `WorkerResult` metadata.
4. When adding a response or changing a code/constant name, run `php _internal/constantGenerator.php responselibrary` from the repository root. This regenerates `html/_core/_init/_status/ResponseLibrary.php`.
5. Return `WorkerResult::success(...)` or `WorkerResult::failureCode(...)` from the worker using that generated constant and all required metadata.
6. Add a smoke assertion that normalizes the outcome and verifies both the code and fully rendered message.

## Potential future consolidation: typed collection repository

`MySqlManager::fetchData()` and `fetchDataByDetail()` are string-key dispatchers over another string-key dispatcher in `runQueryFromJson()`. `CollectionWorker` then has to understand database row field names such as `requirement`, `text`, `gameName`, and `customName`. A typed `CollectionRepository` could expose methods such as `findQuote(string $term): ?array`, `randomObjective(): ?array`, and `findMonsterName(string $gameName): ?array`.

That change would remove both `match` blocks from `MySqlManager`, prevent invalid keyword/query combinations, and make return types explicit. It was not done here because `MySqlManager::fetchData()` also serves page-building calls for complete command, lore, quote, objective, and monster collections. Splitting it safely requires cataloging those non-API consumers and deciding whether each repository returns database rows or domain objects. Mixing those decisions into the response overhaul would create a broad migration with little immediate benefit to Streamer.bot.

## Potential future consolidation: worker priming contracts

Every worker implements `prime(WorkerContext, InputDataContext)`, but currency and collection workers only use `InputDataContext`; configure and interaction use both. The unused argument is currently harmless and keeps dispatch uniform. Possible alternatives are constructor injection of request-scoped contexts, or two interfaces (one for input-only workers and one for game-aware workers).

This was not changed because either alternative pushes conditional construction logic into `ServiceFactory`/`ApiController`, which may cost more complexity than it removes with only four workers. Revisit this if more input-only workers are added or if worker instances become immutable.

## Potential future consolidation: all JSON endpoints

The new normalized contract intentionally covers the Streamer.bot-facing `html/api/xogoriaApi.php` endpoint and every request worker it dispatches. Admin, clip lifecycle, and sound queue endpoints also reuse `ApiController::sendJson()`, but they have purpose-built payloads consumed by browser/admin code.

Those endpoints could eventually adopt an envelope similar to this one, but doing so requires coordinated client changes and response catalogs for clip lifecycle and admin CRUD outcomes. They should not be silently routed through `ApiResponseNormalizer`, because changing fields such as `rows`, `pending`, or queue payloads would break existing clients. If a site-wide API contract is desired, version it first (for example `/api/v2/...`) or add the standard envelope while temporarily retaining their existing domain fields.

## Potential future consolidation: response metadata versus diagnostics

`meta` currently contains both message variables (safe operational data) and admin-only relay/debug diagnostics. This is backward-compatible with the interaction page, which reads `meta.cost` and `meta.cooldown`. If metadata grows significantly, the contract could split it into fixed `data` and `diagnostics` objects. That should be a versioned contract decision because Streamer.bot and the interaction page will rely on the present top-level names after this overhaul.
