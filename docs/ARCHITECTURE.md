# Xogoria architecture

## API request flow

1. `html/api/xogoriaApi.php` loads the shared bootstrap.
2. `ApiController` creates/uses `ServiceFactory` and requests the input context from `ContextManager`.
3. `ContextManager` asks `DataController` to create contexts and caches each one for the request.
4. `ApiController` asks `ServiceFactory` for the worker matching `InputDataContext::getRequest()`.
5. The worker receives `WorkerContext` and `InputDataContext`, validates the active profile and identity as required, and returns `WorkerResult`.
6. `ApiController` serializes the result as JSON and records a structured API log entry.

The factory centralizes construction and lazy service reuse. Web and API entry points no longer share a controller: `WebController` exposes page-facing services, while `ApiController` owns API dispatch and output.

## Context lifetime

Contexts are scoped to the current PHP request. `ContextManager` caches the exact instance it creates. Passing `true` to `getInputData()` rebuilds request/session input and updates the cached input context. External identities supplied in request data require the configured API key; otherwise session identity is authoritative.

## Worker contract

Workers implement `WorkerInterface`, are newly selected per dispatch, and return `WorkerResult` for both success and expected failure. Workers do not echo output or write chat messages. Interaction workers verify that the action belongs to the active game/profile and perform currency charging server-side.

## Logs

`Logger` writes one JSON object per line to `html/_core/_init/_logs/{common|api|web}.log`. Records contain timestamp, level, channel, message, and call-site context. The prior report/response placeholder queues and logger traits were removed.

## Admin control center

`manageXogoria.php` composes `AdminController`, `AdminResourceManager`, and `AdminConfigManager` from the shared request `ServiceFactory`. Database editors are rendered and validated from `adminResources.json`. Configuration editors are restricted to existing public application JSON files, validate required structure, and create timestamped backups before saving.

Clip review is intentionally separate. `ClipReviewManager` composes local clip metadata, Twitch discovery, and the deletion registry. Only approved and enabled records reach the public clip collection. Ignored clips remain excluded without deletion; deletion requests disable the clip and retain Twitch/Backblaze links until external deletion is confirmed manually.
