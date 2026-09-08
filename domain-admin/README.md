# Symfony skeleton with PHP 8.5 Runtime

## Usage

```bash
# Clone the repository, rename services, and run tests to verify everything is working correctly.
(PROJECT=my-symfony-project PORT=57123 \
  && git clone https://github.com/knork-fork/symfony-skeleton-php-8-5 "$PROJECT" \
  && cd "$PROJECT" \
  && sed -i "s/skeleton-php-fpm/${PROJECT}-php-fpm/g; s/skeleton-webserver/${PROJECT}-webserver/g; s/12345:80/${PORT}:80/g" docker-compose.yml .github/workflows/.github-ci.yml docker/* .env \
  && docker compose up -d --build \
  && docker/composer install \
  && docker/quality-check \
  && rm -rf .git && git init && git add -A && git commit -m "Initial commit")
```

## Features

#### Tech Stack

- **Backend:** PHP 8.5 / Symfony 8.0 (served via nginx + php-fpm)

#### Quality Gates

**Backend (PHP):**
- `docker/phpunit` -- PHPUnit tests (unit, integration, functional)
- `docker/phpstan` analyse -- PHPStan static analysis (level 5)
- `docker/php-cs-fixer` fix --dry-run -- PHP CS Fixer code style

#### Docker Setup

All services run in Docker. The `docker/` directory contains shortcut scripts:

| Script | Description |
|--------|-------------|
| `docker/shell` | Open a bash shell in the php-fpm container |
| `docker/composer` | Run Composer commands |
| `docker/phpstan` | Run PHPStan |
| `docker/php-cs-fixer` | Run PHP CS Fixer |
| `docker/phpunit` | Run PHPUnit |

#### Services & Ports

| Service | Container | Port |
|---------|-----------|------|
| Nginx (API) | skeleton-webserver | 57123 |
