# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Architecture

This repo has two independently-deployed pieces sharing one JSON file:

1. **The reverse proxy** (repo root: `nginx/`, `php/resolve.php`, `docker-compose.yml`, `config/`) — nginx uses `auth_request` to hit `php/resolve.php` (a PHP-FPM sidecar) on every incoming request. `resolve.php` reads `config/domains.json` fresh on every call (no reload/restart needed on change), looks up the request's `Host` header, and returns the backend port via the `X-Upstream-Port` header, or 403 if there's no match (nginx remaps 403 → 404 to nginx so a missing domain looks like a clean 404, not an auth failure). nginx then proxies to `127.0.0.1:<port>`.
2. **`domain-admin/`** — a small Symfony 8 app (its own `docker-compose.yml`, independent from the root one) providing a web UI to manage `config/domains.json` instead of hand-editing it. It writes to the same file the resolver reads, so changes take effect immediately. Auth is a single-firewall, form-login setup backed by `App\Security\JsonUserProvider`, which reads users from `var/security/users.json` (created/updated via `bin/console app:user:create`, never via a signup form).

### `config/domains.json` format

Keyed by domain, each value is either:
- a plain integer port (legacy/default — a mapping with no comment), or
- an object `{"port": <int>, "comment": <string>}` when a comment has been set via the UI.

Both consumers (`php/resolve.php` and `domain-admin`'s `App\Service\DomainsRepository`) must handle both shapes — `DomainsRepository::toStorable()` only writes the object form when a comment is non-empty, to stay backward-compatible with tools consuming the file and avoid needlessly rewriting untouched entries.

### `domain-admin` internals

- `App\Service\DomainsRepository` is the sole read/write path to `config/domains.json` (path injected via `DOMAINS_CONFIG_PATH` env var, bound in `config/services.yaml`). Writes use `flock` for concurrency safety and always re-sort keys.
- `App\Service\PortChecker` reports each mapping's online/offline status by connecting out to the Docker host (`HOST_GATEWAY`, i.e. `host.docker.internal`, wired via `extra_hosts` in `domain-admin/docker-compose.yml`) rather than checking inside the container — mappings point at ports on the host machine, not inside this container.
- All mutating routes in `App\Controller\DomainsApiController` require a CSRF token (`X-CSRF-Token` header, token id `domains-api`) in addition to the session-based `ROLE_ADMIN` access control from `security.yaml`.
- The dashboard (`templates/dashboard/index.html.twig` + `public/js/app.js`) is a single vanilla-JS page polling `GET /api/domains` every 10s and doing optimistic in-place row editing; there's no frontend build step or framework.
- `App\Twig\AssetVersionExtension` (`asset_version()`) appends a cache-busting suffix to static asset URLs referenced from Twig.

## Commands

All `domain-admin` tooling runs inside its `domain-admin-php-fpm` container via wrapper scripts in `domain-admin/docker/` — run these from the `domain-admin/` directory:

```bash
docker compose up -d --build      # start the app (also: from repo root, `docker compose up -d --build` for the proxy itself)
docker/composer install           # install PHP deps
docker/console app:user:create    # create/update the dashboard login
docker/quality-check              # PHP CS Fixer (dry-run) + PHPStan + PHPUnit, in that order — run this before considering PHP changes done
docker/phpunit                    # tests only
docker/phpstan analyse            # static analysis only
docker/php-cs-fixer fix           # auto-fix style (omit --dry-run unlike quality-check)
docker/shell                      # shell into the php-fpm container
```

To run a single test: `docker/phpunit --filter testMethodName` or `docker/phpunit path/to/SomeTest.php`.

### Deploying to prod — cache gotcha

Prod runs with `APP_ENV=prod` / `APP_DEBUG=0` (per the `.env.local` generated in the README's setup steps). In this mode Symfony compiles Twig templates and the service container into `var/cache/prod/` and does **not** invalidate that cache based on file mtimes (only dev mode does). Since `var/cache` lives in the bind-mounted app directory rather than being baked into the Docker image, a plain `docker compose up -d --build` on prod does **not** clear it — the old compiled templates/behavior keep being served even though the new code is on disk. After every prod deploy, run:

```bash
domain-admin/docker/console cache:clear --env=prod
```

(or run it from inside `domain-admin/` as `docker/console cache:clear --env=prod`.)
