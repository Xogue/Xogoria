# Xogoria development conventions

## Scope

- `html/_core` is the only source of application classes.
- Do not restore or reference `html/_core_old`; its live clip and sound-queue behavior was migrated and the remaining code was obsolete.
- The `.drawio` files are not requirements sources. Use repository code and explicitly supplied screenshots/requirements.

## Application flow

- Web entry points load `html/includes/session.php` and use its `$webController`.
- API entry points load `html/includes/bootstrap.php` and delegate request execution/output to `ApiController`.
- `ServiceFactory` owns the object graph. Avoid constructing managers/controllers in entry points.
- `DataController` creates context objects; `ContextManager` owns and caches them for one request.
- `ApiController` selects a newly created worker through `ServiceFactory`, primes it with request/worker contexts, runs it, and emits its `WorkerResult`.

## Results and logging

- Workers always return `WorkerResult`; expected validation failures use `WorkerResult::failure()`.
- API output is JSON. Do not echo from workers or managers.
- Use `Logger` for immediate structured logging. Include useful context at the call site.
- Do not add global replacement maps, deferred report queues, logging traits, or additional logger implementations.

## Style

- Target PHP 8.4 and use typed properties, parameters, and returns where practical.
- Prefer constructor property promotion and strict comparisons.
- Validate at boundaries and return clear domain errors rather than allowing null dereferences.
- Keep credentials in `private/privateConfig.json`; never put secret values in logs or source.

## Verification

- Run `composer dump-autoload -o` after adding or moving classes.
- Run `php -l` over changed PHP files.
- Run `php -d zend.assertions=1 -d assert.exception=1 tests/smoke.php`.

## Admin

- `adminResources.json` is the allowlist and rendering schema for editable database resources. Add fields there instead of hard-coding another table form.
- `AdminResourceManager` owns allowlisted CRUD; `AdminConfigManager` owns validated configuration writes and backups.
- All admin mutations go through an admin-authorized API endpoint and require the session CSRF token.
- Clip review is a lifecycle workflow, not generic CRUD: pending, approved, ignored, and deletion-requested states must remain distinguishable.
- A deletion must not be reported as complete unless the external provider confirms it. Preserve external links in `clipDeletionQueue.json` when manual deletion remains.
