import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import { distRoot, repositoryRoot, websiteRoot } from './website-paths.mjs';
import { contentMap } from '../content-map.mjs';
import { blumeSidebar } from '../site-navigation.mjs';
import { validateArtifactReaderContract } from './reader-contract.mjs';
import { assertProductFramingArtifactContract, P22_005E_TASK_PATH } from './product-framing-contract.mjs';
import { loadDiagramManifest } from './archify-diagrams.mjs';

const searchIndex = JSON.parse(await readFile(path.join(distRoot, 'blume-search.json'), 'utf8'));
const diagramManifest = await loadDiagramManifest();
await validateArtifactReaderContract({ contentMap, artifactDirectory: distRoot });
const releaseSearchRecord = searchIndex.find(({ route }) => route === '/releases/current-status');
if (!releaseSearchRecord || releaseSearchRecord.section !== 'Releases' || JSON.stringify(releaseSearchRecord.breadcrumb) !== JSON.stringify(['Releases'])) {
  throw new Error(`Releases Search record must be section Releases with breadcrumb ['Releases']; found ${JSON.stringify({ section: releaseSearchRecord?.section, breadcrumb: releaseSearchRecord?.breadcrumb })}.`);
}
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
const landingPageSource = await readFile(path.join(websiteRoot, 'pages/index.astro'), 'utf8');
const landingGuideSource = await readFile(path.join(repositoryRoot, 'docs/guide/README.md'), 'utf8');
const retentionRuntimeSource = await readFile(path.join(repositoryRoot, 'src/Internal/Application/ApplicationConsoleKernel.php'), 'utf8');
const spec83Source = await readFile(path.join(repositoryRoot, 'develop/spec/83-blume-documentation-experience.md'), 'utf8');
const taskSource = await readFile(path.join(repositoryRoot, P22_005E_TASK_PATH), 'utf8');
const repositoryPaths = await readRepositoryPathInventory(repositoryRoot);
if (!configSource.includes("import { blumeSidebar } from './site-navigation.mjs';") || !configSource.includes('items: blumeSidebar')) {
  throw new Error('Blume sidebar must be generated from site-navigation.mjs.');
}
const requiredSections = ['Start Here', 'Build', 'Async and Lifecycle', 'Data and Security', 'Operate', 'Reference', 'Releases'];
for (const section of requiredSections) {
  if (!navigationSource.includes(`label: '${section}'`)) throw new Error(`Sidebar source is missing the canonical section: ${section}`);
}
if (navigationSource.includes('Introduction') || navigationSource.includes('Execution and Workers') || navigationSource.includes('Testing Overview') || navigationSource.includes('Current Status')) {
  throw new Error('Sidebar source contains a retired information-architecture label.');
}
if (!navigationSource.includes('root: item.link') || !navigationSource.includes('root: items[0]')) {
  throw new Error('Sidebar source must feed canonical content roots to native Blume navigation.');
}
if (!navigationSource.includes("items: ['releases/current-status']") || navigationSource.includes("{ label: 'Releases', link: 'releases/current-status' }")) {
  throw new Error('Releases source must map to one direct singleton root.');
}
const releasesProjection = blumeSidebar.find(({ label }) => label === 'Releases');
if (!releasesProjection || releasesProjection.root !== 'releases/current-status' || !Array.isArray(releasesProjection.items) || releasesProjection.items.length !== 0 || releasesProjection.display !== 'flat') {
  throw new Error('Releases Blume projection must be one empty-child flat group with one direct root.');
}
if (!contentMapSource.includes("slug: 'releases/current-status'") || !contentMapSource.includes("section: 'Releases'")) {
  throw new Error('Releases Content Map source mapping is missing.');
}
assertCanonicalLandingSections(landingGuideSource, 'Landing source');
assertLandingOperationSource(landingPageSource);
if (
  !landingPageSource.includes('<ExecutionWalkthrough')
  || !landingPageSource.includes('svgSrc="/diagrams/execution-overview.svg"')
  || !landingPageSource.includes('desktopSvgSrc="/diagrams/execution-overview-desktop.svg"')
  || !landingPageSource.includes('desktopDataSrc="/diagrams/execution-overview-desktop.json"')
) {
  throw new Error('Landing source must compose both responsive canonical execution walkthrough layouts.');
}
if (landingPageSource.includes('<main class="landing-shell">') || !landingPageSource.includes('<div class="landing-shell">')) {
  throw new Error('Landing source must use a non-landmark root inside PageLayout.');
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
const landingStyles = cssAssets
  .filter((css) => css.includes('.landing-shell'))
  .map((css) => css.slice(css.indexOf('.landing-shell')))
  .join('\n');
const landing = pages.get('/');
const releases = pages.get('/releases/current-status');
const quickstart = pages.get('/getting-started/quickstart');
requireText(releases, 'CHANGELOG%2Emd', 'Release notes CHANGELOG link');
requireText(releases, 'UPGRADE%2Emd', 'Release notes UPGRADE link');
requireText(releases, '9つのMigration', 'Release notes migration boundary');
requireText(releases, 'Versionを固定したSkeletonをInstallし、ApplicationのHTTP入口とCLIの公開結果を確認できます。', 'Release notes user-observable installation guidance');
const quickstartAnchor = 'id="stable-120-authentication-and-deferred-journey"';
const quickstartAnchorCount = (quickstart.match(new RegExp(quickstartAnchor, 'g')) ?? []).length;
if (quickstartAnchorCount !== 1) {
  throw new Error(`Quickstart must contain exactly one generated ${quickstartAnchor} anchor; found ${quickstartAnchorCount}.`);
}
if (quickstart.includes('id="stable-120-quickstart"')) {
  throw new Error('Quickstart contains the retired stable-120-quickstart anchor.');
}
requireText(landing, '<h1 id="landing-title"><span class="landing-brand" data-landing-neon-title>BlackOps</span> <span class="landing-tagline">The PHP Framework</span></h1>', 'Landing product heading');
for (const [marker, label] of [
  ['data-execution-walkthrough', 'Landing execution walkthrough'],
  ['data-execution-svg-src="/diagrams/execution-overview.svg"', 'Landing execution SVG'],
  ['data-execution-desktop-svg-src="/diagrams/execution-overview-desktop.svg"', 'Landing desktop execution SVG'],
  ['data-execution-desktop-data-src="/diagrams/execution-overview-desktop.json"', 'Landing desktop execution layout'],
  ['Journalイベント', 'Landing Journal heading'],
  ['data-execution-graph-stage', 'Landing execution graph stage'],
]) requireText(landing, marker, label);
const executionLinkCount = (landing.match(/data-execution-link="[a-z0-9-]+"/g) ?? []).length;
if (executionLinkCount !== 7) {
  throw new Error(`Landing must expose seven geometry-derived execution guide links; found ${executionLinkCount}.`);
}
const executionInspectButtonCount = (landing.match(/data-execution-inspect(?:[=>\s])/g) ?? []).length;
if (executionInspectButtonCount !== 7 || /<a\b[^>]*data-execution-region=/u.test(landing)) {
  throw new Error('Landing execution guide links must be seven native inspection buttons without direct navigation overlays.');
}
for (const marker of ['data-execution-inspection-panel', 'data-execution-stage-badge', '詳しく見る', 'data-execution-journal-status']) {
  requireText(landing, marker, `Landing execution inspection contract: ${marker}`);
}
const landingVisible = landing.replace(/<[^>]*>/g, '').replace(/&#123;/g, '{').replace(/&#125;/g, '}').replace(/&#39;/g, "'");
const operationType = "#[OperationType('report.generate')]";
if ((landingVisible.match(/#\[OperationType\('report\.generate'\)\]/g) ?? []).length !== 1) {
  throw new Error('Landing Artifact must contain exactly one report.generate OperationType.');
}
const routeIndex = landingVisible.indexOf("#[Route(method: 'POST', path: '/reports')]");
const operationTypeIndex = landingVisible.indexOf(operationType);
const deferredIndex = landingVisible.indexOf('#[Deferred]');
if (routeIndex < 0 || operationTypeIndex < 0 || deferredIndex < 0 || !(routeIndex < operationTypeIndex && operationTypeIndex < deferredIndex)) {
  throw new Error('Landing Artifact Operation metadata must be ordered Route, OperationType, Deferred.');
}
const whyActionIndex = landing.indexOf('data-landing-action="why"');
const installActionIndex = landing.indexOf('data-landing-action="install"');
if (whyActionIndex < 0 || installActionIndex < 0 || whyActionIndex >= installActionIndex) {
  throw new Error('Landing Artifact hero actions must be ordered What\'s BlackOps, Install.');
}
const visibleMainCount = (landing.match(/<main\b/g) ?? []).length;
const contentMainCount = (landing.match(/<main id="blume-content">/g) ?? []).length;
const skipTargetCount = (landing.match(/href="#blume-content"/g) ?? []).length;
if (visibleMainCount !== 1 || contentMainCount !== 1 || skipTargetCount !== 1 || landing.includes('<main class="landing-shell">')) {
  throw new Error(`Landing Artifact must have one PageLayout main, one matching skip target, and no nested Landing main; found main=${visibleMainCount}, content=${contentMainCount}, skip=${skipTargetCount}.`);
}
for (const [href, label] of [
  ['/getting-started/installation', 'Landing Installation action'],
  ['/getting-started/quickstart', 'Landing Quickstart action'],
  ['/getting-started/first-operation', 'Landing First Operation action'],
  ['/concepts/why-blackops', "Landing What's BlackOps action"],
  ['/concepts/journal', 'Landing Journal action'],
  ['/reference/project-cli', 'Landing Reference action'],
]) requireText(landing, `href="${href}"`, label);
requireText(landing, 'return new ReportGenerated(', 'Landing PHP sample constructor');
requireText(landing, '$value->reportName,', 'Landing PHP sample report name argument');
requireText(landing, "'/reports/generated/' . $value->reportName . '.json',", 'Landing PHP sample location argument');
if (landing.includes("return new ReportGenerated($value->reportName")) {
  throw new Error('Landing PHP sample constructor must remain multiline.');
}
requireText(landing, 'composer create-project blackops/skeleton my-app 1.2.0', 'Landing Stable install command');
const landingText = landing.replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/\s+/g, ' ');
for (const copy of [
  'HTTPとWorkerの処理を一つのOperationとして扱い、受付・再試行・完了までを同じIDで追跡できるPHP Frameworkです。',
  'Stable 1.2.0',
  'Install',
  'Quickstart and Skeleton',
  'First Operation',
  'HTTP',
  'Console',
  'Schedule',
  'Inline',
  'Deferred',
  '同じID',
  'BlackOpsを動かす',
  '環境を用意し、サンプルで動きを確かめてから、自分のOperationを作ります。',
  '環境を用意する',
  'サンプルを動かす',
  'Operationを作る',
  '認証付きHTTPとDeferred Workerを順に確認する',
  'CLIで生成し、HTTPとWorkerで完走する',
]) {
  requireText(landingText, copy, 'Landing exact text content');
}
if (!landing.includes('<h2 id="landing-resources-title">BlackOpsを動かす</h2>')
  || !landing.includes('環境を用意し、サンプルで動きを確かめてから、自分のOperationを作ります。')) {
  throw new Error('Landing must expose the ordered tutorial journey with a meaningful heading and lead.');
}
for (const marker of ['landing-authoring', 'landing-contract-list', 'landing-purpose-nav', '実際のPHPコードから始める', '必要な場所から読む']) {
  if (landing.includes(marker)) throw new Error(`Landing must not retain the retired tutorial section marker: ${marker}`);
}
if (landing.includes('landing-lifecycle-panel') || landing.includes('landing-lifecycle-rail') || landingText.includes('Operation Lifecycle')) {
  throw new Error('Landing must not contain the retired hand-built Lifecycle rail.');
}
for (const forbidden of ['BlackOpsの3つの特徴', 'BlackOpsは、PHP 8.5向けのHeadless Operation Frameworkです。同期HTTP実行とPostgreSQLを使ったDeferred実行を同じOperation Modelで扱い、Lifecycle Journal、Retry、Outcome、Retention、BlackOps CLIを提供します。', 'ONE MODEL / TWO PATHS', 'Operation ↔ Execution', 'Inline HTTP or durable Deferred', 'THE BLACKOPS SHAPE', 'Make the work explicit.', 'Nothing stays in the dark.', 'Bring your frontend.', 'landing-feature', 'landing-hero-glow', 'landing-panel-dot', 'linear-gradient', 'radial-gradient', 'overflow-x: hidden', 'overflow-x:hidden', '–']) {
  if (landingText.includes(forbidden) || landing.includes(forbidden)) throw new Error(`Landing contains forbidden copy or decoration: ${forbidden}`);
}
const journey = landing.match(/<ol class="landing-journey">([\s\S]*?)<\/ol>/)?.[1] ?? '';
let journeyOffset = -1;
for (const [href, label, description] of [
  ['/getting-started/installation', '環境を用意する', 'PHPとComposerでProjectを作り、Local環境を準備する'],
  ['/getting-started/quickstart', 'サンプルを動かす', '認証付きHTTPとDeferred Workerを順に確認する'],
  ['/getting-started/first-operation', 'Operationを作る', 'CLIで生成し、HTTPとWorkerで完走する'],
]) {
  if ((journey.match(new RegExp(`href="${href}"`, 'g')) ?? []).length !== 1) throw new Error(`Landing start journey must contain exactly one ${href} action.`);
  const position = journey.indexOf(`href="${href}"`);
  if (position <= journeyOffset) throw new Error(`Landing start journey must keep its sequential order: ${href}`);
  journeyOffset = position;
  requireText(journey, `>${label}</strong>`, `Landing journey label ${label}`);
  requireText(journey, `<small>${description}</small>`, `Landing journey description ${label}`);
}
await validateLandingLinks(landing);
const landingMarkdown = await readFile(path.join(distRoot, 'index.md'), 'utf8');
assertCanonicalLandingSections(landingMarkdown, 'Generated Landing Markdown');
const llmsFull = await readFile(path.join(distRoot, 'llms-full.txt'), 'utf8');
const llmsShort = await readFile(path.join(distRoot, 'llms.txt'), 'utf8');
const rawArtifacts = await readTextArtifacts(distRoot);
const searchText = (route) => JSON.stringify(searchIndex.find((record) => record.route === route) ?? {});
const firstRaw = (prefix) => [...rawArtifacts.entries()].find(([name]) => name.startsWith(prefix + '.'))?.[1] ?? '';
const productSurfaces = new Map([
  ['landing-html', landing],
  ['landing-raw', rawArtifacts.get('index.md') ?? ''],
  ['landing-search', searchText('/')],
  ['landing-llm', llmsFull],
  ['landing-llm-short', llmsShort],
  ['why-search', searchText('/concepts/why-blackops')],
  ['why-html', pages.get('/concepts/why-blackops') ?? ''],
  ['why-raw', firstRaw('concepts/why-blackops')],
  ['why-llm', llmsFull],
  ['cli-search', searchText('/reference/project-cli')],
  ['cli-html', pages.get('/reference/project-cli') ?? ''],
  ['cli-raw', firstRaw('reference/project-cli')],
  ['cli-llm', llmsFull],
  ['journal-search', searchText('/concepts/journal')],
  ['journal-html', pages.get('/concepts/journal') ?? ''],
  ['journal-raw', firstRaw('concepts/journal')],
  ['journal-llm', llmsFull],
  ['observability-search', searchText('/reference/observability')],
  ['observability-html', pages.get('/reference/observability') ?? ''],
  ['observability-raw', firstRaw('reference/observability')],
  ['observability-llm', llmsFull],
  ['security-search', searchText('/security')],
  ['security-html', pages.get('/security') ?? ''],
  ['security-raw', firstRaw('security')],
  ['security-llm', llmsFull],
  ['glossary-search', searchText('/reference/glossary')],
  ['glossary-html', pages.get('/reference/glossary') ?? ''],
  ['glossary-raw', firstRaw('reference/glossary')],
  ['glossary-llm', llmsFull],
  ['quickstart-search', searchText('/getting-started/quickstart')],
  ['quickstart-html', pages.get('/getting-started/quickstart') ?? ''],
  ['quickstart-raw', firstRaw('getting-started/quickstart')],
  ['quickstart-llm', llmsFull],
  ['search-all', JSON.stringify(searchIndex)],
  ['llm-full-all', llmsFull],
  ['llm-short-all', llmsShort],
]);
for (const [name, html] of pages) productSurfaces.set('page:' + name, html);
for (const [name, content] of rawArtifacts) productSurfaces.set('raw:' + name, content);
assertProductFramingArtifactContract({ surfaces: productSurfaces, css: landingStyles, retentionRuntimeSource, spec83Source, taskSource, repositoryPaths });
const llmsLandingStartMarker = '# BlackOps - The PHP Framework\nSource: https://blackops-php.pages.dev/';
const llmsLandingStart = llmsFull.indexOf(llmsLandingStartMarker);
const llmsLandingEnd = llmsLandingStart === -1 ? -1 : llmsFull.indexOf('\n---\n\n# ', llmsLandingStart);
if (llmsLandingStart === -1 || llmsLandingEnd === -1) throw new Error('LLM artifact is missing the Landing segment boundary.');
assertCanonicalLandingSections(llmsFull.slice(llmsLandingStart, llmsLandingEnd), 'Landing llms-full segment');
if (!styles.includes('prefers-reduced-motion')) throw new Error('Landing must ship reduced-motion CSS.');
if (!landingStyles.includes('overflow-x:auto') && !landingStyles.includes('overflow-x: auto')) throw new Error('Landing code samples must contain horizontal scrolling locally.');
if (landingStyles.includes('overflow-x:hidden') || landingStyles.includes('linear-gradient')) throw new Error('Landing must not hide overflow or use linear gradients.');
if (landingStyles.includes('radial-gradient')
  && !/\[data-theme=dark\]\s*\.landing-shell:before\{[^}]*radial-gradient/u.test(landingStyles)
  && !/\.landing-backdrop__field\{[^}]*radial-gradient/u.test(landingStyles)) {
  throw new Error('Landing ambient gradients must remain scoped to the landing shell or decorative backdrop field.');
}
for (const [value, theme] of [['#4f6f16', 'light'], ['#c9ed68', 'dark']]) {
  if (!new RegExp(`--bo-focus\\s*:\\s*${value}(?:[;}])`, 'iu').test(styles)) {
    throw new Error(`Emitted CSS must define the accessible ${theme} focus token.`);
  }
}
if (!/:where\(a,button,summary,input,select,textarea\):focus-visible\{[^}]*outline:3px solid var\(--bo-focus\)/u.test(styles)) {
  throw new Error('Emitted CSS must apply the accessible focus token to keyboard controls.');
}
if (!/\[data-blume-nav-drawer\]\s+\[data-blume-nav-tree\]\s+a\[aria-current=page\][^{]*\{[^}]*box-shadow:inset 3px 0 0 var\(--bo-accent\)/u.test(styles)) {
  throw new Error('Sidebar active state must include a visible accent marker alongside the active navigation rule.');
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

const releasesSidebar = releases.match(/<aside\b[\s\S]*?<\/aside>/)?.[0] ?? '';
const releaseTargetAnchors = releasesSidebar.match(/<a\b[^>]*href="\/releases\/current-status"[^>]*>/g) ?? [];
const releaseActiveAnchors = releasesSidebar.match(/<a\b[^>]*aria-current="page"[^>]*href="\/releases\/current-status"[^>]*>/g) ?? [];
const releaseSectionDetails = releasesSidebar.match(/<details\b/g) ?? [];
if (releaseTargetAnchors.length !== 1 || releaseActiveAnchors.length !== 1 || releaseSectionDetails.length !== requiredSections.length - 1) {
  throw new Error(`Releases Artifact must be one direct operation target with unique aria-current; found targets=${releaseTargetAnchors.length}, active=${releaseActiveAnchors.length}, collapsibleSections=${releaseSectionDetails.length}.`);
}

const navPage = pages.get('/getting-started/installation');
for (const label of [
  'Start Here',
  'What&#39;s BlackOps',
  'Install',
  'Quickstart and Skeleton',
  'First Operation',
  'Build',
  'Authoring',
  'Generators',
  'Value and Validation',
  'Inline and Deferred',
  'ConsoleCommand',
  'Scheduled Operation',
  'Async and Lifecycle',
  'Execution Context',
  'Outcome',
  'Outbox',
  'Journal',
  'Data and Security',
  'Transaction',
  'Security',
  'Operate',
  'Configuration',
  'Deployment',
  'Observability',
  'Testing',
  'Troubleshooting',
  'Reference',
  'BlackOps CLI',
  'Application Bootstrap',
  'Releases',
  'Authentication',
  'Authorization',
  'Frontend',
]) requireText(navPage, `>${label}<`, `Sidebar label ${label}`);

const redirects = await readFile(path.join(distRoot, '_redirects'), 'utf8');
for (const redirect of [
  '/operations/lifecycle/* /concepts/lifecycle/:splat 301',
  '/reference/security/* /security/:splat 301',
  '/reference/troubleshooting/* /troubleshooting/:splat 301',
  '/reference/current-status/* /releases/current-status/:splat 301',
]) requireText(redirects, redirect, `Redirect ${redirect}`);

for (const [route, html] of pages) {
  const mermaidTargetCount = (html.match(/<blume-mermaid(?:\s|>)/g) ?? []).length;
  const mermaidCodeBlockCount = (html.match(/data-language="mermaid"/g) ?? []).length;
  if (mermaidTargetCount !== 0 || mermaidCodeBlockCount !== 0) {
    throw new Error(`${route} must not contain Mermaid targets or syntax-highlighted code blocks; found targets=${mermaidTargetCount}, codeBlocks=${mermaidCodeBlockCount}.`);
  }
}

const diagramEntriesByRoute = new Map();
for (const entry of diagramManifest.diagrams) {
  const route = entry.ownerRoute === '/' ? '/' : entry.ownerRoute.replace(/\/+$/u, '');
  if (route === '/') continue;
  const entries = diagramEntriesByRoute.get(route) ?? [];
  entries.push(entry);
  diagramEntriesByRoute.set(route, entries);
}
let renderedFigureCount = 0;
for (const [route, entries] of diagramEntriesByRoute) {
  const html = pages.get(route);
  if (html === undefined) throw new Error(`${route} is missing from the generated site diagram inventory.`);
  const figures = html.match(/<[^>]*\bclass="[^"]*\barchify-figure\b[^"]*"[^>]*>/g) ?? [];
  if (figures.length !== entries.length) {
    throw new Error(`${route} must render ${entries.length} registered Archify figures; found ${figures.length}.`);
  }
  const images = [...html.matchAll(/<img\b[^>]*>/gu)].map(([tag]) => tag);
  if (images.length !== entries.length) {
    throw new Error(`${route} must render one image for each registered Archify figure; expected ${entries.length}, found ${images.length}.`);
  }
  for (const entry of entries) {
    const stem = path.posix.basename(entry.pngPath, '.png').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const imagePattern = new RegExp(`<img\\b[^>]*\\bsrc="[^"]*/${stem}\\.[^"]+\\.(?:avif|png|webp)(?:\\?[^" ]*)?"`, 'u');
    if (!imagePattern.test(html)) throw new Error(`${route} is missing the rendered Archify image for ${entry.id}.`);
  }
  for (const tag of images) {
    if (!/\balt="[^"\n]+\S[^"\n]*"/u.test(tag) || !/\bwidth="\d+"/u.test(tag) || !/\bheight="\d+"/u.test(tag)) {
      throw new Error(`${route} Archify figures must render descriptive, dimensioned images.`);
    }
  }
  renderedFigureCount += figures.length;
}
const expectedFigureCount = [...diagramEntriesByRoute.values()].reduce((count, entries) => count + entries.length, 0);
if (renderedFigureCount !== expectedFigureCount) {
  throw new Error(`Static site must render exactly ${expectedFigureCount} registered Archify figures; found ${renderedFigureCount}.`);
}
if ((landing.match(/data-execution-svg-src="\/diagrams\/execution-overview\.svg"/g) ?? []).length !== 1) {
  throw new Error('Landing must reference the registered execution-overview canonical SVG exactly once.');
}

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

async function readTextArtifacts(root, prefix = '') {
  const result = new Map();
  for (const entry of await readdir(path.join(root, prefix), { withFileTypes: true })) {
    const relative = prefix === '' ? entry.name : prefix + '/' + entry.name;
    if (entry.isDirectory()) {
      for (const [name, content] of await readTextArtifacts(root, relative)) result.set(name, content);
    } else if (entry.isFile() && /\.(?:md|mdx|txt)$/u.test(entry.name)) {
      result.set(relative, await readFile(path.join(root, relative), 'utf8'));
    }
  }
  return result;
}

async function readRepositoryPathInventory(root) {
  const paths = [];
  const ignoredDirectories = new Set(['.git', 'node_modules', 'vendor']);

  async function visit(directory, prefix = '') {
    for (const entry of await readdir(directory, { withFileTypes: true })) {
      const relative = prefix === '' ? entry.name : `${prefix}/${entry.name}`;
      if (entry.isDirectory()) {
        if (ignoredDirectories.has(entry.name)) continue;
        await visit(path.join(directory, entry.name), relative);
      } else if (entry.isFile()) {
        paths.push(relative);
      }
    }
  }

  await visit(root);
  return paths.sort((left, right) => left.localeCompare(right, 'en'));
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

function landingHeadings(markdown) {
  const headings = [];
  let fence = null;
  for (const line of markdown.split(/\r?\n/)) {
    const fenceMatch = line.match(/^\s*(```+|~~~+)/);
    if (fenceMatch !== null) {
      const marker = fenceMatch[1][0];
      fence = fence === null ? marker : fence === marker ? null : fence;
      continue;
    }
    if (fence === null) {
      const heading = line.match(/^##\s+(.+)$/);
      if (heading !== null) headings.push(heading[1].trim());
    }
  }
  return headings;
}

function assertCanonicalLandingSections(markdown, label) {
  const headings = landingHeadings(markdown);
  if (JSON.stringify(headings) !== JSON.stringify(requiredSections)) {
    throw new Error(`${label} must contain exactly the canonical seven level-two sections in order; found ${headings.join(', ')}.`);
  }
  if (markdown.includes('## Reference and Releases')) {
    throw new Error(`${label} must not recombine Reference and Releases.`);
  }
}

function assertLandingOperationSource(source) {
  const visible = source.replace(/<[^>]*>/g, '');
  const operationType = "#[OperationType('report.generate')]";
  if ((visible.match(/#\[OperationType\('report\.generate'\)\]/g) ?? []).length !== 1) {
    throw new Error('Landing Source must contain exactly one report.generate OperationType.');
  }
  const routeIndex = visible.indexOf("#[Route(method: 'POST', path: '/reports')]");
  const operationTypeIndex = visible.indexOf(operationType);
  const deferredIndex = visible.indexOf('#[Deferred]');
  if (routeIndex < 0 || operationTypeIndex < 0 || deferredIndex < 0 || !(routeIndex < operationTypeIndex && operationTypeIndex < deferredIndex)) {
    throw new Error('Landing Source Operation metadata must be ordered Route, OperationType, Deferred.');
  }
}
