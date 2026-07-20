# BlackOps Board Foundation

`examples/community-board/` is an independent full-stack reference application. It consumes the repository root framework through a Composer path repository, but it is not part of `blackops/skeleton` and does not change `examples/quickstart/`.

The application contains the unauthenticated `ShowBoardWelcome` Operation, an Application-owned identity and session boundary, and authenticated Post／Comment Operations. Registration, login, current-user display, and logout run through SvelteKit server actions. The browser-facing Post／Comment journey, deferred digests, and final visual design remain for later tasks.

## Runtime topology

```text
Browser -> SvelteKit SSR/BFF -> .server.ts wrapper -> generated Operation -> BlackOps HTTP -> PostgreSQL
                            \-> Application auth route (/auth/*)  -> PostgreSQL
                                                     Deferred worker (profile)
```

The browser connects to SvelteKit on `http://localhost:5173`. BlackOps is available separately on `http://localhost:8081` for local debugging. Browser code does not import generated Operations, read `BLACKOPS_BASE_URL`, or receive the raw session token in page/action data.

`POST /auth/users`, `POST /auth/sessions`, and `DELETE /auth/sessions/current` belong to the application router. Other paths delegate to BlackOps. The authenticated `GET /me` Operation receives only a verified `ActorRef`. Passwords use Argon2id, and the database stores only the SHA-256 hash of each 256-bit opaque session token.

Authenticated callers can use the following BlackOps Inline Operations through the HTTP boundary:

| Method | Route | Behavior |
|---|---|---|
| `GET` | `/posts` | Deterministic paginated feed |
| `GET` | `/posts/{postId}` | Post detail and ordered comments |
| `POST` | `/posts` | Create a post |
| `PUT` | `/posts/{postId}` | Update an owned post |
| `DELETE` | `/posts/{postId}` | Delete an owned post and cascade its comments |
| `POST` | `/posts/{postId}/comments` | Add a comment |

Post titles accept 1–120 characters, post bodies 1–10,000 characters, and comment bodies 1–2,000 characters. Update and delete conceal unknown, malformed, and non-owned Post IDs behind the same safe 404 response. `app/Domain/Board/` owns the use cases, models, exceptions, and ports; `app/Infrastructure/` provides the Doctrine DBAL, system-clock, and Symfony UUIDv7 adapters. Deleting a post hard-deletes its comments through the database foreign key in the same transaction.

## Prepare the application

Run each step explicitly from this directory. Setup only copies `.env.example` when `.env` is absent and creates runtime directories; it does not install dependencies, migrate, build, generate, or start services.

```bash
php bin/setup
docker compose build app http frontend
docker compose run --rm app composer install --no-interaction --prefer-dist --no-progress
pnpm --dir frontend install --frozen-lockfile
docker compose run --rm app php blackops database:migrate
docker compose run --rm app php blackops build:compile
docker compose run --rm app php blackops frontend:generate
docker compose run --rm app php blackops frontend:check
```

The development-only Composer path repository points to `../..`. Do not copy Framework source into this application.

## Check and build SvelteKit

```bash
pnpm --dir frontend run check
pnpm --dir frontend run test
pnpm --dir frontend run build
```

The Generated Output lives under `frontend/src/lib/server/blackops/generated/` and is ignored. Only `frontend/src/lib/server/blackops/operations.server.ts` imports it. Server loads call that Application-owned wrapper and expose a small safe view model to pages.

Registration and login move the authentication response token directly into the `community_board_session` cookie. It is `HttpOnly`, `SameSite=Strict`, and scoped to `/`. Secure cookies are the default; local plain HTTP must explicitly set `SESSION_COOKIE_SECURE=false`. The cookie max age uses `SESSION_TTL_SECONDS` and never exceeds the backend session TTL.

## Start the local runtime

After the explicit preparation and build steps:

```bash
docker compose up -d postgres http frontend
```

Start the Deferred Worker only when a later feature needs it:

```bash
docker compose --profile worker up -d worker
```

The Classic PHP front controller is an explicit local fallback and uses the same `bootstrap/http.php` composition as FrankenPHP Worker Mode:

```bash
docker compose --profile classic-mode up -d http-classic
```

FrankenPHP, the Classic fallback, and the Deferred Worker all run with `HOST_UID:HOST_GID` (default `1000:1000`). The FrankenPHP container keeps Caddy's writable config and data below `/tmp`, so every runtime writer can share application files such as `var/log/journal.jsonl` without depending on startup order.

Open `http://localhost:5173`. Stop and remove local state with:

```bash
docker compose down --volumes --remove-orphans
```

## Test the foundation journey

From the repository root:

```bash
bash tests/Consumer/community-board-foundation.sh
```

The Consumer test checks real SvelteKit-to-BlackOps SSR, the safe unavailable state, the server-only import boundary, and generated/build/tracking guards.

Run the identity journey as well:

```bash
bash tests/Consumer/community-board-identity.sh
```

It checks registration, HttpOnly cookie attributes, authorized current-user projection, server-side logout, revocation, login rotation, expiry, CSRF origin rejection, Classic/Worker parity, safe failures, and credential marker absence across database, BlackOps artifacts, generated code, browser build, SSR/action responses, and logs.

Run the Post／Comment journey:

```bash
bash tests/Consumer/community-board-post-comment.sh
```

It checks migration and generated-contract freshness, authentication and validation, feed and detail projections, owner-only mutations with resource concealment, comment ordering, hard-delete cascade, and sensitive-data boundaries against real PostgreSQL and HTTP.
