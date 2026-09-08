# Domain admin

A small Symfony app that provides a login-protected dashboard for managing the
subdomain → port mappings in the project root's `config/domains.json` — the
file nginx's resolver reads to route incoming requests (see the root
`README.md`).

- Sessions are stored in cookies and persist across browser restarts
  (remember-me), until you hit Logout.
- There's no signup form. Logins are created/updated with the `app:user:create`
  console command, which stores a username and bcrypt password hash in
  `var/security/users.json`.
- The dashboard lists every entry in `config/domains.json`, with a red/green
  dot showing whether anything is currently listening on that port — checked
  on the host machine itself (via `host.docker.internal`, see
  `docker-compose.yml`), not inside this app's own container.
- Entries can be added, edited, and removed from the dashboard; changes are
  written straight back to `config/domains.json`.
