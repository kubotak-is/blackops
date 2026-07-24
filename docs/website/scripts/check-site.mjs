import { createHash } from 'node:crypto';
import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { distRoot } from './website-paths.mjs';

const routes = [
  '/',
  '/concepts/why-blackops/',
  '/concepts/core-concepts/',
  '/concepts/lifecycle/',
  '/getting-started/installation/',
  '/getting-started/directory-structure/',
  '/getting-started/first-operation/',
  '/getting-started/local-runtime/',
  '/getting-started/quickstart/',
  '/operations/authoring/',
  '/operations/generators/',
  '/operations/validation/',
  '/execution/http-and-deferred/',
  '/execution/context/',
  '/database/transactions/',
  '/database/migrations/',
  '/database/seeding/',
  '/database/outcomes/',
  '/database/retention/',
  '/testing/',
  '/testing/community-board/',
  '/deployment/worker-operations/',
  '/security/',
  '/troubleshooting/',
  '/releases/current-status/',
  '/reference/core-api/',
  '/reference/attributes/',
  '/reference/configuration/',
  '/reference/project-cli/',
  '/reference/observer-replay/',
  '/reference/application-bootstrap/',
  '/reference/glossary/',
];

const pages = new Map();
for (const route of routes) {
  pages.set(route, await readFile(htmlPath(route), 'utf8'));
}

const landing = pages.get('/');
requireText(landing, '<h1 id="_top" data-page-title class="astro-', 'Landing product heading');
requireText(landing, 'BlackOps — The PHP Framework</h1>', 'Landing product title');
requireText(landing, 'href="/getting-started/installation/"', 'Landing Installation action');
requireText(landing, 'href="/concepts/why-blackops/"', 'Landing Why BlackOps action');
for (const feature of ['/operations/authoring/', '/concepts/lifecycle/', '/execution/http-and-deferred/']) {
  requireText(landing, `class="landing-feature-link" href="${feature}"`, `Landing feature ${feature}`);
}
if ((landing.match(/class="landing-feature-link"/g) ?? []).length !== 3) {
  throw new Error('Landing must contain three feature link blocks.');
}
requireJourney('/', '/getting-started/installation/');
requireJourney('/concepts/why-blackops/', '/concepts/core-concepts/');
requireJourney('/concepts/core-concepts/', '/concepts/lifecycle/');
requireJourney('/concepts/lifecycle/', '/getting-started/installation/');
requireJourney('/getting-started/installation/', '/getting-started/quickstart/');
requireJourney('/getting-started/quickstart/', '/getting-started/first-operation/');
requireJourney('/getting-started/first-operation/', '/getting-started/directory-structure/');
requireJourney('/getting-started/directory-structure/', '/getting-started/local-runtime/');
requireJourney('/getting-started/first-operation/', '/operations/validation/');
requireJourney('/', '/testing/community-board/');
requireJourney('/testing/', '/testing/community-board/');

requireText(pages.get('/getting-started/quickstart/'), 'diagnostics.failure.trigger', 'Quickstart failure operation');
requireText(pages.get('/getting-started/quickstart/'), 'operation:inspect', 'Quickstart operation inspect');
requireText(pages.get('/getting-started/quickstart/'), 'operation:viewer', 'Quickstart local viewer');
requireText(pages.get('/getting-started/quickstart/'), 'Docker-only Quickstartでは、Host BrowserからLocal Viewerを利用できません', 'Quickstart Docker viewer boundary');
requireText(pages.get('/getting-started/quickstart/'), 'Application／PHP CLI／PostgreSQL／Browserが同じLocal Network Namespace', 'Quickstart native viewer boundary');
requireText(pages.get('/getting-started/quickstart/'), 'Generated Operation Objectから呼ぶ', 'Quickstart frontend journey');
requireText(pages.get('/getting-started/quickstart/'), 'ShowWelcome.fetch', 'Quickstart frontend inline call');
requireText(pages.get('/getting-started/quickstart/'), 'GenerateReport.toRequest', 'Quickstart frontend request descriptor');
requireText(pages.get('/reference/project-cli/'), 'operation:inspect', 'BlackOps CLI diagnostics command');
requireText(pages.get('/reference/project-cli/'), 'frontend:check', 'BlackOps CLI frontend drift command');
requireText(pages.get('/reference/configuration/'), 'application.jsonl', 'Application JSONL configuration');
requireText(pages.get('/reference/configuration/'), 'resources/js/blackops', 'Frontend output configuration');
requireText(pages.get('/security/'), 'Canonical DataとSafe Diagnostics', 'Diagnostics security boundary');
requireText(pages.get('/security/'), 'Frontend Operation Contractの境界', 'Frontend security boundary');
requireText(pages.get('/troubleshooting/'), 'Operation ID付き500を調べる', 'Diagnostics troubleshooting');
requireText(pages.get('/troubleshooting/'), 'Frontend Generated TreeがMissing／Drift', 'Frontend troubleshooting');
const communityBoard = pages.get('/testing/community-board/');
requireText(communityBoard, 'BlackOps Board Reference Application', 'Community Board title');
requireText(communityBoard, 'alt="BlackOps BoardのCredential-free Landing画面"', 'Community Board screenshot alt');
requireText(communityBoard, '/_astro/blackops-board.', 'Community Board local screenshot artifact');
requireText(communityBoard, 'SvelteKit same-origin UI / BFF', 'Community Board architecture');
requireText(communityBoard, 'Worker未起動', 'Community Board worker troubleshooting');
requireText(communityBoard, 'External Publication／Deployは行っていません', 'Community Board publication boundary');
const boardAssets = (await readdir(path.join(distRoot, '_astro')))
  .filter((file) => /^blackops-board\.[A-Za-z0-9_-]+\.png$/.test(file));
if (boardAssets.length !== 1) {
  throw new Error(`Static site must contain one BlackOps Board screenshot; found ${boardAssets.length}.`);
}
const boardAssetHash = createHash('sha256')
  .update(await readFile(path.join(distRoot, '_astro', boardAssets[0])))
  .digest('hex');
if (boardAssetHash !== 'a7619b25d97b6ac1e4eba42888968d71fd1633102836a105a2d6c1c94501945d') {
  throw new Error('Static BlackOps Board screenshot does not preserve the credential-free source asset.');
}

const documentation = pages.get('/getting-started/installation/');
for (const section of [
  'Overview',
  'Getting Started',
  'Operations',
  'Execution &amp; Workers',
  'Data &amp; Retention',
  'Testing',
  'Deployment',
  'Security',
  'Troubleshooting',
  'Releases',
  'Reference',
]) {
  requireText(documentation, `>${section}<`, `Sidebar section ${section}`);
}
requireText(documentation, '>Tutorial<', 'Sidebar Tutorial label');

const redirects = await readFile(path.join(distRoot, '_redirects'), 'utf8');
const expectedRedirects = [
  '/operations/lifecycle/* /concepts/lifecycle/:splat 301',
  '/reference/security/* /security/:splat 301',
  '/reference/troubleshooting/* /troubleshooting/:splat 301',
  '/reference/current-status/* /releases/current-status/:splat 301',
  '',
].join('\n');
if (redirects !== expectedRedirects) {
  throw new Error('Static artifact redirects do not match the four moved public URLs.');
}

const diagramRoutes = [
  '/concepts/core-concepts/',
  '/concepts/lifecycle/',
  '/execution/http-and-deferred/',
  '/execution/context/',
];
const responsiveContentRoutes = [
  '/getting-started/first-operation/',
  '/reference/core-api/',
  '/reference/attributes/',
  '/troubleshooting/',
  '/security/',
  '/testing/',
  '/testing/community-board/',
  '/deployment/worker-operations/',
  '/operations/validation/',
];
let diagramCount = 0;
for (const route of diagramRoutes) {
  const html = pages.get(route);
  const count = (html.match(/<pre[^>]*class="mermaid"/g) ?? []).length;
  if (count !== 1) {
    throw new Error(`${route} must contain exactly one Mermaid render target; found ${count}.`);
  }
  diagramCount += count;
  requireText(html, 'accTitle:', `${route} diagram accessible title source`);
  requireText(html, 'accDescr:', `${route} diagram accessible description source`);
  if (html.includes('図のテキスト代替')) {
    throw new Error(`${route} must not display a mechanical diagram-alternative heading.`);
  }
}
if (diagramCount !== 4) {
  throw new Error(`Static site must contain four Mermaid render targets; found ${diagramCount}.`);
}
await verifyResponsiveDiagramStyle();

for (const [route, html] of pages) {
  requireText(html, '<html lang="ja"', `${route} Japanese locale`);
  requireText(html, 'Document Channel:', `${route} document channel`);
  requireText(html, '<code>main</code>', `${route} main channel`);
  requireText(html, 'Latest Stable:', `${route} stable label`);
  requireText(html, '<code>1.1.0</code>', `${route} stable version`);
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

async function verifyResponsiveDiagramStyle() {
  const assetRoot = path.join(distRoot, '_astro');
  const stylesheets = (await readdir(assetRoot)).filter((file) => file.endsWith('.css'));
  const responsiveStylesheets = [];

  for (const stylesheet of stylesheets) {
    const css = await readFile(path.join(assetRoot, stylesheet), 'utf8');
    if (
      css.includes('pre.mermaid') &&
      css.includes('min-inline-size:0') &&
      css.includes('max-inline-size:100%') &&
      css.includes('min-inline-size:60rem') &&
      css.includes('min-inline-size:72rem') &&
      css.includes("aria-roledescription=sequence") &&
      css.includes('landing-feature-grid') &&
      css.includes('grid-template-columns:repeat(3,minmax(0,1fr))') &&
      css.includes('prefers-reduced-motion:reduce') &&
      css.includes('pre:not(.mermaid)') &&
      css.includes('overflow-wrap:anywhere') &&
      css.includes('white-space:normal') &&
      css.includes('inline-size:max-content') &&
      !css.includes('overflow-x:clip') &&
      (css.includes('overflow-x:auto') || css.includes('overflow:auto hidden'))
    ) {
      responsiveStylesheets.push(stylesheet);
    }
  }

  if (responsiveStylesheets.length !== 1) {
    throw new Error(`Static site must contain one responsive Mermaid stylesheet; found ${responsiveStylesheets.length}.`);
  }
  for (const route of diagramRoutes) {
    requireText(pages.get(route), `/_astro/${responsiveStylesheets[0]}`, `${route} responsive Mermaid stylesheet`);
  }
  for (const route of responsiveContentRoutes) {
    requireText(pages.get(route), `/_astro/${responsiveStylesheets[0]}`, `${route} responsive content stylesheet`);
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
    const glossaryResult = await search.search('Fencing Token');
    if (glossaryResult.results.length === 0) {
      throw new Error('Pagefind returned no result for Fencing Token.');
    }
    const glossaryRecords = await Promise.all(glossaryResult.results.slice(0, 5).map(({ data }) => data()));
    if (!glossaryRecords.some(({ url }) => url.includes('/reference/glossary/'))) {
      throw new Error('Pagefind did not return the Glossary for Fencing Token.');
    }
    const troubleshootingResult = await search.search('Typed Self-handled Signature Error');
    if (troubleshootingResult.results.length === 0) {
      throw new Error('Pagefind returned no result for Typed Self-handled Signature Error.');
    }
    const troubleshootingRecords = await Promise.all(
      troubleshootingResult.results.slice(0, 5).map(({ data }) => data()),
    );
    if (!troubleshootingRecords.some(({ url }) => url.includes('/troubleshooting/'))) {
      throw new Error('Pagefind did not return Troubleshooting for Typed Self-handled Signature Error.');
    }
    const securityResult = await search.search('Credential Rotation');
    if (securityResult.results.length === 0) {
      throw new Error('Pagefind returned no result for Credential Rotation.');
    }
    const securityRecords = await Promise.all(securityResult.results.slice(0, 5).map(({ data }) => data()));
    if (!securityRecords.some(({ url }) => url.includes('/security/'))) {
      throw new Error('Pagefind did not return Security for Credential Rotation.');
    }
    await search.destroy();
  } finally {
    globalThis.fetch = originalFetch;
  }
}
