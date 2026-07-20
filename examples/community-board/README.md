# BlackOps Board Foundation

`examples/community-board/` is an independent full-stack reference application. It consumes the repository root framework through a Composer path repository, but it is not part of `blackops/skeleton` and does not change `examples/quickstart/`.

The application contains the unauthenticated `ShowBoardWelcome` Operation, an Application-owned identity and session boundary, authenticated Post／Comment Operations, and a Deferred weekly digest. Registration, login, current-user display, logout, the paginated feed, post create/edit/delete, detail, comments, digest progress, and digest detail run through SvelteKit server loads and actions. Final visual design remains for a later task.

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
| `GET` | `/digests/{digestId}` | Show an owned immutable digest |

`POST /digests` accepts a canonical UTC ISO week and returns `202 Accepted`. The worker counts posts and comments inside that week's half-open UTC range, stores an immutable owner-scoped snapshot, and publishes a typed terminal outcome. `/digests/operations/{operationId}` provides an SSR progress surface backed by the authenticated status endpoint. For local retry testing only, set `DIGEST_FAIL_FIRST_ATTEMPT=true`; the default is `false`.

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

The Generated Output lives under `frontend/src/lib/server/blackops/generated/` and is ignored. Only Application-owned wrappers below `frontend/src/lib/server/blackops/*.server.ts` import it. Routes call those wrappers and expose small safe view models and form failures; generated results, backend URLs, and raw session credentials never enter page data or browser code. The opaque operation ID is the only operation metadata exposed for the progress route and its same-origin wait request.

The authenticated product routes include `/posts`, `/posts/new`, `/posts/{postId}`, `/posts/{postId}/edit`, `/digests`, `/digests/operations/{operationId}`, and `/digests/{digestId}`. They use standard HTML forms and remain usable without client-side JavaScript. Owner actions are hidden for other users, while the PHP application remains the authorization boundary and conceals unknown, malformed, and non-owned resources behind the same safe not-found result.

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

Run the browser-facing product journey:

```bash
bash tests/Consumer/community-board-product-journey.sh
```

It drives registration, feed, create, comment, edit, and delete entirely through SvelteKit, checks safe authentication and backend-failure behavior, and verifies that generated clients, private configuration, credentials, and backend details stay out of browser artifacts and rendered responses.

Run the Deferred digest journey:

```bash
bash tests/Consumer/community-board-digest.sh
```

It verifies accepted progress, finite wait fallback, first-attempt retry, second-attempt completion, immutable same-week snapshots, hard-delete recounting, status and detail ownership concealment, and browser-sensitive boundaries against the real worker and PostgreSQL transport.

The current pages intentionally use minimal semantic controls. Final visual design and icon integration are deferred; the planned icon source is [Reicon](https://reicon.dev).
