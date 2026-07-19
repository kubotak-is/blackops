# BlackOps Board Foundation

`examples/community-board/` is an independent full-stack reference application. It consumes the repository root framework through a Composer path repository, but it is not part of `blackops/skeleton` and does not change `examples/quickstart/`.

This foundation contains one unauthenticated Inline Operation, `ShowBoardWelcome`, and a SvelteKit server-rendered landing page. Identity, posts, comments, deferred digests, and final visual design are intentionally left for later tasks.

## Runtime topology

```text
Browser -> SvelteKit SSR/BFF -> .server.ts wrapper -> generated Operation -> BlackOps HTTP -> PostgreSQL
                                                     Deferred worker (profile)
```

The browser connects to SvelteKit on `http://localhost:5173`. BlackOps is available separately on `http://localhost:8081` for local debugging. Browser code does not import generated Operations or read `BLACKOPS_BASE_URL`.

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

The Generated Output lives under `frontend/src/lib/server/blackops/generated/` and is ignored. Only `frontend/src/lib/server/blackops/operations.server.ts` imports it. `+page.server.ts` calls that Application-owned Wrapper and exposes a small safe view model to the page.

## Start the local runtime

After the explicit preparation and build steps:

```bash
docker compose up -d postgres http frontend
```

Start the Deferred Worker only when a later feature needs it:

```bash
docker compose --profile worker up -d worker
```

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
