# dynamic-subdomain-reverse-proxy

An nginx reverse proxy that routes incoming requests to different local ports based on the request's domain/subdomain, read from a JSON config file that's re-read on every request, no nginx reload or restart needed when mapping changes.

## Running it

```
docker compose up -d --build
```

This starts nginx (listening on the port configured in `config/proxy.env`) plus a small PHP-FPM sidecar that resolves each request's `Host` header to a backend port.

## Setting up `config/domains.json`

Create `config/domains.json` (it's gitignored, since it's local/environment-specific) mapping each domain to the local port it should be proxied to:

```json
{
    "foo.example.com": 3000,
    "bar.example.com": 8080
}
```

Add, remove, or edit entries at any time — changes take effect on the next request, immediately. A request for a host with no matching entry gets a 404 page; if the file is missing, empty, or not valid JSON, requests get a 500 page instead.
