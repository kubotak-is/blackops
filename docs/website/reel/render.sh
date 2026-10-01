#!/bin/sh
# Render reel.html into the reviewed landing video assets.
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname "$0")" && pwd)
WEBSITE_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/.." && pwd)
OUT="$WEBSITE_DIR/public/assets/reel"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.63.0-noble}"
PLAYWRIGHT_CORE_DIR="${PLAYWRIGHT_CORE_DIR:-$WEBSITE_DIR/node_modules/playwright-core}"
FFMPEG="${FFMPEG:-ffmpeg}"
FONT_URL='https://raw.githubusercontent.com/google/fonts/baa2e5561af8a4873b058859dcfe158bdd033942/ofl/notosansjp/NotoSansJP%5Bwght%5D.ttf'
FONT_SHA256='c2f3b4d463500a2ddcd3849cded1fceeb9fd6d1c32e6cbecd568453ba50fc68f'

command -v docker >/dev/null 2>&1 || { echo 'docker is required.' >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { echo 'curl is required to fetch the pinned render font.' >&2; exit 1; }
command -v sha256sum >/dev/null 2>&1 || { echo 'sha256sum is required.' >&2; exit 1; }
command -v "$FFMPEG" >/dev/null 2>&1 || { echo "ffmpeg executable not found: $FFMPEG" >&2; exit 1; }

FFMPEG_VERSION=$("$FFMPEG" -version | sed -n '1s/^ffmpeg version \([^ ]*\).*/\1/p')
# Distribution and static builds commonly append a suffix (for example,
# 7.0.2-static or 7.1.1-ubuntu). Validate the numeric major separately so
# those suffixes remain accepted while malformed majors are rejected.
case "$FFMPEG_VERSION" in
  ''|[!0-9]*) echo "Unable to read an ffmpeg version: $FFMPEG_VERSION" >&2; exit 1 ;;
esac
FFMPEG_MAJOR=${FFMPEG_VERSION%%.*}
case "$FFMPEG_MAJOR" in
  ''|*[!0-9]*) echo "Unable to read the ffmpeg major version: $FFMPEG_VERSION" >&2; exit 1 ;;
esac
if [ "$FFMPEG_MAJOR" -lt 6 ]; then
  echo "ffmpeg 6 or newer is required; found $FFMPEG_VERSION." >&2
  exit 1
fi
FFMPEG_ENCODERS=$("$FFMPEG" -hide_banner -encoders 2>&1)
for encoder in libx264 libvpx-vp9; do
  if ! printf '%s\n' "$FFMPEG_ENCODERS" | grep -Eq "[[:space:]]$encoder([[:space:]]|$)"; then
    echo "ffmpeg encoder is required but unavailable: $encoder" >&2
    exit 1
  fi
done

[ -f "$PLAYWRIGHT_CORE_DIR/package.json" ] || {
  echo "Pinned playwright-core dependency is missing: $PLAYWRIGHT_CORE_DIR" >&2
  echo 'Run pnpm --dir docs/website install --frozen-lockfile first.' >&2
  exit 1
}
PLAYWRIGHT_VERSION=$(sed -n '0,/"version"[[:space:]]*:[[:space:]]*"[^"]*"/s/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' "$PLAYWRIGHT_CORE_DIR/package.json")
[ "$PLAYWRIGHT_VERSION" = '1.63.0' ] || {
  echo "playwright-core 1.63.0 is required; found ${PLAYWRIGHT_VERSION:-unknown}." >&2
  exit 1
}

WORK="${REEL_WORK_DIR:-}"
OWN_WORK=0
if [ -z "$WORK" ]; then
  WORK=$(mktemp -d "${TMPDIR:-/tmp}/blackops-reel.XXXXXX")
  OWN_WORK=1
else
  mkdir -p "$WORK"
fi
cleanup() {
  status=$?
  if [ "$OWN_WORK" -eq 1 ]; then rm -rf "$WORK" || true; fi
  exit "$status"
}
trap cleanup EXIT HUP INT TERM

SOURCE="$WORK/source"
FRAMES="$WORK/frames"
mkdir -p "$SOURCE/fonts" "$SOURCE/node_modules/playwright-core" "$FRAMES" "$OUT"
cp "$SCRIPT_DIR/reel.html" "$SOURCE/reel.html"
cp "$SCRIPT_DIR/capture.mjs" "$SOURCE/capture.mjs"
cp "$WEBSITE_DIR/public/fonts/UbuntuSans.woff2" "$SOURCE/fonts/UbuntuSans.woff2"
cp "$WEBSITE_DIR/public/fonts/UbuntuMono.woff2" "$SOURCE/fonts/UbuntuMono.woff2"
curl --fail --silent --show-error --location --output "$SOURCE/fonts/NotoSansJP.ttf" "$FONT_URL"
printf '%s  %s\n' "$FONT_SHA256" "$SOURCE/fonts/NotoSansJP.ttf" | sha256sum --check --status - || {
  echo 'Pinned Noto Sans JP font hash did not match.' >&2
  exit 1
}

echo "playwright-core=$PLAYWRIGHT_VERSION image=$PLAYWRIGHT_IMAGE"
echo "ffmpeg=$FFMPEG_VERSION encoders=libx264,libvpx-vp9 fps=30 size=1920x1080 duration=36s"
echo "font-sha256=$FONT_SHA256"
docker run --rm --init --ipc=host -u "$(id -u):$(id -g)" \
  -e HOME=/tmp -e REEL_FRAMES=/frames \
  -v "$SOURCE:/reel:ro" -v "$FRAMES:/frames" \
  -v "$PLAYWRIGHT_CORE_DIR:/reel/node_modules/playwright-core:ro" \
  -w /reel "$PLAYWRIGHT_IMAGE" node capture.mjs frames 30

"$FFMPEG" -y -hide_banner -loglevel error -framerate 30 -i "$FRAMES/%05d.png" \
  -c:v libx264 -preset slow -crf 23 -tune animation -pix_fmt yuv420p -movflags +faststart -an \
  -t 36 "$OUT/blackops-reel.mp4"
"$FFMPEG" -y -hide_banner -loglevel error -framerate 30 -i "$FRAMES/%05d.png" \
  -c:v libvpx-vp9 -b:v 0 -crf 36 -row-mt 1 -deadline good -cpu-used 2 -pix_fmt yuv420p -an \
  -t 36 "$OUT/blackops-reel.webm"
"$FFMPEG" -y -hide_banner -loglevel error -i "$FRAMES/00078.png" -q:v 3 "$OUT/blackops-reel-poster.jpg"

echo '--- rendered assets ---'
sha256sum "$OUT/blackops-reel.mp4" "$OUT/blackops-reel.webm" "$OUT/blackops-reel-poster.jpg"
ls -la "$OUT"
