import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { distRoot, websiteRoot } from './website-paths.mjs';

const searchIndex = JSON.parse(await readFile(path.join(distRoot, 'blume-search.json'), 'utf8'));
const routes = searchIndex.map(({ route }) => route).sort();
const requiredRoutes = [
  '/',
  '/execution/console-command',
  '/execution/outbox',
  '/auth/authentication',
  '/auth/authorization',
  '/frontend',
  '/getting-started/quickstart',
  '/releases/current-status',
];

if (routes.length < 37) throw new Error(`Search index must contain the public guide set; found ${routes.length} routes.`);
for (const route of requiredRoutes) {
  if (!routes.includes(route)) throw new Error(`Search index is missing required route: ${route}`);
}

const pages = new Map();
for (const route of routes) pages.set(route, await readFile(htmlPath(route), 'utf8'));
const configSource = await readFile(path.join(websiteRoot, 'blume.config.ts'), 'utf8');
const navigationSource = await readFile(path.join(websiteRoot, 'site-navigation.mjs'), 'utf8');
const contentMapSource = await readFile(path.join(websiteRoot, 'content-map.mjs'), 'utf8');
if (!configSource.includes("import { blumeSidebar } from './site-navigation.mjs';") || !configSource.includes('items: blumeSidebar')) {
  throw new Error('Blume sidebar must be generated from site-navigation.mjs.');
}
if (navigationSource.includes('Testing Overview') || navigationSource.includes('DatabaseとTransaction') || navigationSource.includes('Current Status')) {
  throw new Error('Sidebar source contains a retired page title.');
}
if (!navigationSource.includes('root: item.link') || !navigationSource.includes('root: items[0]')) {
  throw new Error('Sidebar source must feed canonical content roots to native Blume navigation.');
}
if (!contentMapSource.includes('editUrlForRoute') || !contentMapSource.includes('/docs/guide/')) {
  throw new Error('Content map must provide tracked docs/guide Edit URLs.');
}
const sidebarHrefLines = [...configSource.matchAll(/href:\s*'([^']+)'/g)].map(([, href]) => href);
for (const href of sidebarHrefLines) {
  if (href === '/releases/current-status/') continue;
  if (href.endsWith('/')) throw new Error(`Sidebar href must be canonical without trailing slash: ${href}`);
}
const cssDirectory = path.join(distRoot, '_astro');
const cssAssets = await Promise.all(
  (await readdir(cssDirectory)).filter((name) => name.endsWith('.css')).map((name) => readFile(path.join(cssDirectory, name), 'utf8')),
);
const styles = cssAssets.join('\n');
const landing = pages.get('/');
const releases = pages.get('/releases/current-status');
const quickstart = pages.get('/getting-started/quickstart');
requireText(releases, 'CHANGELOG%2Emd', 'Release notes CHANGELOG link');
requireText(releases, 'UPGRADE%2Emd', 'Release notes UPGRADE link');
requireText(releases, '9つのMigration', 'Release notes migration boundary');
requireText(releases, 'Framework／Skeleton annotated Tag', 'Release notes live publication evidence');
const quickstartAnchor = 'id="stable-120-authentication-and-deferred-journey"';
const quickstartAnchorCount = (quickstart.match(new RegExp(quickstartAnchor, 'g')) ?? []).length;
if (quickstartAnchorCount !== 1) {
  throw new Error(`Quickstart must contain exactly one generated ${quickstartAnchor} anchor; found ${quickstartAnchorCount}.`);
}
if (quickstart.includes('id="stable-120-quickstart"')) {
  throw new Error('Quickstart contains the retired stable-120-quickstart anchor.');
}
requireText(landing, '<h1 id="landing-title"><span class="landing-brand">BlackOps</span><span class="landing-tagline">The PHP Framework</span></h1>', 'Landing product heading');
requireText(landing, 'href="/getting-started/installation"', 'Landing Installation action');
requireText(landing, 'href="/concepts/why-blackops"', "Landing What's BlackOps action");
requireText(landing, 'href="/getting-started/first-operation"', 'Landing Operation action');
requireText(landing, 'href="/concepts/journal"', 'Landing Journal action');
requireText(landing, 'href="/frontend"', 'Landing Headless action');
requireText(landing, 'return new ReportGenerated(', 'Landing PHP sample constructor');
requireText(landing, '$value->reportName,', 'Landing PHP sample report name argument');
requireText(landing, "'/reports/generated/' . $value->reportName . '.json',", 'Landing PHP sample location argument');
if (landing.includes("return new ReportGenerated($value->reportName")) {
  throw new Error('Landing PHP sample constructor must remain multiline.');
}
requireText(landing, 'composer create-project blackops/skeleton my-app 1.2.0', 'Landing Stable install command');
requireText(
  landing,
  '<article class="landing-feature landing-feature-headless"><div class="landing-feature-visual landing-client-visual" aria-hidden="true"><code>',
  'Landing Headless visual structure',
);
requireText(landing, '<div class="landing-feature-copy"><h3>Headless</h3>', 'Landing Headless copy structure');
for (const claim of ['Operation', 'Journal', 'Headless', '接続クライアント']) requireText(landing, claim, `Landing ${claim} claim`);
if ((landing.match(/class="landing-feature/g) ?? []).length < 3) throw new Error('Landing must contain three feature blocks.');
const landingText = landing.replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/\s+/g, ' ');
for (const copy of [
  '#[Route]で同期API、#[Deferred]で非同期化します。HTTPリクエストもコンソールコマンドもJobも、すべてはOperationで統一されます。',
  '受理・試行・リトライ・拒否・完了をFrameworkが自動でJournalへ記録します。「なぜ失敗したか」をFrameworkが記録します。',
  'BlackOpsはフロントエンドを持ちません。代わりに、JavaScript向けに接続クライアントのコードを自動生成します。フロントエンドはNext.jsでもNuxtでもSvelteKitでもお好きなFrameworkと組み合わせられます。',
]) {
  requireText(landingText, copy, 'Landing exact text content');
}
for (const forbidden of ['BlackOpsの3つの特徴', 'BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。', 'ONE MODEL / TWO PATHS', 'Operation ↔ Execution', 'Inline HTTP or durable Deferred', 'THE BLACKOPS SHAPE', 'Make the work explicit.', 'Nothing stays in the dark.', 'Bring your frontend.']) {
  if (landingText.includes(forbidden)) throw new Error(`Landing contains forbidden copy: ${forbidden}`);
}
await validateLandingLinks(landing);
if (!styles.includes('prefers-reduced-motion')) throw new Error('Landing must ship reduced-motion CSS.');
if (!styles.includes('data-blume-nav-tree] a[aria-current=page]') || !styles.includes('box-shadow:inset 3px 0 0')) {
  throw new Error('Sidebar active state must include a visible accent marker.');
}

for (const [route, html] of pages) {
  requireText(html, 'BlackOps1.xは試験的なバージョンです。Production Readyは2.xを予定しています。', `${route} experimental notice`);
  requireText(html, 'href="/releases/current-status/"', `${route} Releases banner link`);
  if (html.includes('&lt;a href=')) throw new Error(`${route} contains escaped banner markup.`);
  requireText(html, '<html dir="ltr" lang="ja">', `${route} Japanese locale`);
  requireText(html, 'href="#blume-content"', `${route} skip link`);
  const markdownLinks = [...html.matchAll(/href="([^" ]+\.md(?:[?#][^" ]*)?)"/g)].map(([, href]) => href);
  if (markdownLinks.some((href) => !href.startsWith('https://github.com/kubotak-is/blackops/edit/main/docs/guide/'))) {
    throw new Error(`${route} contains a source Markdown link.`);
  }
  const editLinks = [...html.matchAll(/href="(https:\/\/github\.com\/[^\"]+\/edit\/main\/[^\"]+)"/g)].map(([, href]) => href);
  for (const href of editLinks) {
    if (!href.startsWith('https://github.com/kubotak-is/blackops/edit/main/docs/guide/') || /(?:\.generated|dist|\.mdx?\/)/.test(href)) {
      throw new Error(`${route} contains an invalid GitHub edit link: ${href}`);
    }
  }
  const githubAnchor = html.match(/<a\b[^>]*href="https:\/\/github\.com\/kubotak-is\/blackops"[^>]*>/)?.[0];
  if (!githubAnchor || !/aria-label="[^"]+"/.test(githubAnchor) || !/target="_blank"/.test(githubAnchor) || !/rel="noreferrer"/.test(githubAnchor)) {
    throw new Error(`${route} is missing the native GitHub repository header link contract.`);
  }
  if (route !== '/') validateActiveSidebarAnchor(route, html);
}

const navPage = pages.get('/getting-started/installation');
for (const label of [
  'Introduction',
  'What&#39;s BlackOps',
  'Core Concepts',
  'Getting Started',
  'Quickstart and Skeleton',
  'First Operation',
  'Operation',
  'Authoring',
  'Generators',
  'Inline and Deferred',
  'Execution and Workers',
  'Execution Context',
  'ConsoleCommand',
  'Outbox',
  'Retention',
  'Auth',
  'Authentication',
  'Authorization',
  'Frontend',
  'Reference',
]) requireText(navPage, `>${label}<`, `Sidebar label ${label}`);

const redirects = await readFile(path.join(distRoot, '_redirects'), 'utf8');
for (const redirect of [
  '/operations/lifecycle/* /concepts/lifecycle/:splat 301',
  '/reference/security/* /security/:splat 301',
  '/reference/troubleshooting/* /troubleshooting/:splat 301',
  '/reference/current-status/* /releases/current-status/:splat 301',
]) requireText(redirects, redirect, `Redirect ${redirect}`);

const diagramPages = ['/concepts/core-concepts', '/concepts/lifecycle', '/execution/http-and-deferred', '/execution/context'];
let diagramCount = 0;
for (const route of diagramPages) {
  const html = pages.get(route);
  const count = (html.match(/<blume-mermaid(?:\s|>)/g) ?? []).length;
  if (count !== 1) throw new Error(`${route} must contain one Mermaid source target; found ${count}.`);
  if ((html.match(/data-language="mermaid"/g) ?? []).length !== 0) {
    throw new Error(`${route} must not contain a Mermaid syntax-highlighted code block.`);
  }
  diagramCount += count;
  requireText(html, 'accTitle:', `${route} diagram title`);
  requireText(html, 'accDescr:', `${route} diagram description`);
}
if (diagramCount !== 4) throw new Error(`Static site must contain four Mermaid targets; found ${diagramCount}.`);

console.log(`Site navigation, accessibility markup, version notice, and search checks passed for ${routes.length} pages.`);

async function validateLandingLinks(html) {
  const links = [...html.matchAll(/href="([^"]+)"/g)].map(([, href]) => href);
  for (const href of links) {
    if (href.startsWith('#') || /^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(href)) continue;
    const url = new URL(href, 'https://blackops.local');
    const pathname = decodeURIComponent(url.pathname);
    const relative = pathname === '/' ? 'index.html' : path.extname(pathname) !== '' ? pathname.slice(1) : `${pathname.slice(1)}/index.html`;
    const target = path.join(distRoot, relative);
    try {
      await readFile(target);
    } catch {
      throw new Error(`Landing link does not resolve to a static artifact: ${href}`);
    }
  }
}

function validateActiveSidebarAnchor(route, html) {
  const anchors = [...html.matchAll(/<a\b[^>]*>/g)].map(([tag]) => tag);
  const active = anchors
    .filter((tag) => tag.includes('aria-current="page"'))
    .map((tag) => tag.match(/href="([^"]+)"/)?.[1])
    .filter(Boolean)
    .filter((href) => href === route);
  if (active.length !== 1) {
    throw new Error(`${route} must have exactly one canonical Sidebar aria-current="page" anchor; found ${active.length}.`);
  }
}

function htmlPath(route) {
  return route === '/' ? path.join(distRoot, 'index.html') : path.join(distRoot, route, 'index.html');
}

function requireText(content, expected, label) {
  if (!content.includes(expected)) throw new Error(`${label} was not found in the static site.`);
}
