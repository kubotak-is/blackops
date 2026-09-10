import { createHash } from 'node:crypto';
import { execFile } from 'node:child_process';
import { lstat, readFile, readdir, realpath } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import { distRoot, repositoryRoot as defaultRepositoryRoot } from './website-paths.mjs';

const execFileAsync = promisify(execFile);

export const ARCHIFY_REPOSITORY = 'https://github.com/tt-a1i/archify';
export const ARCHIFY_REVISION = '2ead014aa8ec91f104cd052f1a6ca82de5e26c31';
export const ARCHIFY_VERSION = '2.17.0-dev.1';
export const DIAGRAM_MANIFEST_SCHEMA_VERSION = 1;
export const OFFLINE_TEMPLATE_PATCH_PATH = 'docs/website/diagrams/offline-fonts.patch';
export const OFFLINE_TEMPLATE_PATCH_SHA256 = '69dde0626b865a2db2ffddfb296da6fb2c6c058b473d7d689b0e69705bbebee3';
export const diagramManifestPath = path.join(defaultRepositoryRoot, 'docs/website/diagrams/manifest.json');

const REMOTE_FONT_PROVIDER_PATTERN = /fonts\.googleapis\.com|fonts\.gstatic\.com/iu;
const REMOTE_FONT_FACE_PATTERN = /@font-face\s*\{[^}]*?src\s*:\s*url\(\s*["']?(?:https?:|\/\/)/iu;
const LOCAL_NOTO_FONT_PATH = 'docs/website/public/fonts/NotoSansJP.woff2';
const LOCAL_NOTO_LICENSE_PATH = 'docs/website/public/fonts/NotoSansJP-OFL.txt';
const NOTO_FONT_FAMILY_PATTERN = /font-family\s*:\s*["']?Noto Sans JP(?:["']|\s|,)/iu;
const NOTO_FONT_DATA_PATTERN = /data:font\/woff2;base64,([A-Za-z0-9+/]+={0,2})/gu;

const publicDiagramDirectory = 'docs/website/public/diagrams';
const guideDiagramDirectory = 'docs/guide/assets/diagrams';

function diagramContract(id, ownerSource, ownerRoute, options = {}) {
  return {
    type: 'architecture',
    ownerSource,
    ownerRoute,
    sourcePath: `${publicDiagramDirectory}/${id}.json`,
    htmlPath: `${publicDiagramDirectory}/${id}.html`,
    pngPath: `${guideDiagramDirectory}/${id}.png`,
    ...(options.svg === true ? { svgPath: `${publicDiagramDirectory}/${id}.svg` } : {}),
  };
}

const diagramContracts = new Map([
  ['runtime', diagramContract('runtime', 'docs/guide/core-concepts.md', '/concepts/core-concepts/')],
  ['execution-overview', diagramContract('execution-overview', 'docs/guide/README.md', '/', { svg: true })],
  ['execution-inline', diagramContract('execution-inline', 'docs/guide/execution.md', '/execution/http-and-deferred/')],
  ['execution-acceptance', diagramContract('execution-acceptance', 'docs/guide/execution.md', '/execution/http-and-deferred/')],
  ['execution-worker', diagramContract('execution-worker', 'docs/guide/execution.md', '/execution/http-and-deferred/')],
  ['execution-context', diagramContract('execution-context', 'docs/guide/execution-context.md', '/execution/context/')],
  ['lifecycle-success', diagramContract('lifecycle-success', 'docs/guide/operation-lifecycle.md', '/concepts/lifecycle/')],
  ['lifecycle-rejection', diagramContract('lifecycle-rejection', 'docs/guide/operation-lifecycle.md', '/concepts/lifecycle/')],
  ['lifecycle-failure', diagramContract('lifecycle-failure', 'docs/guide/operation-lifecycle.md', '/concepts/lifecycle/')],
  ['outbox', diagramContract('outbox', 'docs/guide/outbox.md', '/execution/outbox/')],
]);

// Responsive exports remain variants of the same logical diagram. Keep the
// allow-list deliberately narrow until another reader surface has an explicit
// need for a second authored layout.
const responsiveVariantContracts = new Map([
  ['execution-overview', new Map([
    ['desktop', {
      sourcePath: `${publicDiagramDirectory}/execution-overview-desktop.json`,
      htmlPath: `${publicDiagramDirectory}/execution-overview-desktop.html`,
      svgPath: `${publicDiagramDirectory}/execution-overview-desktop.svg`,
    }],
  ])],
]);

const BASE_ENTRY_KEYS = new Set([
  'id',
  'type',
  'ownerSource',
  'ownerRoute',
  'sourcePath',
  'source',
  'htmlPath',
  'html',
  'pngPath',
  'png',
  'receipts',
  'svgPath',
  'svg',
]);

export function expectedDiagramContract(id) {
  const contract = diagramContracts.get(id);
  return contract === undefined ? undefined : { id, ...contract };
}

export function diagramContractsList() {
  return [...diagramContracts.entries()].map(([id, contract]) => ({ id, ...contract }));
}

function responsiveVariantsFor(entry) {
  const contracts = responsiveVariantContracts.get(entry.id) ?? new Map();
  return [...contracts.entries()]
    .filter(([name]) => entry[name] !== undefined)
    .map(([name, contract]) => ({ contract, name, value: entry[name] }));
}

function expectedResponsiveVariantsFor(entry) {
  return responsiveVariantContracts.get(entry.id) ?? new Map();
}

function publicDiagramPath(relativePath) {
  return `/diagrams/${path.posix.relative(publicDiagramDirectory, relativePath)}`;
}

function diagramLinkRecords(entry) {
  const records = [
    { kind: 'source', link: publicDiagramPath(entry.sourcePath), path: entry.sourcePath },
    { kind: 'html', link: publicDiagramPath(entry.htmlPath), path: entry.htmlPath },
  ];
  if (entry.svg !== undefined) records.push({ kind: 'svg', link: publicDiagramPath(entry.svg.path), path: entry.svg.path });
  for (const { name, value } of responsiveVariantsFor(entry)) {
    records.push({ kind: 'source', link: publicDiagramPath(value.sourcePath), name, path: value.sourcePath });
    records.push({ kind: 'html', link: publicDiagramPath(value.htmlPath), name, path: value.htmlPath });
    records.push({ kind: 'svg', link: publicDiagramPath(value.svgPath), name, path: value.svgPath });
  }
  return records;
}

export async function loadDiagramManifest(manifestPath = diagramManifestPath) {
  let text;
  try {
    text = await readFile(manifestPath, 'utf8');
  } catch (error) {
    throw new Error(`Diagram manifest is unreadable: ${manifestPath}`, { cause: error });
  }

  let manifest;
  try {
    manifest = JSON.parse(text);
  } catch (error) {
    throw new Error(`Diagram manifest is not valid JSON: ${manifestPath}`, { cause: error });
  }
  validateDiagramManifest(manifest, { manifestPath });
  return manifest;
}

export function validateDiagramManifest(manifest, { manifestPath = 'diagram manifest' } = {}) {
  if (manifest === null || typeof manifest !== 'object' || Array.isArray(manifest)) {
    throw new Error(`Diagram manifest must be an object: ${manifestPath}`);
  }
  if (manifest.schemaVersion !== DIAGRAM_MANIFEST_SCHEMA_VERSION) {
    throw new Error(`Diagram manifest schemaVersion must be ${DIAGRAM_MANIFEST_SCHEMA_VERSION}: ${manifestPath}`);
  }
  const upstream = manifest.upstream;
  if (upstream === null || typeof upstream !== 'object' || Array.isArray(upstream)
    || upstream.repository !== ARCHIFY_REPOSITORY
    || upstream.revision !== ARCHIFY_REVISION
    || upstream.version !== ARCHIFY_VERSION) {
    throw new Error(`Diagram manifest must pin Archify ${ARCHIFY_VERSION} at ${ARCHIFY_REVISION}: ${manifestPath}`);
  }
  if (upstream.updateChecks !== 'disabled') {
    throw new Error(`Diagram manifest must disable Archify update checks: ${manifestPath}`);
  }
  validateTemplatePatch(upstream.templatePatch, manifestPath);
  if (!Array.isArray(manifest.diagrams) || manifest.diagrams.length !== diagramContracts.size) {
    throw new Error(`Diagram manifest must contain exactly ${diagramContracts.size} diagrams: ${manifestPath}`);
  }

  const ids = new Set();
  const paths = new Set();
  for (const entry of manifest.diagrams) {
    if (entry === null || typeof entry !== 'object' || Array.isArray(entry) || typeof entry.id !== 'string') {
      throw new Error(`Diagram manifest contains a malformed entry: ${manifestPath}`);
    }
    const contract = diagramContracts.get(entry.id);
    if (contract === undefined || ids.has(entry.id)) {
      throw new Error(`Diagram manifest contains an unknown or duplicate diagram: ${entry.id}`);
    }
    ids.add(entry.id);
    const allowedVariants = expectedResponsiveVariantsFor(entry);
    for (const key of Object.keys(entry)) {
      if (!BASE_ENTRY_KEYS.has(key) && !allowedVariants.has(key)) {
        throw new Error(`Diagram manifest ${entry.id} has an unexpected ${key}.`);
      }
    }
    for (const [key, expected] of Object.entries(contract)) {
      if (key === 'svgPath') continue;
      if (entry[key] !== expected) {
        throw new Error(`Diagram manifest ${entry.id} has an unexpected ${key}: ${entry[key] ?? '<missing>'}`);
      }
    }
    validateHashRecord(entry, 'source', entry.sourcePath);
    validateHashRecord(entry, 'html', entry.htmlPath);
    validateHashRecord(entry, 'png', entry.pngPath);
    if (contract.svgPath === undefined) {
      if (Object.hasOwn(entry, 'svg')) {
        throw new Error(`Diagram manifest ${entry.id} has an unexpected svg record.`);
      }
    } else {
      validateHashRecord(entry, 'svg', contract.svgPath);
    }
    const filePaths = [entry.sourcePath, entry.htmlPath, entry.pngPath];
    if (contract.svgPath !== undefined) filePaths.push(entry.svg.path);
    for (const { name, contract: variantContract, value: variant } of responsiveVariantsFor(entry)) {
      validateResponsiveVariant(entry, name, variant, variantContract, manifestPath);
      filePaths.push(variant.sourcePath, variant.htmlPath, variant.svgPath);
    }
    for (const filePath of filePaths) {
      if (paths.has(filePath)) throw new Error(`Diagram manifest contains a duplicate path: ${filePath}`);
      paths.add(filePath);
    }
    validateReceipts(entry.receipts, entry.id, contract.svgPath !== undefined);
  }
  for (const id of diagramContracts.keys()) {
    if (!ids.has(id)) throw new Error(`Diagram manifest is missing the ${id} diagram.`);
  }
  if (manifest.regeneration === null || typeof manifest.regeneration !== 'object' || Array.isArray(manifest.regeneration)
    || typeof manifest.regeneration.validate !== 'string'
    || typeof manifest.regeneration.deliver !== 'string'
    || typeof manifest.regeneration.exportPng !== 'string') {
    throw new Error(`Diagram manifest is missing its pinned regeneration commands: ${manifestPath}`);
  }
  return manifest;
}

function validateHashRecord(entry, key, expectedPath) {
  const record = entry[key];
  if (record === null || typeof record !== 'object' || Array.isArray(record) || record.path !== expectedPath) {
    throw new Error(`Diagram manifest ${entry.id} has an invalid ${key} record.`);
  }
  if (record.sha256 !== null && (typeof record.sha256 !== 'string' || !/^[0-9a-f]{64}$/u.test(record.sha256))) {
    throw new Error(`Diagram manifest ${entry.id} has an invalid ${key} sha256.`);
  }
}

function validateResponsiveVariant(entry, name, variant, contract, manifestPath) {
  if (variant === null || typeof variant !== 'object' || Array.isArray(variant)) {
    throw new Error(`Diagram manifest ${entry.id}.${name} is malformed: ${manifestPath}`);
  }
  for (const [key, expected] of Object.entries(contract)) {
    if (variant[key] !== expected) {
      throw new Error(`Diagram manifest ${entry.id}.${name} has an unexpected ${key}: ${variant[key] ?? '<missing>'}`);
    }
  }
  const variantEntry = { id: `${entry.id}.${name}`, ...variant };
  validateHashRecord(variantEntry, 'source', variant.sourcePath);
  validateHashRecord(variantEntry, 'html', variant.htmlPath);
  validateHashRecord(variantEntry, 'svg', variant.svgPath);
  validateReceipts(variant.receipts, `${entry.id}.${name}`, true);
}

function validateTemplatePatch(templatePatch, manifestPath) {
  if (templatePatch === null || typeof templatePatch !== 'object' || Array.isArray(templatePatch)
    || templatePatch.path !== OFFLINE_TEMPLATE_PATCH_PATH) {
    throw new Error(`Diagram manifest must record the offline Archify template patch: ${manifestPath}`);
  }
  for (const key of ['sha256', 'originalTemplateSha256', 'patchedTemplateSha256']) {
    const value = templatePatch[key];
    if (value !== null && (typeof value !== 'string' || !/^[0-9a-f]{64}$/u.test(value))) {
      throw new Error(`Diagram manifest has an invalid offline template patch ${key}: ${manifestPath}`);
    }
  }
}

function validateReceipts(receipts, id, supportsSvgExport) {
  if (receipts === null || typeof receipts !== 'object' || Array.isArray(receipts)) {
    throw new Error(`Diagram manifest ${id} must include validate/deliver receipts.`);
  }
  for (const key of ['validate', 'deliver', 'visualCheck', 'svgExport']) {
    if (receipts[key] !== undefined && receipts[key] !== null
      && (typeof receipts[key] !== 'object' || Array.isArray(receipts[key]))) {
      throw new Error(`Diagram manifest ${id} has an invalid ${key} receipt.`);
    }
  }
  if (!supportsSvgExport && receipts.svgExport !== undefined && receipts.svgExport !== null) {
    throw new Error(`Diagram manifest ${id} has an unexpected svgExport receipt.`);
  }
}

export function publicDiagramLinks(manifest) {
  validateDiagramManifest(manifest);
  return new Set(manifest.diagrams.flatMap((entry) => diagramLinkRecords(entry).map(({ link }) => link)));
}

export function registeredViewerRelativePaths(manifest) {
  validateDiagramManifest(manifest);
  return new Set(manifest.diagrams.flatMap((entry) => diagramLinkRecords(entry)
    .filter(({ kind }) => kind === 'html')
    .map(({ path: filePath }) => path.posix.relative('docs/website/public', filePath))));
}

export function registeredDiagramForLink(manifest, target) {
  validateDiagramManifest(manifest);
  const normalized = normalizePublicDiagramLink(target);
  return manifest.diagrams.find((entry) => diagramLinkRecords(entry).some(({ link }) => link === normalized)) ?? null;
}

export function normalizePublicDiagramLink(target) {
  if (typeof target !== 'string' || !/^\/diagrams\/[a-z0-9-]+\.(?:html|json|svg)$/u.test(target)) {
    throw new Error(`Public diagram link must be a registered /diagrams/<name>.html, .json, or .svg path: ${target}`);
  }
  return target;
}

export async function assertRegisteredDiagramLink(target, {
  manifest = null,
  manifestPath = diagramManifestPath,
  repositoryRoot = defaultRepositoryRoot,
} = {}) {
  const currentManifest = manifest ?? await loadDiagramManifest(manifestPath);
  const normalized = normalizePublicDiagramLink(target);
  const entry = currentManifest.diagrams.find((candidate) => diagramLinkRecords(candidate)
    .some(({ link }) => link === normalized));
  if (entry === undefined) throw new Error(`Public diagram link is not registered: ${target}`);
  const relative = diagramLinkRecords(entry).find(({ link }) => link === normalized)?.path;
  if (relative === undefined) throw new Error(`Public diagram link is not registered: ${target}`);
  await assertRegularTrackedPath(relative, repositoryRoot, 'Public diagram link');
  return entry;
}

export async function assertDiagramArtifacts({
  manifestPath = diagramManifestPath,
  repositoryRoot = defaultRepositoryRoot,
  artifactDirectory = null,
  requireReceipts = true,
} = {}) {
  const manifest = await loadDiagramManifest(manifestPath);
  const repository = path.resolve(repositoryRoot);
  const sourceHashes = new Map();
  const artifactHashes = new Map();
  const svgHashes = new Map();
  const localFontBytes = await readTrackedFile(LOCAL_NOTO_FONT_PATH, repository, 'Local Noto Sans JP font');
  const localLicenseBytes = await readTrackedFile(LOCAL_NOTO_LICENSE_PATH, repository, 'Local Noto Sans JP license');
  if (localFontBytes.length === 0) throw new Error('Local Noto Sans JP font is empty.');
  if (!/SIL OPEN FONT LICENSE/iu.test(localLicenseBytes.toString('utf8'))) {
    throw new Error('Local Noto Sans JP license must contain the SIL Open Font License.');
  }
  const templatePatch = manifest.upstream.templatePatch;
  const patchBytes = await readTrackedFile(templatePatch.path, repository, 'Offline template patch');
  const patchHash = sha256(patchBytes);
  if (patchHash !== OFFLINE_TEMPLATE_PATCH_SHA256) {
    throw new Error(`Offline template patch bytes do not match the reviewed patch: found ${patchHash}.`);
  }
  assertHashRecord(templatePatch, 'sha256', patchHash, 'offline template patch');
  if (requireReceipts) {
    for (const key of ['originalTemplateSha256', 'patchedTemplateSha256']) {
      if (templatePatch[key] === null) {
        throw new Error(`Diagram manifest is missing the offline template patch ${key}.`);
      }
    }
  }

  for (const entry of manifest.diagrams) {
    const ownerBytes = await readTrackedFile(entry.ownerSource, repository, 'Diagram owner guide');
    assertOwnerGuideCoverage(entry, ownerBytes.toString('utf8'));
    const sourceBytes = await readTrackedFile(entry.sourcePath, repository, 'Diagram source');
    const htmlBytes = await readTrackedFile(entry.htmlPath, repository, 'Diagram HTML');
    const pngBytes = await readTrackedFile(entry.pngPath, repository, 'Diagram PNG');
    const svgBytes = entry.svg === undefined
      ? null
      : await readTrackedFile(entry.svg.path, repository, 'Diagram SVG');
    const sourceHash = sha256(sourceBytes);
    const htmlHash = sha256(htmlBytes);
    const pngHash = sha256(pngBytes);
    const svgHash = svgBytes === null ? null : sha256(svgBytes);
    sourceHashes.set(entry.id, sourceHash);
    artifactHashes.set(entry.id, htmlHash);
    if (svgHash !== null) svgHashes.set(entry.id, svgHash);
    assertManifestHash(entry, 'source', sourceHash);
    assertManifestHash(entry, 'html', htmlHash);
    assertManifestHash(entry, 'png', pngHash);
    if (svgHash !== null) assertManifestHash(entry, 'svg', svgHash);

    let specification;
    try {
      specification = JSON.parse(sourceBytes.toString('utf8'));
    } catch (error) {
      throw new Error(`Diagram source is not valid JSON: ${entry.sourcePath}`, { cause: error });
    }
    assertSpecification(entry, specification);
    assertViewerHtml(entry, specification, htmlBytes.toString('utf8'), localFontBytes);
    if (svgBytes !== null) {
      const svg = svgBytes.toString('utf8');
      assertNoRemoteFontProvider(svg, `diagram ${entry.id} SVG`);
      assertEmbeddedLocalNotoFont(svg, `diagram ${entry.id} SVG`, localFontBytes);
      if (!/<svg\b[^>]*\brole="img"/iu.test(svg)) {
        throw new Error(`Diagram SVG accessibility contract failed for ${entry.id}.`);
      }
    }
    if (requireReceipts) assertReceipts(entry, sourceHash, htmlHash, svgHash);

    if (artifactDirectory !== null) {
      const emitted = [
        ['source', entry.sourcePath, sourceBytes],
        ['html', entry.htmlPath, htmlBytes],
        ['png', entry.pngPath, pngBytes],
      ];
      if (svgBytes !== null) emitted.push(['svg', entry.svg.path, svgBytes]);
      for (const [kind, relative, expected] of emitted) {
        const publicRelative = path.posix.relative('docs/website/public', relative);
        const generatedRelative = kind === 'png'
          ? path.posix.join('blume-assets/content/src/content/docs', path.posix.relative('docs/guide', relative))
          : publicRelative;
        const file = path.join(artifactDirectory, ...generatedRelative.split('/'));
        let actual;
        try {
          actual = await readFile(file);
        } catch (error) {
          throw new Error(`Built diagram ${kind} is missing: ${generatedRelative}`, { cause: error });
        }
        if (!actual.equals(expected)) {
          throw new Error(`Built diagram ${kind} is not byte-exact for ${entry.id}: ${generatedRelative}`);
        }
      }
    }
    for (const { name, value: variant } of responsiveVariantsFor(entry)) {
      await assertResponsiveVariantArtifacts({
        artifactDirectory,
        localFontBytes,
        name,
        requireReceipts,
        repository,
        variant,
      });
    }
  }

  await assertNoOrphans(
    path.join(repository, publicDiagramDirectory),
    new Set(manifest.diagrams.flatMap((entry) => diagramLinkRecords(entry)
      .map(({ path: filePath }) => path.posix.relative(publicDiagramDirectory, filePath)))),
    'public diagram',
  );
  await assertNoOrphans(
    path.join(repository, guideDiagramDirectory),
    new Set(diagramContractsList().map(({ id }) => `${id}.png`)),
    'diagram PNG',
  );

  return { manifest, sourceHashes, artifactHashes, svgHashes };
}

async function assertResponsiveVariantArtifacts({
  artifactDirectory,
  localFontBytes,
  name,
  requireReceipts,
  repository,
  variant,
}) {
  const id = `execution-overview.${name}`;
  const entry = { id, htmlPath: variant.htmlPath, receipts: variant.receipts, sourcePath: variant.sourcePath, type: 'architecture' };
  const sourceBytes = await readTrackedFile(variant.sourcePath, repository, 'Responsive diagram source');
  const htmlBytes = await readTrackedFile(variant.htmlPath, repository, 'Responsive diagram HTML');
  const svgBytes = await readTrackedFile(variant.svgPath, repository, 'Responsive diagram SVG');
  const sourceHash = sha256(sourceBytes);
  const htmlHash = sha256(htmlBytes);
  const svgHash = sha256(svgBytes);
  assertManifestHash({ ...entry, source: variant.source }, 'source', sourceHash);
  assertManifestHash({ ...entry, html: variant.html }, 'html', htmlHash);
  assertManifestHash({ ...entry, svg: variant.svg }, 'svg', svgHash);

  let specification;
  try {
    specification = JSON.parse(sourceBytes.toString('utf8'));
  } catch (error) {
    throw new Error(`Responsive diagram source is not valid JSON: ${variant.sourcePath}`, { cause: error });
  }
  assertSpecification(entry, specification);
  assertViewerHtml(entry, specification, htmlBytes.toString('utf8'), localFontBytes);
  const svg = svgBytes.toString('utf8');
  assertNoRemoteFontProvider(svg, `diagram ${id} SVG`);
  assertEmbeddedLocalNotoFont(svg, `diagram ${id} SVG`, localFontBytes);
  if (!/<svg\b[^>]*\brole="img"/iu.test(svg)) {
    throw new Error(`Diagram SVG accessibility contract failed for ${id}.`);
  }
  if (requireReceipts) assertReceipts(entry, sourceHash, htmlHash, svgHash);

  if (artifactDirectory === null) return;
  for (const [kind, relative, expected] of [
    ['source', variant.sourcePath, sourceBytes],
    ['html', variant.htmlPath, htmlBytes],
    ['svg', variant.svgPath, svgBytes],
  ]) {
    const generatedRelative = path.posix.relative('docs/website/public', relative);
    const file = path.join(artifactDirectory, ...generatedRelative.split('/'));
    let actual;
    try {
      actual = await readFile(file);
    } catch (error) {
      throw new Error(`Built responsive diagram ${kind} is missing: ${generatedRelative}`, { cause: error });
    }
    if (!actual.equals(expected)) {
      throw new Error(`Built responsive diagram ${kind} is not byte-exact for ${id}: ${generatedRelative}`);
    }
  }
}

async function assertNoOrphans(directory, expectedNames, kind) {
  let entries;
  try {
    entries = await readdir(directory, { withFileTypes: true });
  } catch (error) {
    if (error?.code === 'ENOENT') return;
    throw error;
  }
  for (const entry of entries) {
    if (entry.name.startsWith('.')) continue;
    if (!expectedNames.has(entry.name)) {
      throw new Error(`${kind} directory contains an unregistered file: ${entry.name}`);
    }
  }
}

function assertManifestHash(entry, kind, actual) {
  const expected = entry[kind].sha256;
  if (expected === null) throw new Error(`Diagram manifest ${entry.id} is missing the ${kind} sha256.`);
  if (expected !== actual) throw new Error(`Diagram ${kind} hash mismatch for ${entry.id}: expected ${expected}, found ${actual}.`);
}

function assertHashRecord(record, key, actual, label) {
  const expected = record[key];
  if (expected === null) throw new Error(`Diagram manifest is missing the ${label} ${key}.`);
  if (expected !== actual) throw new Error(`Diagram ${label} hash mismatch: expected ${expected}, found ${actual}.`);
}

function assertSpecification(entry, specification) {
  if (specification === null || typeof specification !== 'object' || Array.isArray(specification)
    || specification.schema_version !== 1 || specification.diagram_type !== entry.type
    || specification.meta?.quality_profile !== 'showcase'
    || specification.meta?.locale !== undefined || specification.meta?.animation === 'trace') {
    throw new Error(`Diagram source contract failed for ${entry.id}: expected typed showcase JSON with the default English viewer UI.`);
  }
  if (typeof specification.meta?.title !== 'string' || specification.meta.title.trim() === '') {
    throw new Error(`Diagram source is missing a title: ${entry.sourcePath}`);
  }
}

function assertViewerHtml(entry, specification, html, localFontBytes) {
  assertNoRemoteFontProvider(html, `diagram ${entry.id}`);
  assertEmbeddedLocalNotoFont(html, `diagram ${entry.id} HTML`, localFontBytes);
  if (!/^<!doctype html>/iu.test(html)
    || !html.includes(`<meta name="generator" content="archify ${ARCHIFY_VERSION}">`)
    || !/<html\s+lang="en"/iu.test(html)
    || !/<svg\b[^>]*\brole="img"/iu.test(html)
    || !/<title\b[^>]*>[^<]+<\/title>/iu.test(html)
    || !/<desc\b[^>]*>[^<]+<\/desc>/iu.test(html)) {
    throw new Error(`Diagram HTML viewer contract failed for ${entry.id}: expected a standalone accessible Archify artifact.`);
  }
  const title = escapeRegExp(escapeHtml(specification.meta.title));
  const documentTitlePattern = new RegExp(`<title\\b[^>]*>${title}(?: Diagram)?<\\/title>`, 'u');
  const diagramTitlePattern = new RegExp(`<title\\b[^>]*>${title}<\\/title>`, 'u');
  if (!documentTitlePattern.test(html) || !diagramTitlePattern.test(html)) {
    throw new Error(`Diagram HTML title does not match its source for ${entry.id}.`);
  }
}

function assertEmbeddedLocalNotoFont(text, location, localFontBytes) {
  if (!NOTO_FONT_FAMILY_PATTERN.test(text)) {
    throw new Error(`Static artifact must declare Noto Sans JP: ${location}`);
  }
  const payloads = [...text.matchAll(NOTO_FONT_DATA_PATTERN)];
  if (!payloads.some(([, payload]) => Buffer.from(payload, 'base64').equals(localFontBytes))) {
    throw new Error(`Static artifact must embed the exact local Noto Sans JP font: ${location}`);
  }
}

function assertOwnerGuideCoverage(entry, markdown) {
  const expectedImage = path.posix.relative(path.posix.dirname(entry.ownerSource), entry.pngPath);
  let imageFound = false;
  let fenced = false;
  for (const line of markdown.split(/\r?\n/u)) {
    const fence = line.match(/^\s*(```+|~~~+)/u);
    if (fence !== null) {
      fenced = !fenced;
      continue;
    }
    if (fenced) continue;
    for (const match of line.matchAll(/(!?\[[^\]]*\])\(([^)]+)\)/gu)) {
      const targetText = match[2].trim();
      const target = targetText.startsWith('<')
        ? targetText.slice(1, targetText.indexOf('>'))
        : targetText.split(/\s+/, 1)[0];
      if (match[1].startsWith('!') && target === expectedImage) imageFound = true;
    }
  }
  if (!imageFound) throw new Error(`Diagram ${entry.id} owner guide must reference ${expectedImage}.`);
}

export function assertNoRemoteFontProvider(text, location = 'artifact') {
  if (REMOTE_FONT_PROVIDER_PATTERN.test(text)) {
    throw new Error(`Static artifact must not depend on a remote font provider: ${location}`);
  }
  if (REMOTE_FONT_FACE_PATTERN.test(text)) {
    throw new Error(`Static artifact must not contain a remote @font-face source: ${location}`);
  }
}

function escapeHtml(value) {
  return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function assertReceipts(entry, sourceHash, htmlHash, svgHash) {
  const validate = entry.receipts.validate;
  if (validate === null || validate.ok !== true || validate.command !== 'validate' || validate.type !== entry.type
    || !Array.isArray(validate.checks) || validate.checks.length !== 9
    || validate.checks.some((check) => check?.ok !== true)
    || validate.composition?.profile !== 'showcase'
    || validate.composition?.status !== 'pass'
    || validate.composition?.summary?.errors !== 0
    || validate.composition?.summary?.warnings !== 0) {
    throw new Error(`Archify validate receipt for ${entry.id} must be 9/9 showcase with zero errors and warnings.`);
  }

  const deliver = entry.receipts.deliver;
  if (deliver === null || deliver.ok !== true || deliver.command !== 'deliver' || deliver.type !== entry.type
    || deliver.specification?.sha256 !== sourceHash || deliver.artifact?.sha256 !== htmlHash
    || deliver.validation?.checksPassed !== 9 || deliver.validation?.checkCount !== 9
    || deliver.validation?.compositionProfile !== 'showcase'
    || deliver.validation?.compositionStatus !== 'pass'
    || deliver.validation?.errors !== 0 || deliver.validation?.warnings !== 0) {
    throw new Error(`Archify deliver receipt for ${entry.id} must bind source/output hashes and pass 9/9 showcase.`);
  }

  const visual = entry.receipts.visualCheck;
  if (visual !== null && (visual.ok !== true || visual.command !== 'visual-check' || visual.status !== 'pass'
    || visual.artifact?.sha256 !== htmlHash)) {
    throw new Error(`Archify visual-check receipt for ${entry.id} is not a passing artifact-bound receipt.`);
  }

  const svgExport = entry.receipts.svgExport;
  if (svgHash === null) {
    if (svgExport !== undefined && svgExport !== null) {
      throw new Error(`Archify SVG export receipt is only valid for the canonical SVG diagram: ${entry.id}.`);
    }
  } else if (svgExport === null || typeof svgExport !== 'object' || Array.isArray(svgExport)
    || svgExport.command !== 'Archify viewer Export > Image > SVG'
    || svgExport.status !== 'pass'
    || svgExport.html?.sha256 !== htmlHash
    || svgExport.svg?.sha256 !== svgHash) {
    throw new Error(`Archify SVG export receipt for ${entry.id} must bind HTML/SVG hashes and pass.`);
  }
}

async function readTrackedFile(relative, repository, kind) {
  await assertRegularTrackedPath(relative, repository, kind);
  return readFile(resolveRepositoryPath(repository, relative));
}

async function assertRegularTrackedPath(relative, repository, kind) {
  const absolute = resolveRepositoryPath(repository, relative);
  let status;
  try {
    status = await lstat(absolute);
  } catch (error) {
    throw new Error(`${kind} is missing: ${relative}`, { cause: error });
  }
  if (!status.isFile() || status.isSymbolicLink()) throw new Error(`${kind} must be a regular file: ${relative}`);
  const canonicalRepository = await realpath(repository);
  const canonicalAbsolute = await realpath(absolute);
  const canonicalRelative = path.relative(canonicalRepository, canonicalAbsolute).split(path.sep).join('/');
  if (canonicalRelative !== relative || canonicalRelative.startsWith('../') || path.posix.isAbsolute(canonicalRelative)) {
    throw new Error(`${kind} must resolve to the tracked path without a symlink or repository escape: ${relative}`);
  }
  try {
    await execFileAsync('git', ['-C', repository, 'ls-files', '--error-unmatch', '--', relative], { encoding: 'utf8' });
  } catch (error) {
    throw new Error(`${kind} must be tracked by git: ${relative}`, { cause: error });
  }
  return absolute;
}

function resolveRepositoryPath(repository, relative) {
  if (typeof relative !== 'string' || relative.startsWith('/') || relative.includes('\\')
    || relative !== path.posix.normalize(relative) || relative.startsWith('../') || relative.includes('/../')) {
    throw new Error(`Diagram path is unsafe: ${relative}`);
  }
  const absolute = path.resolve(repository, ...relative.split('/'));
  const resolvedRelative = path.relative(repository, absolute).split(path.sep).join('/');
  if (resolvedRelative !== relative || resolvedRelative.startsWith('../') || path.posix.isAbsolute(resolvedRelative)) {
    throw new Error(`Diagram path escapes the repository: ${relative}`);
  }
  return absolute;
}

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

export async function assertDiagramSourceArtifacts(options = {}) {
  return assertDiagramArtifacts({ ...options, artifactDirectory: null });
}

if (path.resolve(process.argv[1] ?? '') === fileURLToPath(import.meta.url)) {
  const mode = process.argv[2] ?? 'source';
  if (mode === 'source') {
    await assertDiagramSourceArtifacts();
  } else if (mode === 'artifact') {
    await assertDiagramArtifacts({ artifactDirectory: distRoot });
  } else {
    throw new Error(`Unknown Archify diagram check mode: ${mode}`);
  }
  console.log(`Archify diagram ${mode} check passed.`);
}
