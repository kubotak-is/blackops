// Usage:
//   node capture.mjs stills 1.5 6 11 ...   -> stills/t-<sec>.png
//   node capture.mjs frames [fps]          -> $REEL_FRAMES/%05d.png for the full reel
import { createServer } from 'node:http';
import { fileURLToPath } from 'node:url';
import { mkdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright-core';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)));
const types = Object.freeze({ '.html': 'text/html; charset=utf-8', '.ttf': 'font/ttf', '.woff2': 'font/woff2' });
const resolveLocalFile = (requestUrl) => {
  try {
    const pathname = decodeURIComponent(new URL(requestUrl, 'http://127.0.0.1').pathname);
    if (!pathname.startsWith('/') || pathname.includes('\0')) return null;
    const candidate = path.resolve(root, `.${pathname === '/' ? '/reel.html' : pathname}`);
    const relative = path.relative(root, candidate);
    if (relative.startsWith('..') || path.isAbsolute(relative)) return null;
    if (!types[path.extname(candidate)]) return null;

    return candidate;
  } catch {
    return null;
  }
};

const server = createServer(async (request, response) => {
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    response.writeHead(405, { allow: 'GET, HEAD' });
    response.end();
    return;
  }

  const file = resolveLocalFile(request.url ?? '/');
  if (!file) {
    response.writeHead(404);
    response.end();
    return;
  }

  try {
    const body = await readFile(file);
    response.writeHead(200, { 'cache-control': 'no-store', 'content-type': types[path.extname(file)] });
    response.end(request.method === 'HEAD' ? undefined : body);
  } catch {
    response.writeHead(404);
    response.end();
  }
});

const listen = () => new Promise((resolve, reject) => {
  const onError = (error) => {
    server.off('listening', onListening);
    reject(error);
  };
  const onListening = () => {
    server.off('error', onError);
    resolve(server.address().port);
  };
  server.once('error', onError);
  server.once('listening', onListening);
  server.listen({ host: '127.0.0.1', port: 0 });
});

const closeServer = () => new Promise((resolve) => server.close(() => resolve()));
const [mode, ...rest] = process.argv.slice(2);
const fps = Number(rest[0] ?? 30);
if (mode !== 'stills' && mode !== 'frames') throw new Error('Usage: node capture.mjs <stills|frames> [values...]');
if (mode === 'frames' && (!Number.isInteger(fps) || fps <= 0)) throw new Error('Frame rate must be a positive integer.');

const port = await listen();
let browser = null;
try {
  browser = await chromium.launch({
    ...(process.env.PW_EXEC ? { executablePath: process.env.PW_EXEC } : {}),
  });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 1 });
  await page.goto(`http://127.0.0.1:${port}/reel.html`, { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.evaluate(() => Promise.all([...document.fonts].map((font) => font.load())));

  if (mode === 'stills') {
    const stillsDir = path.resolve(process.env.REEL_STILLS ?? path.join(root, 'stills'));
    await mkdir(stillsDir, { recursive: true });
    for (const value of rest) {
      const time = Number(value);
      if (!Number.isFinite(time) || time < 0) throw new Error(`Invalid still time: ${value}`);
      await page.evaluate((currentTime) => window.renderAt(currentTime), time);
      await page.screenshot({ path: path.join(stillsDir, `t-${value}.png`) });
    }
  } else {
    const duration = await page.evaluate(() => window.reelDuration);
    const total = Math.round(duration * fps);
    const framesDir = path.resolve(process.env.REEL_FRAMES ?? path.join(root, 'frames'));
    await mkdir(framesDir, { recursive: true });
    const started = Date.now();
    for (let index = 0; index < total; index += 1) {
      await page.evaluate((currentTime) => window.renderAt(currentTime), index / fps);
      await page.screenshot({ path: path.join(framesDir, `${String(index).padStart(5, '0')}.png`) });
      if (index % 150 === 0) console.log(`frame ${index}/${total} ${((Date.now() - started) / 1000).toFixed(1)}s`);
    }
    console.log(`done ${total} frames in ${((Date.now() - started) / 1000).toFixed(1)}s`);
  }
} finally {
  await browser?.close();
  await closeServer();
}
