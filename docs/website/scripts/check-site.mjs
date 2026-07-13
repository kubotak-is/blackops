import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { distRoot } from './website-paths.mjs';

const routes = [
  '/',
  '/getting-started/installation/',
  '/getting-started/directory-structure/',
  '/getting-started/first-operation/',
  '/getting-started/local-runtime/',
  '/getting-started/quickstart/',
  '/operations/authoring/',
  '/operations/generators/',
  '/operations/lifecycle/',
  '/execution/http-and-deferred/',
  '/execution/context/',
  '/database/migrations/',
  '/database/outcomes/',
  '/database/retention/',
  '/reference/configuration/',
  '/reference/application-bootstrap/',
  '/reference/project-cli/',
  '/reference/current-status/',
];

const pages = new Map();
for (const route of routes) {
  pages.set(route, await readFile(htmlPath(route), 'utf8'));
}

const landing = pages.get('/');
requireText(landing, 'href="/getting-started/installation/"', 'Landing install action');
requireText(landing, 'href="/getting-started/quickstart/"', 'Landing quickstart action');
requireJourney('/getting-started/installation/', '/getting-started/directory-structure/');
requireJourney('/getting-started/directory-structure/', '/getting-started/first-operation/');
requireJourney('/getting-started/first-operation/', '/getting-started/local-runtime/');

const documentation = pages.get('/getting-started/installation/');
for (const section of ['Getting Started', 'Operations', 'Execution', 'Database', 'Reference']) {
  requireText(documentation, `>${section}<`, `Sidebar section ${section}`);
}

for (const [route, html] of pages) {
  requireText(html, '<html lang="ja"', `${route} Japanese locale`);
  requireText(html, 'Document Channel:', `${route} document channel`);
  requireText(html, '<code>main</code>', `${route} main channel`);
  requireText(html, 'Latest Stable:', `${route} stable label`);
  requireText(html, '<code>1.0.0</code>', `${route} stable version`);
  requireText(html, 'class="sl-skip-link', `${route} skip link`);
  requireText(html, 'href="#_top"', `${route} skip target`);
  requireText(html, 'id="_top"', `${route} page heading target`);
  requireText(html, 'aria-label="検索"', `${route} search label`);
  requireText(html, 'aria-keyshortcuts="Control+K"', `${route} search shortcut`);
  requireText(html, 'テーマの選択', `${route} theme selector label`);
  if (/href="[^"]+\.md(?:[?#][^"]*)?"/.test(html)) {
    throw new Error(`${route} contains a source Markdown link instead of a public route.`);
  }
}

for (const [route, html] of pages) {
  if (route === '/') continue;
  requireText(html, 'aria-label="メニュー"', `${route} mobile menu label`);
  requireText(html, 'aria-expanded="false"', `${route} mobile menu state`);
  requireText(html, 'aria-controls="starlight__sidebar"', `${route} mobile menu control`);
}

const pagefindEntry = JSON.parse(await readFile(path.join(distRoot, 'pagefind/pagefind-entry.json'), 'utf8'));
if (pagefindEntry.languages?.ja?.page_count !== routes.length) {
  throw new Error(`Pagefind must index ${routes.length} Japanese pages.`);
}

await verifySearch();
console.log(`Site navigation, accessibility markup, and Pagefind search checks passed for ${routes.length} pages.`);

function htmlPath(route) {
  return route === '/' ? path.join(distRoot, 'index.html') : path.join(distRoot, route, 'index.html');
}

function requireText(content, expected, label) {
  if (!content.includes(expected)) {
    throw new Error(`${label} was not found in the static site.`);
  }
}

function requireJourney(from, to) {
  const html = pages.get(from);
  const targets = [...html.matchAll(/href="([^"]+)"/g)].map((match) => new URL(match[1], `https://example.test${from}`).pathname);
  if (!targets.includes(to)) {
    throw new Error(`User journey link is missing: ${from} -> ${to}`);
  }
}

async function verifySearch() {
  const originalFetch = globalThis.fetch;
  globalThis.fetch = async (input) => {
    const url = new URL(input instanceof Request ? input.url : input);
    if (url.origin !== 'https://pagefind.test' || !url.pathname.startsWith('/pagefind/')) {
      return new Response(null, { status: 404 });
    }
    const relative = decodeURIComponent(url.pathname.slice('/pagefind/'.length));
    if (relative.includes('..')) {
      return new Response(null, { status: 400 });
    }
    try {
      const file = path.join(distRoot, 'pagefind', relative);
      const body = await readFile(file);
      return new Response(body, {
        status: 200,
        headers: { 'content-type': file.endsWith('.wasm') ? 'application/wasm' : 'application/octet-stream' },
      });
    } catch {
      return new Response(null, { status: 404 });
    }
  };

  try {
    const module = await import(pathToFileURL(path.join(distRoot, 'pagefind/pagefind.js')).href);
    const search = module.createInstance({
      basePath: 'https://pagefind.test/pagefind/',
      language: 'ja',
      noWorker: true,
    });
    await search.init();
    const result = await search.search('Operation');
    if (result.results.length === 0) {
      throw new Error('Pagefind returned no result for Operation.');
    }
    const records = await Promise.all(result.results.slice(0, 5).map(({ data }) => data()));
    if (!records.some(({ url }) => url.includes('/operations/') || url.includes('/getting-started/first-operation/'))) {
      throw new Error('Pagefind did not return an Operation guide for Operation.');
    }
    await search.destroy();
  } finally {
    globalThis.fetch = originalFetch;
  }
}
