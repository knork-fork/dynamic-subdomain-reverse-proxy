# dynamic-subdomain-reverse-proxy

An nginx reverse proxy that routes incoming requests to different local ports based on the request's domain/subdomain, read from a JSON config file that's re-read on every request, no nginx reload or restart needed when mapping changes.

## Running it

```
docker compose up -d --build
```

This starts nginx (listening on the port configured in `config/proxy.env`) plus a small PHP-FPM sidecar that resolves each request's `Host` header to a backend port.

Default nginx port is 20500. To override it locally, create the gitignored `config/proxy.env.local` (e.g. `LISTEN_PORT=8080`); it takes precedence over `config/proxy.env`.

## Serving HTTPS (local, not committed)

TLS is configured per machine through gitignored files, so nothing certificate-specific lives in the repo. The nginx template includes `/etc/nginx/local/*.conf` inside its `server` block and takes extra `listen` options from `LISTEN_OPTS` (empty by default).

To serve HTTPS with a Let's Encrypt certificate (example for `luka.cld.gcld.io`):

1. `config/proxy.env.local`:

   ```
   LISTEN_PORT=443
   LISTEN_OPTS=ssl
   ```

2. `nginx/local/ssl.conf` (gitignored directory):

   ```
   ssl_certificate /etc/letsencrypt/live/luka.cld.gcld.io/fullchain.pem;
   ssl_certificate_key /etc/letsencrypt/live/luka.cld.gcld.io/privkey.pem;
   ```

3. `docker-compose.override.yml` (gitignored; Compose merges it into `docker-compose.yml` automatically):

   ```yaml
   services:
     web:
       volumes:
         - ./nginx/local:/etc/nginx/local:ro
         - /etc/letsencrypt:/etc/letsencrypt:ro
   ```

Mount the whole `/etc/letsencrypt` directory, not just the cert files: the files in `live/` are symlinks into `archive/`. After a certificate renewal, run `docker compose exec web nginx -s reload`.

Without these files the proxy serves plain HTTP on the port from `config/proxy.env`.

## Setting up `config/domains.json`

Create `config/domains.json` (it's gitignored, since it's local/environment-specific) mapping each domain to the local port it should be proxied to:

```json
{
    "foo.example.com": 3000,
    "bar.example.com": 8080
}
```

Add, remove, or edit entries at any time — changes take effect on the next request, immediately. A request for a host with no matching entry gets a 404 page; if the file is missing, empty, or not valid JSON, requests get a 500 page instead.

## Domain admin

This repo comes with a small admin interface for managing the domain-to-port mapping. 
It's available at port 20501 by default.

Start it up with:

```bash
cd domain-admin \
    && printf 'APP_ENV=prod\nAPP_DEBUG=0\nAPP_SECRET=%s\n' "$(openssl rand -hex 32)" > .env.local \
    && docker compose up -d --build \
    && docker/composer install
```

There's no signup form — create (or update) the login it should accept by running:

```bash
domain-admin/docker/console app:user:create
```

It'll ask for a username and password and store the username plus a bcrypt hash of the password in `domain-admin/var/security/users.json`. Run it again with the same username to change that user's password.

Then visit `http://localhost:20501`, log in, and use the dashboard to view, add, edit, or remove entries. Changes made here are written to `config/domains.json` and take effect immediately. Login persists across browser restarts; use the Logout button to end the session explicitly.

Protip: add `"<domain-admin url":<domain-admin port>` to your `config/domains.json` so you can manage the mappings from the same interface that serves them.