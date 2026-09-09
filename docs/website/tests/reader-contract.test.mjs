import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { createHash } from 'node:crypto';
import { cp, mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import { JSDOM } from 'jsdom';
import path from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { promisify } from 'node:util';
import { contentMap } from '../content-map.mjs';
import {
  assertArtifactReaderText,
  assertArtifactReaderFile,
  assertPlaceholderParity,
  extractVisibleInlineCodeInventory,
  assertNoInternalEvidenceVoice,
  assertNoCurrentMainOnly,
  assertNoProtectedDecode,
  assertNoUnsafeLgtmDiagnostics,
  normalizeArtifactVisibleText,
  contentPipelineTitle,
  markdownFenceCloses,
  markdownFenceLine,
  markdownFenceRanges,
  markdownHtmlBlockRanges,
  nextMarkdownFenceState,
  nextRawFenceState,
  placeholderInventory,
  renderReaderBody,
  rewriteRelativeImageReferences,
  sourcePhysicalLines,
  assertSourceDerivedReferenceCoverage,
  stableReferenceExclusionPaths,
  stableReferenceExclusionsFor,
  validateArtifactReaderContract,
  validateLlmRouteInventory,
  validateArtifactPageRouteInventory,
  validateReferenceDocumentation,
  validateReaderContract,
  validateSearchRouteInventory,
} from '../scripts/reader-contract.mjs';
import { loadDiagramManifest } from '../scripts/archify-diagrams.mjs';
import {
  contentRoot as canonicalContentRoot,
  manifestPath as canonicalManifestPath,
  repositoryRoot,
} from '../scripts/website-paths.mjs';

const execFileAsync = promisify(execFile);
const blumeRequire = createRequire(import.meta.resolve('blume/package.json'));
const blumePackageRoot = path.dirname(blumeRequire.resolve('blume/package.json'));
const { fenceRanges, toPlainText } = await import(pathToFileURL(path.join(blumePackageRoot, 'src/search/plain-text.mjs')).href);
const { codeToHtml } = await import(pathToFileURL(blumeRequire.resolve('shiki')).href);
const { createSatteriMarkdownProcessor } = await import(pathToFileURL(blumeRequire.resolve('@astrojs/markdown-satteri')).href);
const satteriMarkdownProcessor = await createSatteriMarkdownProcessor({ syntaxHighlight: false });

function escapeHtml(value) {
  return value.replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');
}

async function writeSyntheticCompleteReaderArtifact({ artifactDirectory, contentMap: map }) {
  const pages = Object.entries(map).filter(([source]) => source !== 'README.md');
  assert.equal(pages.length, 40, 'Synthetic reader artifact must contain exactly 40 non-Landing pages.');
  const routes = pages.map(([source, metadata]) => ({
    source,
    metadata,
    route: metadata.slug === 'index' ? '/' : `/${metadata.slug}`,
  }));
  const canonicalManifestText = await readFile(canonicalManifestPath, 'utf8');
  const canonicalManifest = JSON.parse(canonicalManifestText);
  const canonicalPages = new Map(canonicalManifest.pages
    .filter(({ source }) => source !== 'README.md')
    .map((page) => [page.source, page]));
  const fixtureRoot = path.dirname(artifactDirectory);
  const fixtureManifestPath = path.join(fixtureRoot, '.generated', 'content-manifest.json');
  const fixtureContentRoot = path.join(fixtureRoot, 'src', 'content', 'docs');
  const publicRawBySource = new Map();
  const generatedBody = (raw, source) => {
    const frontmatter = raw.match(/^---\n[\s\S]*?\n---\n/u)?.[0];
    assert.ok(frontmatter, `Synthetic canonical Raw must contain frontmatter for ${source}.`);
    return raw.slice(frontmatter.length);
  };
  const searchContentFor = (source) => {
    const raw = publicRawBySource.get(source);
    assert.ok(raw, `Synthetic public Raw must exist for ${source}.`);
    return toPlainText(generatedBody(raw, source));
  };

  await mkdir(artifactDirectory, { recursive: true });
  await mkdir(path.dirname(fixtureManifestPath), { recursive: true });
  await cp(canonicalContentRoot, fixtureContentRoot, { recursive: true });
  await writeFile(fixtureManifestPath, canonicalManifestText, 'utf8');
  for (const { metadata, source: sourceName } of routes) {
    const htmlGenerated = path.join(artifactDirectory, ...metadata.slug.split('/'));
    const outcome = metadata.reader.outcome;
    const manifestPage = canonicalPages.get(sourceName);
    assert.ok(manifestPage, `Canonical content manifest is missing ${sourceName}.`);
    const canonicalRawPath = path.join(canonicalContentRoot, ...manifestPage.generated.split('/'));
    const canonicalRaw = await readFile(canonicalRawPath);
    const generatedRawPath = path.join(artifactDirectory, ...manifestPage.generated.split('/'));
    const fixtureRawPath = path.join(fixtureContentRoot, ...manifestPage.generated.split('/'));
    await mkdir(path.dirname(generatedRawPath), { recursive: true });
    await mkdir(path.dirname(fixtureRawPath), { recursive: true });
    const expectedRaw = await rewriteRelativeImageReferences({
      source: canonicalRaw.toString('utf8'),
      sourcePath: fixtureRawPath,
      projectRoot: fixtureRoot,
    });
    publicRawBySource.set(sourceName, expectedRaw);
    await writeFile(generatedRawPath, expectedRaw);
    await writeFile(fixtureRawPath, canonicalRaw);
    const renderedBody = await renderReaderBody(generatedBody(expectedRaw, sourceName));
    const mermaidElements = renderedBody.mermaid
      .map((diagram) => `<blume-mermaid data-source="${escapeHtml(diagram)}"></blume-mermaid>`)
      .join('');
    await mkdir(htmlGenerated, { recursive: true });
    await writeFile(
      path.join(htmlGenerated, 'index.html'),
      `<!doctype html><html><head><meta charset="utf-8"><title>Reader page</title></head><body><main><article class="prose"><h1>${escapeHtml(manifestPage.title)}</h1><p class="text-lg text-muted-foreground">${escapeHtml(outcome)}</p>${renderedBody.html}${mermaidElements}</article></main></body></html>\n`,
      'utf8',
    );
  }

  await writeFile(
    path.join(artifactDirectory, 'blume-search.json'),
    `${JSON.stringify(routes.map(({ route, metadata, source: sourceName }) => ({
      route,
      url: route,
      title: metadata.slug,
      description: metadata.reader.outcome,
      content: searchContentFor(sourceName),
    })), null, 2)}\n`,
    'utf8',
  );
  await writeFile(
    path.join(artifactDirectory, 'llms.txt'),
    `${routes.map(({ route, metadata }) => `- [${metadata.slug}](${route}): ${metadata.reader.outcome}`).join('\n')}\n`,
    'utf8',
  );
  await writeFile(
    path.join(artifactDirectory, 'llms-full.txt'),
    `${routes.map(({ route, source: sourceName }) => {
      const manifestPage = canonicalPages.get(sourceName);
      assert.ok(manifestPage, `Canonical content manifest is missing ${sourceName}.`);
      const raw = publicRawBySource.get(sourceName);
      assert.ok(raw, `Synthetic public Raw must exist for ${sourceName}.`);
      return `# ${manifestPage.title}\nSource: https://docs.example.test${route}\n\n${generatedBody(raw, sourceName).trim()}`;
    }).join('\n---\n\n')}\n`,
    'utf8',
  );
  return { manifestPath: fixtureManifestPath, contentRoot: fixtureContentRoot };
}

test('canonical Content Map is the one 40-page reader inventory', () => {
  const result = validateReaderContract(contentMap);
  assert.deepEqual(result.counts, { tutorial: 3, 'how-to': 18, concept: 10, reference: 8, troubleshooting: 1 });
  assert.equal(result.pages.length, 40);
  assert.equal(new Set(result.pages.map(({ outcome }) => outcome)).size, 40);
});

test('Stable Reference exclusions are exact and bound to the current release authority', async () => {
  const authority = JSON.parse(await readFile(path.join(repositoryRoot, 'develop/spec/release-authority.json'), 'utf8'));
  const exclusions = stableReferenceExclusionsFor(authority);
  assert.equal(exclusions.size, 9);
  assert.deepEqual([...exclusions], stableReferenceExclusionPaths);
  for (const sourcePath of stableReferenceExclusionPaths) assert.equal(exclusions.has(sourcePath), true);
  for (const sourcePath of [
    'src/Audit/AuditOpaqueIdKeyProviderExtra.php',
    'src/Internal/Console/DiagnosticsCheckCommands.php',
    'src/Internal/Console/QueueStatusCommand.php.bak',
    'src/Internal/Projection/Route/RouteProjectionListCommand.php.disabled',
    'src/Internal/Application/ApplicationAuditConfigurationTest.php',
  ]) assert.equal(exclusions.has(sourcePath), false);

  const changedAuthority = JSON.parse(JSON.stringify(authority));
  changedAuthority.currentStable.framework.peeledSource = '0000000000000000000000000000000000000000';
  assert.throws(() => stableReferenceExclusionsFor(changedAuthority), /reevaluate the exact paths/);
});

test('Source-derived Reference coverage applies the exact Stable boundary to the extractor', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-stable-reference-boundary-'));
  try {
    await cp(path.join(repositoryRoot, 'src'), path.join(temporary, 'src'), { recursive: true });
    await mkdir(path.join(temporary, 'docs/guide'), { recursive: true });
    for (const name of ['core-api.md', 'attributes.md', 'project-cli.md', 'configuration.md']) {
      await cp(path.join(repositoryRoot, 'docs/guide', name), path.join(temporary, 'docs/guide', name));
    }
    await mkdir(path.join(temporary, 'develop/spec'), { recursive: true });
    await cp(
      path.join(repositoryRoot, 'develop/spec/release-authority.json'),
      path.join(temporary, 'develop/spec/release-authority.json'),
    );

    await assert.doesNotReject(() => assertSourceDerivedReferenceCoverage(contentMap, temporary));
    const applicationBuilderPath = path.join(temporary, 'src/Application/ApplicationBuilder.php');
    const applicationBuilder = await readFile(applicationBuilderPath, 'utf8');
    const renamedApplicationBuilder = applicationBuilder.replace(
      'withOperationalHealthQuery(',
      'withOperationalHealthQueryExtra(',
    );
    assert.notEqual(renamedApplicationBuilder, applicationBuilder);
    try {
      await writeFile(applicationBuilderPath, renamedApplicationBuilder, 'utf8');
      await assert.rejects(
        assertSourceDerivedReferenceCoverage(contentMap, temporary),
        /Core API exact Return Method mapping count drifted for source-derived type: BlackOps\\Application\\ApplicationBuilder/,
      );
    } finally {
      await writeFile(applicationBuilderPath, applicationBuilder, 'utf8');
    }

    await writeFile(
      path.join(temporary, 'src/Audit/AuditOpaqueIdKeyProviderExtra.php'),
      '<?php\nnamespace BlackOps\\Audit;\n\n#[PublicApi]\ninterface AuditOpaqueIdKeyProviderExtra {}\n',
      'utf8',
    );
    await assert.rejects(
      assertSourceDerivedReferenceCoverage(contentMap, temporary),
      /Source-derived PublicApi coverage expected 216 types; found 217/,
    );
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('source inline placeholder inventory is the current 50-occurrence contract', async () => {
  const counts = new Map();
  for (const source of Object.keys(contentMap).filter((name) => name !== 'README.md')) {
    const inventory = placeholderInventory(await readFile(path.join(repositoryRoot, 'docs/guide', source), 'utf8'), { inlineCodeOnly: true });
    for (const [token, count] of Object.entries(inventory.counts)) counts.set(token, (counts.get(token) ?? 0) + count);
  }
  const total = [...counts.values()].reduce((sum, count) => sum + count, 0);
  assert.equal(total, 50);
  assert.equal(counts.get('<command>'), 2);
  assert.equal(counts.get('<operation-id>'), 8);
  assert.equal(counts.get('<application-root>'), 1);
});

test('placeholder parity preserves inline literal tokens and rejects dangling variants', () => {
  const source = [
    'inline `<command>` and ``<operation-id>`` plus ````<application-root>````',
    '<span><command></span> and <Component data-token="<operation-id>"> remain outside inline code.',
    '```text\n`<command>`\n```',
    '~~~text\n`<operation-id>`\n~~~',
  ].join('\n');
  const expected = placeholderInventory(source, { inlineCodeOnly: true });
  assert.equal(expected.total, 3);
  for (const token of ['<command>', '<operation-id>', '<application-root>']) assert.equal(expected.counts[token], 1);
  assert.equal(placeholderInventory('<Component value={`<command>`} />', { inlineCodeOnly: true }).total, 0);
  assert.equal(placeholderInventory('<span>prefix `<command>` suffix</span>', { inlineCodeOnly: true }).counts['<command>'], 1);
  assert.doesNotThrow(() => assertPlaceholderParity(expected, source, {
    actualInlineCodeOnly: true,
    location: 'placeholder-source-direct',
  }));
  assert.doesNotThrow(() => assertPlaceholderParity(expected, '<command> <operation-id> <application-root>', {
    location: 'placeholder-search-direct',
  }));
  assert.throws(() => assertPlaceholderParity(expected, '<command> command>', {
    location: 'placeholder-extra-closing-direct',
  }), /dangling/);
  assert.doesNotThrow(() => assertPlaceholderParity(expected, normalizeArtifactVisibleText('<p>&lt;command&gt; &lt;operation-id&gt; &lt;application-root&gt;</p>'), {
    location: 'placeholder-html-direct',
  }));
  assert.doesNotThrow(() => assertPlaceholderParity(expected, '<command> <operation-id> <application-root>', {
    location: 'placeholder-llm-direct',
  }));
  assert.equal(placeholderInventory('```text\n<command>\n```', { inlineCodeOnly: true }).total, 0);

  for (const token of ['<command>', '<operation-id>', '<application-root>']) {
    const missingClosing = token.slice(0, -1);
    assert.throws(() => assertPlaceholderParity(expected, `\`${missingClosing}\``, {
      actualInlineCodeOnly: true,
      location: `placeholder-missing-closing-${token}`,
    }), /dangling/);
    assert.throws(() => assertPlaceholderParity(expected, `\`${token.slice(1, -1)}>\``, {
      actualInlineCodeOnly: true,
      location: `placeholder-missing-opening-${token}`,
    }), /dangling/);
  }
});

test('Source fence grammar is shared by ranges, titles, and raw-image state', () => {
  const backtick = markdownFenceLine('   ```text');
  assert.deepEqual(backtick, { character: '`', length: 3, info: 'text', rawInfo: 'text' });
  assert.equal(markdownFenceCloses('  ```` \t', backtick), true, 'A longer same-marker close with spaces/tabs is valid.');
  assert.equal(markdownFenceCloses('  ``` trailing', backtick), false, 'A backtick close with trailing text stays inside the fence.');
  assert.equal(markdownFenceCloses('  ~~~', backtick), false, 'A mixed-marker close stays inside the fence.');

  const tilde = markdownFenceLine('~~~md');
  assert.equal(markdownFenceCloses('~~~~\t', tilde), true, 'A longer tilde close with trailing tabs is valid.');
  assert.equal(markdownFenceCloses('~~~ trailing', tilde), false, 'A tilde close with trailing text stays inside the fence.');
  assert.deepEqual(nextMarkdownFenceState('~~~', backtick), backtick, 'A mixed marker preserves the open state.');
  assert.equal(nextRawFenceState('````  ', backtick), null, 'Raw image rewriting sees the same valid longer close.');

  const invalidClose = '```text\n# hidden title\n``` trailing\n# still hidden';
  assert.deepEqual(markdownFenceRanges(invalidClose), [[0, invalidClose.length]]);
  assert.throws(() => contentPipelineTitle(invalidClose), /title is missing/);

  const validLongClose = '```text\n# hidden title\n````\n# visible title';
  const validLongCloseEnd = validLongClose.lastIndexOf('````') + 4;
  assert.deepEqual(markdownFenceRanges(validLongClose), [[0, validLongCloseEnd]]);
  assert.equal(contentPipelineTitle(validLongClose), 'visible title');

  const mixedClose = '~~~text\n# hidden title\n```\n# still hidden';
  assert.deepEqual(markdownFenceRanges(mixedClose), [[0, mixedClose.length]]);
});

test('Source physical-line grammar preserves LF, CRLF, and lone-CR behavior', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-source-lines-'));
  try {
    const projectRoot = path.join(temporary, 'project');
    const sourcePath = path.join(projectRoot, 'src', 'content', 'docs', 'source-lines.md');
    const imagePath = path.join(projectRoot, 'src', 'content', 'docs', 'assets', 'diagram.png');
    await mkdir(path.dirname(sourcePath), { recursive: true });
    await mkdir(path.dirname(imagePath), { recursive: true });
    await writeFile(imagePath, 'image fixture', 'utf8');
    const logical = [
      '# visible title',
      '',
      'Visible `<command>`.',
      '```text',
      'Fenced `<operation-id>`.',
      '![fenced](assets/diagram.png)',
      '```',
      '![outside](assets/diagram.png)',
    ].join('\n');
    const malformedLogical = [
      '```text',
      '# hidden title',
      'Hidden `<operation-id>`.',
      '![still-fenced](assets/diagram.png)',
      '``` trailing',
      '# still fenced',
    ].join('\n');
    for (const terminator of ['\n', '\r\n', '\r']) {
      const source = logical.replaceAll('\n', terminator);
      const physical = [...sourcePhysicalLines(source)];
      assert.equal(physical.map(({ line }) => line).join('\n'), logical);
      assert.deepEqual(physical.map(({ terminator: actual }) => actual), [
        ...Array(logical.split('\n').length - 1).fill(terminator),
        '',
      ]);
      assert.equal(contentPipelineTitle(source), 'visible title');
      const ranges = markdownFenceRanges(source);
      assert.equal(ranges.length, 1);
      assert.equal(source.slice(...ranges[0]).replace(/\r\n?|\n/gu, '\n'), [
        '```text',
        'Fenced `<operation-id>`.',
        '![fenced](assets/diagram.png)',
        '```',
      ].join('\n'));
      const inventory = placeholderInventory(source, { inlineCodeOnly: true });
      assert.deepEqual(inventory, { total: 1, counts: { '<command>': 1 } });
      const rewritten = await rewriteRelativeImageReferences({ source, sourcePath, projectRoot });
      const expected = [
        '# visible title',
        '',
        'Visible `<command>`.',
        '```text',
        'Fenced `<operation-id>`.',
        '![fenced](assets/diagram.png)',
        '```',
        '![outside](/blume-assets/content/src/content/docs/assets/diagram.png)',
      ].join(terminator);
      assert.equal(rewritten, expected);

      const malformed = malformedLogical.replaceAll('\n', terminator);
      assert.deepEqual(markdownFenceRanges(malformed), [[0, malformed.length]]);
      assert.throws(() => contentPipelineTitle(malformed), /title is missing/);
      assert.equal(placeholderInventory(malformed, { inlineCodeOnly: true }).total, 0);
      assert.equal(await rewriteRelativeImageReferences({ source: malformed, sourcePath, projectRoot }), malformed);
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('Source container-aware fences share list/blockquote state across line endings', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-container-fences-'));
  try {
    const projectRoot = path.join(temporary, 'project');
    const sourcePath = path.join(projectRoot, 'src', 'content', 'docs', 'testing', 'community-board.md');
    const imagePath = path.join(projectRoot, 'src', 'content', 'docs', 'assets', 'community-board', 'blackops-board.png');
    await mkdir(path.dirname(sourcePath), { recursive: true });
    await mkdir(path.dirname(imagePath), { recursive: true });
    await writeFile(imagePath, 'image fixture', 'utf8');
    const imageTarget = '../assets/community-board/blackops-board.png';
    const logical = [
      '- ```md',
      '  `<command>`',
      `  ![shown](${imageTarget})`,
      '  ```',
      '# visible',
      `![outside](${imageTarget})`,
    ].join('\n');
    const nestedLogical = [
      '> - outer',
      '>   - ```md',
      '>     `<operation-id>`',
      '>     ```',
      '> after',
      '# nested visible',
    ].join('\n');
    const malformedLogical = [
      '- ```md',
      '  `<command>`',
      `  ![shown](${imageTarget})`,
      '  ``` trailing',
      '# malformed visible',
      `![outside-after-malformed](${imageTarget})`,
    ].join('\n');
    const mixedLogical = [
      '- ```md',
      '  `<command>`',
      '  ~~~',
      '# mixed visible',
    ].join('\n');
    const longerLogical = [
      '- ```md',
      '  `<command>`',
      '  ````',
      '# long visible',
    ].join('\n');
    for (const terminator of ['\n', '\r\n', '\r']) {
      const source = logical.replaceAll('\n', terminator);
      const closeEnd = source.indexOf(`${terminator}# visible`);
      assert.deepEqual(markdownFenceRanges(source), [[0, closeEnd]], `same-line list fence range drifted for ${JSON.stringify(terminator)}`);
      assert.equal(contentPipelineTitle(source), 'visible');
      assert.equal(placeholderInventory(source, { inlineCodeOnly: true }).total, 0);
      const rewritten = await rewriteRelativeImageReferences({ source, sourcePath, projectRoot });
      assert.equal(rewritten, [
        '- ```md',
        '  `<command>`',
        `  ![shown](${imageTarget})`,
        '  ```',
        '# visible',
        '![outside](/blume-assets/content/src/content/docs/assets/community-board/blackops-board.png)',
      ].join(terminator));

      const nested = nestedLogical.replaceAll('\n', terminator);
      const nestedOpen = nested.indexOf('>   - ```md');
      const nestedCloseEnd = nested.indexOf(`${terminator}> after`);
      assert.deepEqual(markdownFenceRanges(nested), [[nestedOpen, nestedCloseEnd]], `nested blockquote/list fence drifted for ${JSON.stringify(terminator)}`);
      assert.equal(contentPipelineTitle(nested), 'nested visible');
      assert.equal(placeholderInventory(nested, { inlineCodeOnly: true }).total, 0);

      const malformed = malformedLogical.replaceAll('\n', terminator);
      const malformedCloseEnd = malformed.indexOf(`${terminator}# malformed visible`);
      assert.deepEqual(markdownFenceRanges(malformed), [[0, malformedCloseEnd]]);
      assert.equal(contentPipelineTitle(malformed), 'malformed visible');
      assert.equal(placeholderInventory(malformed, { inlineCodeOnly: true }).total, 0);
      assert.equal(await rewriteRelativeImageReferences({ source: malformed, sourcePath, projectRoot }), [
        '- ```md',
        '  `<command>`',
        `  ![shown](${imageTarget})`,
        '  ``` trailing',
        '# malformed visible',
        '![outside-after-malformed](/blume-assets/content/src/content/docs/assets/community-board/blackops-board.png)',
      ].join(terminator));

      const mixed = mixedLogical.replaceAll('\n', terminator);
      const mixedCloseEnd = mixed.indexOf(`${terminator}# mixed visible`);
      assert.deepEqual(markdownFenceRanges(mixed), [[0, mixedCloseEnd]]);
      assert.equal(contentPipelineTitle(mixed), 'mixed visible');
      assert.equal(placeholderInventory(mixed, { inlineCodeOnly: true }).total, 0);

      const longer = longerLogical.replaceAll('\n', terminator);
      const longerCloseEnd = longer.indexOf(`${terminator}# long visible`);
      assert.deepEqual(markdownFenceRanges(longer), [[0, longerCloseEnd]]);
      assert.equal(contentPipelineTitle(longer), 'long visible');
      assert.equal(placeholderInventory(longer, { inlineCodeOnly: true }).total, 0);
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('Raw expected image bytes mirror Blume relative-image rewrite boundaries', async () => {
  const communityRawPath = path.join(canonicalContentRoot, 'testing', 'community-board.md');
  const communityRaw = await readFile(communityRawPath, 'utf8');
  const communityRewritten = await rewriteRelativeImageReferences({
    source: communityRaw,
    sourcePath: communityRawPath,
    projectRoot: path.resolve(canonicalContentRoot, '../../..'),
  });
  assert.match(
    communityRewritten,
    /!\[BlackOps BoardのCredential-free Landing画面\]\(\/blume-assets\/content\/src\/content\/docs\/assets\/community-board\/blackops-board\.png\)/u,
  );

  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-image-rewrite-'));
  try {
    const projectRoot = path.join(temporary, 'project');
    const sourcePath = path.join(projectRoot, 'src', 'content', 'docs', 'guides', 'image-page.md');
    const imagePath = path.join(projectRoot, 'src', 'content', 'docs', 'assets', 'space 名', 'diagram 名.PNG');
    await mkdir(path.dirname(sourcePath), { recursive: true });
    await mkdir(path.dirname(imagePath), { recursive: true });
    await writeFile(imagePath, 'image fixture', 'utf8');
    const source = [
      '![real](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG "title")',
      '`![inline](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)`',
      '~~~md',
      '![fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '~~~',
      '```md',
      '![invalid-close](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '``` trailing',
      '![still-fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '````',
      '![long-close](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '~~~md',
      '![mixed-fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '```',
      '![mixed-still-fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '~~~',
      '![missing](../assets/missing.png)',
      '![remote](https://example.test/image.png)',
      '![absolute](/image.png)',
      '![hash](#image.png)',
      '![non-image](../assets/space%20%E5%90%8D/manual.pdf)',
    ].join('\n');
    const rewritten = await rewriteRelativeImageReferences({ source, sourcePath, projectRoot });
    assert.equal(rewritten, [
      '![real](/blume-assets/content/src/content/docs/assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG "title")',
      '`![inline](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)`',
      '~~~md',
      '![fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '~~~',
      '```md',
      '![invalid-close](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '``` trailing',
      '![still-fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '````',
      '![long-close](/blume-assets/content/src/content/docs/assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '~~~md',
      '![mixed-fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '```',
      '![mixed-still-fenced](../assets/space%20%E5%90%8D/diagram%20%E5%90%8D.PNG)',
      '~~~',
      '![missing](../assets/missing.png)',
      '![remote](https://example.test/image.png)',
      '![absolute](/image.png)',
      '![hash](#image.png)',
      '![non-image](../assets/space%20%E5%90%8D/manual.pdf)',
    ].join('\n'));
    await assert.rejects(
      () => rewriteRelativeImageReferences({
        source: '![escape](../../../../../outside.png)',
        sourcePath,
        projectRoot,
      }),
      /escapes the website project root/,
    );
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('patched Blume plain-text extraction uses one production helper for fences, spans, JSX, and headings', async () => {
  const documentsSource = await readFile(path.join(blumePackageRoot, 'src/search/documents.ts'), 'utf8');
  const cliSource = await readFile(path.join(blumePackageRoot, 'dist/cli/index.js'), 'utf8');
  assert.match(documentsSource, /import\s+\{\s*toPlainText\s*\}\s+from\s+["']\.\/plain-text\.mjs["']/u);
  assert.match(cliSource, /import\s+\{\s*toPlainText\s*\}\s+from\s+["']\.\.\/\.\.\/src\/search\/plain-text\.mjs["']/u);

  const fixtures = [
    ['backtick fence', 'before\n```text\nFENCED_BODY\n```\nafter', 'before after'],
    ['tilde fence', 'before\n~~~text\nTILDE_BODY\n~~~\nafter', 'before after'],
    ['mixed marker prose', 'before\n~~``\nMIXED_BODY\n~~~~\nafter', 'before `` MIXED BODY'],
    ['one-backtick span', 'prefix `one` suffix', 'prefix one suffix'],
    ['two-backtick span', 'prefix ``two`` suffix', 'prefix two suffix'],
    ['four-backtick span', 'prefix ````four```` suffix', 'prefix four suffix'],
    ['arbitrary span content', 'prefix ````a ` b```` suffix', 'prefix a ` b suffix'],
    ['JSX template attribute', '<Component value={`<command>`} />', ''],
    ['HTML prose', '<p>visible prose</p>', 'visible prose'],
    ['heading adjacency', '# real heading\nprefix `code`# not a heading', 'real heading prefix code# not a heading'],
    ['Markdown punctuation', '![alt](x) [link](url) **bold** _em_ > quote', 'link bold em quote'],
    ['backtick close trailing text', 'before\n```text\nFENCED_BODY\n``` trailing\nafter', 'before'],
    ['tilde close trailing text', 'before\n~~~text\nFENCED_BODY\n~~~ trailing\nafter', 'before'],
    ['valid longer backtick close', 'before\n```text\nFENCED_BODY\n````\nafter', 'before after'],
    ['valid longer tilde close', 'before\n~~~text\nFENCED_BODY\n~~~~\nafter', 'before after'],
    ['mixed marker close', 'before\n```text\nFENCED_BODY\n~~~\nafter', 'before'],
    ['blockquote backtick fence', 'before\n> ```text\n> FENCED_BODY\n> ```\nafter', 'before after'],
    ['blockquote tilde fence', 'before\n> ~~~text\n> FENCED_BODY\n> ~~~\nafter', 'before after'],
    ['list continuation fence', 'before\n- item\n  ```text\n  FENCED_BODY\n  ```\nafter', 'before - item after'],
    ['list explicit fence with continuation body', 'before\n- ```text\n  FENCED_BODY\n  ```\nafter', 'before after'],
    ['list tilde fence with continuation body', 'before\n- ~~~text\n  FENCED_BODY\n  ~~~\nafter', 'before after'],
    ['nested mixed-container fence', 'before\n> - - ```text\n>       FENCED_BODY\n>       ```\nafter', 'before after'],
    ['implicit blockquote container exit and same-line reprocess', 'before\n> ```text\n> FENCED_BODY\n```text\nSECOND_BODY\n```\ntail', 'before tail'],
    ['mismatched blockquote close cannot close', 'before\n> ```text\n> FENCED_BODY\n```\nafter', 'before'],
    ['top-level fence indentation may differ by three spaces', 'before\n   ```text\nFENCED_BODY\n  ```\nafter', 'before after'],
    ['list marker padding one through four', 'before\n-    ```text\n     FENCED_BODY\n     ```\nafter', 'before after'],
    ['list marker padding five is not a fence', 'before\n-     ```text\n     BODY\n     ```\nafter', 'before - text BODY after'],
    ['second list-item fence is reprocessed as a new opening', 'before\n- ```text\n  FIRST_BODY\n- ```\n  SECOND_BODY\n  ```\nafter', 'before after'],
    ['cleared list context allows a later top-level unclosed fence', 'before\n- item\nafter\n  ```text\n  BODY\nlater', 'before - item after'],
  ];
  for (const [name, source, expected] of fixtures) {
    assert.equal(toPlainText(source), expected, `Blume helper fixture failed: ${name}`);
  }
});

test('patched Blume CommonMark ordered and nested fence boundaries use the renderer path', async () => {
  const fixtures = [
    {
      name: 'ordered-one-interrupts-paragraph',
      source: 'before\n1. ```bash\n   declare -p GRAFANA_PASSWORD\n   ```\nafter',
      expected: 'before after',
      hidden: 'declare -p GRAFANA_PASSWORD',
    },
    {
      name: 'ordered-content-column-three-partial-tab-fence',
      source: '1. outer\n\t```text\n\tHIDDEN_TAB_BODY\n\t```\nafter',
      expected: '1. outer after',
      hidden: 'HIDDEN_TAB_BODY',
      visible: 'after',
      outsideFence: 'after',
    },
    {
      name: 'ordered-continuation-prose-then-partial-tab-fence',
      source: '1. outer\n\tcontinued\n\t```text\n\tdeclare -p GRAFANA_PASSWORD\n\t```\nafter',
      expected: '1. outer continued after',
      hidden: 'declare -p GRAFANA_PASSWORD',
      visible: 'continued',
      outsideFence: 'after',
    },
    {
      name: 'unordered-content-column-two-partial-tab-fence',
      source: '- outer\n\t```text\n\tHIDDEN_UNORDERED_TAB\n\t```\nafter',
      expected: '- outer after',
      hidden: 'HIDDEN_UNORDERED_TAB',
      visible: 'after',
      outsideFence: 'after',
    },
    {
      name: 'ordered-space-tab-partial-fence',
      source: '1. outer\n \t```text\n \tHIDDEN_SPACE_TAB\n \t```\nafter',
      expected: '1. outer after',
      hidden: 'HIDDEN_SPACE_TAB',
      visible: 'after',
      outsideFence: 'after',
    },
    {
      name: 'nested-blockquote-list-partial-tab-fence',
      source: '> - outer\n>   1. ```text\n>    \tHIDDEN_MIXED_TAB\n>    \t```\n> after',
      expected: '- outer after',
      hidden: 'HIDDEN_MIXED_TAB',
      visible: 'after',
      outsideFence: 'after',
    },
    {
      name: 'nested-ordered-space-tab-dedent-keeps-visible-token',
      source: '- outer\n  1. ```text\n \tVISIBLE_SPACE_TAB\nafter',
      expected: '- outer VISIBLE SPACE TAB after',
      visible: 'VISIBLE_SPACE_TAB',
      outsideFence: 'VISIBLE_SPACE_TAB',
    },
    {
      name: 'nested-ordered-pure-tab-dedent-keeps-visible-token',
      source: '- outer\n  1. ```text\n\tVISIBLE_PURE_TAB\nafter',
      expected: '- outer VISIBLE PURE TAB after',
      visible: 'VISIBLE_PURE_TAB',
      outsideFence: 'VISIBLE_PURE_TAB',
    },
    {
      name: 'nested-blockquote-list-tab-dedent-keeps-visible-token',
      source: '> - outer\n>   1. ```text\n> \tVISIBLE_MIXED_TAB\n> after',
      expected: '- outer VISIBLE MIXED TAB after',
      visible: 'VISIBLE_MIXED_TAB',
      outsideFence: 'VISIBLE_MIXED_TAB',
    },
    {
      name: 'ordered-zero-does-not-interrupt-paragraph',
      source: 'before\n0. ```text\nVISIBLE_ZERO declare -p GRAFANA_PASSWORD',
      expected: 'before 0. ```text VISIBLE ZERO declare -p GRAFANA PASSWORD',
      visible: 'declare -p GRAFANA_PASSWORD',
    },
    {
      name: 'ordered-two-does-not-interrupt-paragraph',
      source: 'before\n2. ```text\nVISIBLE_TWO printenv',
      expected: 'before 2. ```text VISIBLE TWO printenv',
      visible: 'printenv',
    },
    {
      name: 'ordered-zero-is-valid-at-block-boundary',
      source: 'before\n\n0. ```text\n   HIDDEN_ZERO\n   ```\nafter',
      expected: 'before after',
      hidden: 'HIDDEN_ZERO',
    },
    {
      name: 'ordered-two-is-valid-at-block-boundary',
      source: 'before\n\n2. ```text\n   HIDDEN_TWO\n   ```\nafter',
      expected: 'before after',
      hidden: 'HIDDEN_TWO',
    },
    {
      name: 'ordered-two-after-heading-is-a-list-fence',
      source: 'before\n# heading boundary\n2. ```text\n   HIDDEN_AFTER_HEADING\n   ```\nafter',
      expected: 'before heading boundary after',
      hidden: 'HIDDEN_AFTER_HEADING',
    },
    {
      name: 'ordered-zero-after-thematic-break-is-a-list-fence',
      source: 'before\n***\n0. ```text\n   HIDDEN_AFTER_THEMATIC\n   ```\nafter',
      expected: 'before after',
      hidden: 'HIDDEN_AFTER_THEMATIC',
    },
    {
      name: 'ordered-two-after-complete-html-block-is-a-list-fence',
      source: 'before\n<div>html boundary</div>\n\n2. ```text\n   HIDDEN_AFTER_HTML\n   ```\nafter',
      expected: 'before html boundary after',
      hidden: 'HIDDEN_AFTER_HTML',
    },
    {
      name: 'ordered-two-after-multiline-complete-html-block-is-a-list-fence',
      source: 'before\n<div>\nhtml boundary\n</div>\n\n2. ```text\n   HIDDEN_AFTER_MULTILINE_HTML\n   ```\nafter',
      expected: 'before html boundary after',
      hidden: 'HIDDEN_AFTER_MULTILINE_HTML',
    },
    {
      name: 'ordered-two-after-fenced-block-is-a-list-fence',
      source: 'before\n1. ```text\n   HIDDEN_FIRST_FENCE\n   ```\n2. ```text\n   HIDDEN_SECOND_FENCE\n   ```\nafter',
      expected: 'before after',
      hidden: 'HIDDEN_SECOND_FENCE',
    },
    {
      name: 'ten-digit-ordered-marker-is-prose',
      source: 'before\n1234567890. ```text\nVISIBLE_TEN declare -p GRAFANA_PASSWORD',
      expected: 'before 1234567890. ```text VISIBLE TEN declare -p GRAFANA PASSWORD',
      visible: 'declare -p GRAFANA_PASSWORD',
    },
    {
      name: 'nested-list-dedent-reprocesses-sibling',
      source: 'before\n- outer\n  - ```bash\n    declare -p GRAFANA_PASSWORD\n- sibling\nVISIBLE_SIBLING',
      expected: 'before - outer - sibling VISIBLE SIBLING',
      hidden: 'declare -p GRAFANA_PASSWORD',
      visible: 'VISIBLE_SIBLING',
    },
    {
      name: 'mixed-blockquote-list-dedent-reprocesses-prose',
      source: '> - outer\n>   - ```bash\n>     printenv\nVISIBLE_AFTER',
      expected: '- outer VISIBLE AFTER',
      hidden: 'printenv',
      visible: 'VISIBLE_AFTER',
    },
  ];
  for (const fixture of fixtures) {
    const rendered = (await satteriMarkdownProcessor.render(fixture.source)).code;
    assert.equal(toPlainText(fixture.source), fixture.expected, `Blume helper fixture failed: ${fixture.name}`);
    if (fixture.hidden !== undefined) {
      assert.match(rendered, new RegExp(`<pre[^>]*>[\\s\\S]*${fixture.hidden}[\\s\\S]*</pre>`, 'u'));
      assert.equal(toPlainText(fixture.source).includes(fixture.hidden), false);
    }
    if (fixture.visible !== undefined) assert.match(rendered, new RegExp(fixture.visible, 'u'));
    if (fixture.outsideFence !== undefined) {
      assert.doesNotMatch(rendered, new RegExp(`<pre[^>]*>[\\s\\S]*${fixture.outsideFence}[\\s\\S]*</pre>`, 'u'));
    }
  }
});

test('partial-tab continuation state preserves Search, Raw, reader, and renderer parity', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-partial-tab-'));
  try {
    const projectRoot = path.join(temporary, 'project');
    const sourcePath = path.join(projectRoot, 'src', 'content', 'docs', 'testing', 'partial-tab.md');
    const imagePath = path.join(projectRoot, 'src', 'content', 'docs', 'assets', 'partial-tab.png');
    const imageTarget = '../assets/partial-tab.png';
    await mkdir(path.dirname(sourcePath), { recursive: true });
    await mkdir(path.dirname(imagePath), { recursive: true });
    await writeFile(imagePath, 'image fixture', 'utf8');
    const logical = [
      '1. outer',
      '\tcontinued',
      '\t```md',
      '\t`declare -p GRAFANA_PASSWORD`',
      '\t<operation-id>',
      `\t![fenced](${imageTarget})`,
      '\t```',
      'after',
      `![outside](${imageTarget})`,
    ].join('\n');
    const outcome = contentMap['installation.md'].reader.outcome;
    for (const terminator of ['\n', '\r\n', '\r']) {
      const source = logical.replaceAll('\n', terminator);
      const openStart = source.indexOf('\t```md');
      const closeEnd = source.indexOf(`${terminator}after`);
      assert.deepEqual(markdownFenceRanges(source), [[openStart, closeEnd]], `partial-tab fence range drifted for ${JSON.stringify(terminator)}`);
      assert.equal(toPlainText(source), '1. outer continued after');
      assert.equal(placeholderInventory(source, { inlineCodeOnly: true }).total, 0);
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(source, `partial-tab-${JSON.stringify(terminator)}.md`));
      assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${source}`, {
        outcome,
        location: `partial-tab-${JSON.stringify(terminator)}.md`,
      }));

      const rewritten = await rewriteRelativeImageReferences({ source, sourcePath, projectRoot });
      assert.equal(rewritten, [
        '1. outer',
        '\tcontinued',
        '\t```md',
        '\t`declare -p GRAFANA_PASSWORD`',
        '\t<operation-id>',
        `\t![fenced](${imageTarget})`,
        '\t```',
        'after',
        '![outside](/blume-assets/content/src/content/docs/assets/partial-tab.png)',
      ].join(terminator));

      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      assert.match(rendered, /<pre[^>]*>[\s\S]*declare -p GRAFANA_PASSWORD[\s\S]*<\/pre>/u);
      assert.doesNotMatch(rendered, /<pre[^>]*>[\s\S]*after[\s\S]*<\/pre>/u);
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('multiline HTML block completion preserves Search, Raw, reader, and renderer parity', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-html-block-'));
  try {
    const projectRoot = path.join(temporary, 'project');
    const sourcePath = path.join(projectRoot, 'src', 'content', 'docs', 'testing', 'html-block.md');
    const imagePath = path.join(projectRoot, 'src', 'content', 'docs', 'assets', 'html-block.png');
    const imageTarget = '../assets/html-block.png';
    await mkdir(path.dirname(sourcePath), { recursive: true });
    await mkdir(path.dirname(imagePath), { recursive: true });
    await writeFile(imagePath, 'image fixture', 'utf8');
    const logical = [
      'before',
      '<div>',
      'html boundary',
      '</div>',
      '',
      '2. ```md',
      '   HIDDEN_HTML_BODY',
      '   <operation-id>',
      `   ![fenced](${imageTarget})`,
      '   ```',
      'after',
      `![outside](${imageTarget})`,
    ].join('\n');
    const outcome = contentMap['installation.md'].reader.outcome;
    for (const terminator of ['\n', '\r\n', '\r']) {
      const source = logical.replaceAll('\n', terminator);
      const openStart = source.indexOf('2. ```md');
      const closeEnd = source.indexOf(`${terminator}after`);
      assert.deepEqual(markdownFenceRanges(source), [[openStart, closeEnd]], `multiline HTML fence range drifted for ${JSON.stringify(terminator)}`);
      assert.equal(toPlainText(source), 'before html boundary after');
      assert.equal(placeholderInventory(source, { inlineCodeOnly: true }).total, 0);
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(source, `html-block-${JSON.stringify(terminator)}.md`));
      assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${source}`, {
        outcome,
        location: `html-block-${JSON.stringify(terminator)}.md`,
      }));

      const rewritten = await rewriteRelativeImageReferences({ source, sourcePath, projectRoot });
      assert.equal(rewritten, [
        'before',
        '<div>',
        'html boundary',
        '</div>',
        '',
        '2. ```md',
        '   HIDDEN_HTML_BODY',
        '   <operation-id>',
        `   ![fenced](${imageTarget})`,
        '   ```',
        'after',
        '![outside](/blume-assets/content/src/content/docs/assets/html-block.png)',
      ].join(terminator));

      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      assert.match(rendered, /<pre[^>]*>[\s\S]*HIDDEN_HTML_BODY[\s\S]*<\/pre>/u);
      assert.doesNotMatch(rendered, /<pre[^>]*>[\s\S]*after[\s\S]*<\/pre>/u);
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('patched Blume plain-text line semantics match Satteri for LF, CRLF, and lone CR', async () => {
  const logical = [
    '# visible title',
    'Visible `<command>`.',
    '```text',
    '# hidden title',
    'HIDDEN_CR_BODY',
    '```',
    '# after',
  ].join('\n');
  for (const terminator of ['\n', '\r\n', '\r']) {
    const source = logical.replaceAll('\n', terminator);
    const plain = toPlainText(source);
    const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
    const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
    assert.match(plain, /visible title/u);
    assert.match(plain, /after/u);
    assert.doesNotMatch(plain, /#\s+(?:visible title|after)/u);
    assert.doesNotMatch(plain, /HIDDEN_CR_BODY/u);
    assert.match(rendered, /<pre[^>]*>[\s\S]*HIDDEN_CR_BODY[\s\S]*<\/pre>/u);
    assert.doesNotMatch(rendered, /<pre[^>]*>[\s\S]*after[\s\S]*<\/pre>/u);
  }
});

test('Search fence boundaries match Satteri and an independent reader scanner', async () => {
  const fixtures = [
    {
      name: 'top-level thematic break',
      logical: 'before\n* * *\n2. ```text\n   TOKEN123\n   ```\nafter',
      expected: 'before after',
    },
    {
      name: 'list thematic break',
      logical: 'before\n- item\n  * * *\n  2. ```text\n     TOKEN123\n     ```\nafter',
      expected: 'before - item after',
    },
    {
      name: 'blockquote thematic break',
      logical: 'before\n> * * *\n> 2. ```text\n>    TOKEN123\n>    ```\n> after',
      expected: 'before after',
    },
    {
      name: 'incomplete type-six slash prefix remains paragraph',
      logical: '<div/x\n- ```text\n  TOKEN123\n  ```\nafter',
      expected: '<div/x after',
    },
    {
      name: 'incomplete type-six slash prefix in blockquote',
      logical: '> <div/x\n> - ```text\n>   TOKEN123\n>   ```\n> after',
      expected: '<div/x after',
    },
  ];
  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const fixture of fixtures) {
      const source = fixture.logical.replaceAll('\n', terminator);
      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      assert.deepEqual(fenceRanges(source), markdownFenceRanges(source), `${fixture.name} scanner parity drifted for ${JSON.stringify(terminator)}`);
      assert.equal(toPlainText(source), fixture.expected, `${fixture.name} Search text drifted for ${JSON.stringify(terminator)}`);
      assert.match(rendered, /<pre[^>]*>[\s\S]*TOKEN123[\s\S]*<\/pre>/u, `${fixture.name} must render TOKEN123 as a code block`);
      assert.doesNotMatch(toPlainText(source), /TOKEN123/u, `${fixture.name} leaked its fenced token into Search`);
    }
  }
});

test('Setext underlines close the paragraph before top-level, list, and blockquote fences', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-setext-boundary-'));
  try {
    const projectRoot = path.join(temporary, 'project');
    const sourcePath = path.join(projectRoot, 'src', 'content', 'docs', 'setext.md');
    const imagePath = path.join(projectRoot, 'src', 'content', 'docs', 'assets', 'setext.png');
    await mkdir(path.dirname(sourcePath), { recursive: true });
    await mkdir(path.dirname(imagePath), { recursive: true });
    await writeFile(imagePath, 'image fixture', 'utf8');
    const marker = '```';
    const fixtures = [
      {
        name: 'top-level',
        logical: [
          'before',
          'UNDERLINE',
          `2. ${marker}text`,
          '   HIDDEN_SETEXT',
          '   ![fenced](assets/setext.png)',
          `   ${marker}`,
          'after',
          '![outside](assets/setext.png)',
        ].join('\n'),
        expected: 'before after',
        searchExpected: 'before after',
      },
      {
        name: 'list',
        logical: [
          '- before',
          '  UNDERLINE',
          `  2. ${marker}text`,
          '     HIDDEN_SETEXT',
          '     ![fenced](assets/setext.png)',
          `     ${marker}`,
          'after',
          '![outside](assets/setext.png)',
        ].join('\n'),
        expected: 'before after',
        searchExpected: '- before after',
      },
      {
        name: 'blockquote',
        logical: [
          '> before',
          '> UNDERLINE',
          `> 2. ${marker}text`,
          '>    HIDDEN_SETEXT',
          '>    ![fenced](assets/setext.png)',
          `>    ${marker}`,
          '> after',
          '![outside](assets/setext.png)',
        ].join('\n'),
        expected: 'before after',
        searchExpected: 'before after',
      },
    ];
    for (const terminator of ['\n', '\r\n', '\r']) {
      for (const fixture of fixtures) {
        for (const underline of ['--', '==']) {
          const source = fixture.logical.replaceAll('UNDERLINE', underline).replaceAll('\n', terminator);
          const openLine = fixture.name === 'top-level'
            ? `2. ${marker}text`
            : fixture.name === 'list' ? `  2. ${marker}text` : `> 2. ${marker}text`;
          const openStart = source.indexOf(openLine);
          const closeLine = `${fixture.name === 'top-level' ? '   ' : fixture.name === 'list' ? '     ' : '>    '}${marker}`;
          const closeEnd = source.indexOf(closeLine) + closeLine.length;
          assert.deepEqual(fenceRanges(source), [[openStart, closeEnd]], `${fixture.name} production range drifted for ${JSON.stringify({ underline, terminator })}`);
          assert.deepEqual(markdownFenceRanges(source), [[openStart, closeEnd]], `${fixture.name} reader range drifted for ${JSON.stringify({ underline, terminator })}`);
          const expected = fixture.expected;
          assert.equal(toPlainText(source), fixture.searchExpected, `${fixture.name} Search drifted for ${JSON.stringify({ underline, terminator })}`);

          const rendered = (await satteriMarkdownProcessor.render(source.replace(/\r\n?|\n/gu, '\n'))).code;
          const dom = new JSDOM(`<main>${rendered}</main>`);
          try {
            const root = dom.window.document.querySelector('main');
            const walker = root.ownerDocument.createTreeWalker(root, dom.window.NodeFilter.SHOW_COMMENT);
            const comments = [];
            while (walker.nextNode()) comments.push(walker.currentNode);
            for (const comment of comments) comment.remove();
            for (const element of root.querySelectorAll('pre, script, style, template')) element.remove();
            assert.equal((root.textContent ?? '').replace(/\s+/gu, ' ').trim(), expected, `${fixture.name} DOM reader text drifted for ${JSON.stringify({ underline, terminator })}`);
          } finally {
            dom.window.close();
          }
          const preBlocks = [...rendered.matchAll(/<pre[^>]*>[\s\S]*?<\/pre>/gu)].map(([block]) => block).join('\n');
          assert.match(preBlocks, /HIDDEN_SETEXT/u, `${fixture.name} renderer did not retain the fenced body for ${JSON.stringify({ underline, terminator })}`);
          assert.doesNotMatch(preBlocks, /(?:^|\n)after(?:\n|$)/u, `${fixture.name} renderer fence consumed following prose for ${JSON.stringify({ underline, terminator })}`);

          const rewritten = await rewriteRelativeImageReferences({ source, sourcePath, projectRoot });
          assert.match(rewritten, /!\[fenced\]\(assets\/setext\.png\)/u, `${fixture.name} rewrote a fenced image for ${JSON.stringify({ underline, terminator })}`);
          assert.match(rewritten, /!\[outside\]\(\/blume-assets\/content\/src\/content\/docs\/assets\/setext\.png\)/u, `${fixture.name} did not rewrite the outside image for ${JSON.stringify({ underline, terminator })}`);
        }
      }

      for (const [name, underline] of [['standalone-dash', '--'], ['standalone-equals', '==']]) {
        const source = [
          underline,
          `2. ${marker}text`,
          `HIDDEN_${name.toUpperCase()}`,
          marker,
          'after',
        ].join(terminator);
        const openStart = source.indexOf(`2. ${marker}text`);
        assert.notEqual(fenceRanges(source)[0]?.[0], openStart, `${name} unexpectedly interrupted a paragraph in production Search`);
        assert.notEqual(markdownFenceRanges(source)[0]?.[0], openStart, `${name} unexpectedly interrupted a paragraph in reader scanner`);
        const visibleControl = `HIDDEN_${name.toUpperCase()}`.replace('_', ' ');
        assert.match(toPlainText(source), new RegExp(visibleControl, 'u'), `${name} disappeared from Search`);
      }
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('Search normalizes list continuation before Setext and keeps empty markers block-visible', async () => {
  const fixtures = [
    {
      name: 'list setext dedent remains prose',
      logical: '- alpha\n--\n  2. ```text\nTOKEN_DEDENT\n```\nafter',
      token: 'TOKEN_DEDENT',
      fenced: false,
    },
    ...[0, 1, 2, 3].map((relative) => ({
      name: `list setext relative ${relative}`,
      logical: `- alpha\n${' '.repeat(2 + relative)}--\n  2. \`\`\`text\n     TOKEN_RELATIVE_${relative}\n     \`\`\`\nafter`,
      token: `TOKEN_RELATIVE_${relative}`,
      fenced: true,
    })),
    {
      name: 'tab continuation setext',
      logical: '- alpha\n\t--\n  2. ```text\n     TOKEN_TAB\n     ```\nafter',
      token: 'TOKEN_TAB',
      fenced: true,
    },
    {
      name: 'blockquote relative continuation setext',
      logical: '> - alpha\n>   --\n>   2. ```text\n>      TOKEN_BLOCKQUOTE\n>      ```\n> after',
      token: 'TOKEN_BLOCKQUOTE',
      fenced: true,
    },
    {
      name: 'nested list fence remains masked',
      logical: '- outer\n  1. inner\n    ```text\n    TOKEN_NESTED\n    ```\nafter',
      token: 'TOKEN_NESTED',
      fenced: true,
    },
  ];
  const controls = '--\nVISIBLE_DASH\n==\nVISIBLE_EQUALS\nafter';
  for (const terminator of ['\n', '\r\n', '\r']) {
    assert.match(toPlainText(controls.replaceAll('\n', terminator)), /VISIBLE DASH[\s\S]*VISIBLE EQUALS/u);
    for (const fixture of fixtures) {
      const source = fixture.logical.replaceAll('\n', terminator);
      const rendered = (await satteriMarkdownProcessor.render(source.replace(/\r\n?|\n/gu, '\n'))).code;
      assert.deepEqual(fenceRanges(source), markdownFenceRanges(source), `${fixture.name} scanner parity drifted for ${JSON.stringify(terminator)}`);
      const plain = toPlainText(source);
      const tokenStart = source.indexOf(fixture.token);
      const inRange = fenceRanges(source).some(([start, end]) => tokenStart >= start && tokenStart < end);
      assert.equal(inRange, fixture.fenced, `${fixture.name} range visibility drifted for ${JSON.stringify(terminator)}`);
      const searchableForms = [fixture.token, fixture.token.replaceAll('_', ' ')];
      assert.equal(searchableForms.some((value) => plain.includes(value)), !fixture.fenced, `${fixture.name} Search visibility drifted for ${JSON.stringify(terminator)}`);
      const preBlocks = [...rendered.matchAll(/<pre[^>]*>[\s\S]*?<\/pre>/gu)].map(([block]) => block).join('\n');
      assert.equal(preBlocks.includes(fixture.token), fixture.fenced, `${fixture.name} Satteri fence visibility drifted for ${JSON.stringify(terminator)}`);
    }
    for (const marker of ['-', '+', '*', '1.']) {
      const token = `TOKEN_EMPTY_${marker.replace('.', 'ORDER')}`;
      const source = `# alpha\n${marker}\n2. \`\`\`text\n   ${token}\n   \`\`\`\nafter`.replaceAll('\n', terminator);
      assert.deepEqual(fenceRanges(source), markdownFenceRanges(source), `empty ${marker} scanner parity drifted for ${JSON.stringify(terminator)}`);
      const ranges = fenceRanges(source);
      const tokenStart = source.indexOf(token);
      assert.equal(ranges.some(([start, end]) => tokenStart >= start && tokenStart < end), true, `empty ${marker} did not mask its fence body`);
      assert.doesNotMatch(toPlainText(source), new RegExp(token, 'u'), `empty ${marker} leaked its fence body`);
    }
  }
});

test('Search removes complete comments and non-reader HTML content against a DOM oracle', async () => {
  const fixtures = [
    ['complete comment with an early angle bracket', '<!-- hidden > TOKEN123 -->\nafter', 'after'],
    ['multiline complete comment', '<!-- hidden\n> TOKEN123\n-->\nafter', 'after'],
    ['script content', '<script>hidden > TOKEN123</script>after', 'after'],
    ['style content', '<style>hidden > TOKEN123</style>after', 'after'],
    ['template content', '<template>hidden > TOKEN123</template>after', 'after'],
    ['ordinary HTML prose', '<p>visible prose</p>', 'visible prose'],
  ];
  const domVisibleText = (html) => {
    const dom = new JSDOM(`<main>${html}</main>`);
    try {
      const root = dom.window.document.querySelector('main');
      const walker = root.ownerDocument.createTreeWalker(root, dom.window.NodeFilter.SHOW_COMMENT);
      const comments = [];
      while (walker.nextNode()) comments.push(walker.currentNode);
      for (const comment of comments) comment.remove();
      for (const element of root.querySelectorAll('script, style, template')) element.remove();
      return (root.textContent ?? '').replace(/\s+/gu, ' ').trim();
    } finally {
      dom.window.close();
    }
  };
  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const [name, logical, expected] of fixtures) {
      const source = logical.replaceAll('\n', terminator);
      const rendered = (await satteriMarkdownProcessor.render(source.replace(/\r\n?|\n/gu, '\n'))).code;
      assert.equal(toPlainText(source), expected, `${name} Search text drifted for ${JSON.stringify(terminator)}`);
      assert.equal(domVisibleText(rendered), expected, `${name} DOM reader text drifted for ${JSON.stringify(terminator)}`);
    }
  }
});

test('Search excludes script and style raw bodies opened at physical line endings', async () => {
  const domVisibleText = (html) => {
    const dom = new JSDOM(`<main>${html}</main>`);
    try {
      const root = dom.window.document.querySelector('main');
      const walker = root.ownerDocument.createTreeWalker(root, dom.window.NodeFilter.SHOW_COMMENT);
      const comments = [];
      while (walker.nextNode()) comments.push(walker.currentNode);
      for (const comment of comments) comment.remove();
      for (const element of root.querySelectorAll('script, style, template')) element.remove();
      return (root.textContent ?? '').replace(/\s+/gu, ' ').trim();
    } finally {
      dom.window.close();
    }
  };
  const fixtures = [
    ['script-name-line-ending-without-attributes', '<script\n>HIDDEN_SCRIPT</script>\nafter', 'after'],
    ['script-name-line-ending-with-attributes', '<script\n type="application/javascript">HIDDEN_SCRIPT</script>\nafter', 'after'],
    ['style-name-line-ending-without-attributes', '<style\n>HIDDEN_STYLE</style>\nafter', 'after'],
    ['style-name-line-ending-with-attributes', '<style\n media="screen">HIDDEN_STYLE</style>\nafter', 'after'],
    ['script-name-line-ending-without-standalone-terminator', '<script\nTOKEN_SCRIPT\n</script>\nafter', ''],
    ['script-name-line-ending-with-attributes-without-standalone-terminator', '<script\n type="application/javascript"\nTOKEN_SCRIPT_ATTRIBUTE\n</script>\nafter', ''],
    ['style-name-line-ending-without-standalone-terminator', '<style\nTOKEN_STYLE\n</style>\nafter', ''],
    ['style-name-line-ending-with-attributes-without-standalone-terminator', '<style\n media="screen"\nTOKEN_STYLE_ATTRIBUTE\n</style>\nafter', ''],
    ['top-level-versus-blockquote-raw-opener-terminator', '> <script\n> >\n> HIDDEN_BLOCKQUOTE_SCRIPT\n> </script>\n> after', 'after'],
    ['same-line-template-inert', '<template>HIDDEN_TEMPLATE</template>\nafter', 'after'],
  ];
  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const [name, logical, expected] of fixtures) {
      const source = logical.replaceAll('\n', terminator);
      const rendered = (await satteriMarkdownProcessor.render(source.replace(/\r\n?|\n/gu, '\n'))).code;
      assert.equal(toPlainText(source), expected, `${name} Search drifted for ${JSON.stringify(terminator)}`);
      assert.equal(domVisibleText(rendered), expected, `${name} DOM-visible reader text drifted for ${JSON.stringify(terminator)}`);
    }

    const multilineTemplate = '<template\n>VISIBLE_TEMPLATE</template>\nafter'.replaceAll('\n', terminator);
    const multilineTemplateRendered = (await satteriMarkdownProcessor.render(multilineTemplate.replace(/\r\n?|\n/gu, '\n'))).code;
    assert.equal(toPlainText(multilineTemplate), 'VISIBLE TEMPLATE after', `multiline template Search drifted for ${JSON.stringify(terminator)}`);
    assert.match(domVisibleText(multilineTemplateRendered), /VISIBLE_TEMPLATE/u, `multiline template became inert for ${JSON.stringify(terminator)}`);
    assert.match(domVisibleText(multilineTemplateRendered), /after/u, `multiline template lost following prose for ${JSON.stringify(terminator)}`);
  }
});

test('Search masks incomplete CommonMark HTML blocks with an independent reader oracle', async () => {
  const domVisibleText = (html) => {
    const dom = new JSDOM(`<main>${html}</main>`);
    try {
      const root = dom.window.document.querySelector('main');
      const walker = root.ownerDocument.createTreeWalker(root, dom.window.NodeFilter.SHOW_COMMENT);
      const comments = [];
      while (walker.nextNode()) comments.push(walker.currentNode);
      for (const comment of comments) comment.remove();
      for (const element of root.querySelectorAll('script, style, template')) element.remove();
      return (root.textContent ?? '').replace(/\s+/gu, ' ').trim();
    } finally {
      dom.window.close();
    }
  };
  const fixtures = [
    ['script-no-attributes', '<script\n>\nTOKEN_SCRIPT\n</script>\nafter', 'after'],
    ['script-with-attributes', '<script\n type="application/javascript">\nTOKEN_SCRIPT\n</script>\nafter', 'after'],
    ['style-no-attributes', '<style\n>\nTOKEN_STYLE\n</style>\nafter', 'after'],
    ['style-with-attributes', '<style\n media="screen">\nTOKEN_STYLE\n</style>\nafter', 'after'],
    ['processing-incomplete', '<?pi\nTOKEN_PROCESSING\n?>\nafter', 'after'],
    ['declaration-incomplete', '<!DOCTYPE\nTOKEN_DECLARATION\n>\nafter', 'after'],
    ['cdata-incomplete', '<![CDATA[\nTOKEN_CDATA\n]]>\nafter', 'after'],
    ['type-six-incomplete-with-blank', '<div\nTOKEN_DIV\n\n</div>\nafter', 'after'],
    ['list-type-six-incomplete-with-blank', '- <div\n  TOKEN_DIV_LIST\n\n- after', '- after', 'after'],
    ['blockquote-processing-incomplete', '> <?pi\n> TOKEN_PROCESSING_QUOTE\n> ?>\n> after', 'after'],
  ];
  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const [name, logical, expected, domExpected = expected] of fixtures) {
      const source = logical.replaceAll('\n', terminator);
      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      assert.equal(toPlainText(source), expected, `${name} Search visibility drifted for ${JSON.stringify(terminator)}`);
      assert.equal(domVisibleText(rendered), domExpected, `${name} DOM visibility drifted for ${JSON.stringify(terminator)}`);
      const range = markdownHtmlBlockRanges(source);
      const token = logical.match(/TOKEN_[A-Z_]+/u)?.[0];
      assert.ok(token !== undefined && range.some(([start, end]) => {
        const tokenStart = source.indexOf(token);
        return tokenStart >= start && tokenStart < end;
      }), `${name} reader oracle did not mask its incomplete block`);
    }
  }
  for (const [name, logical, expected, domPattern] of [
    ['same-line-template', '<template>TOKEN_TEMPLATE</template>after', 'after', /^after$/u],
    ['multiline-template-control', '<template\n>VISIBLE_TEMPLATE</template>\nafter', 'VISIBLE TEMPLATE after', /VISIBLE_TEMPLATE[\s\S]*after/u],
    ['ordinary-html-control', '<div>VISIBLE_DIV</div>', 'VISIBLE DIV', /^VISIBLE_DIV$/u],
    ['inline-code-control', '`<script\nTOKEN_INLINE</script>` after', '<script TOKEN_INLINE</script> after', /TOKEN_INLINE[\s\S]*after/u],
  ]) {
    const rendered = (await satteriMarkdownProcessor.render(logical)).code;
    assert.equal(toPlainText(logical), expected, `${name} Search control drifted`);
    assert.match(domVisibleText(rendered), domPattern, `${name} DOM control drifted`);
  }
});

test('Search correction matrix follows Satteri and JSDOM reader visibility', async () => {
  const domVisibleText = (html) => {
    const dom = new JSDOM(`<main>${html}</main>`);
    try {
      const root = dom.window.document.querySelector('main');
      const walker = root.ownerDocument.createTreeWalker(root, dom.window.NodeFilter.SHOW_COMMENT);
      const comments = [];
      while (walker.nextNode()) comments.push(walker.currentNode);
      for (const comment of comments) comment.remove();
      for (const element of root.querySelectorAll('script, style, template')) element.remove();
      return (root.textContent ?? '').replace(/\s+/gu, ' ').trim();
    } finally {
      dom.window.close();
    }
  };
  const fixtures = [
    {
      name: 'blockquote script quote-aware opener',
      logical: '> <script\n> type="data > value">\n> HIDDEN_RAW_SCRIPT\n> </script>\n> after',
      token: 'HIDDEN_RAW_SCRIPT',
      searchIncludes: ['after'],
      searchExcludes: ['HIDDEN RAW SCRIPT'],
      domIncludes: ['after'],
      domExcludes: ['HIDDEN_RAW_SCRIPT'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'blockquote style quote-aware opener',
      logical: '> - <style\n>     media="screen > value">\n>     HIDDEN_RAW_STYLE\n>   </style>\n> after',
      token: 'HIDDEN_RAW_STYLE',
      searchIncludes: ['after'],
      searchExcludes: ['HIDDEN RAW STYLE'],
      domIncludes: ['after'],
      domExcludes: ['HIDDEN_RAW_STYLE'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'nested blockquote script quote-aware opener',
      logical: '> > <script\n> > data-x="NESTED > MARK">\n> > HIDDEN_NESTED_RAW\n> > </script>\n> after',
      token: 'HIDDEN_NESTED_RAW',
      searchIncludes: ['after'],
      searchExcludes: ['HIDDEN NESTED RAW'],
      domIncludes: ['after'],
      domExcludes: ['HIDDEN_NESTED_RAW'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'top-level script quote-aware attribute with unfinished opener',
      logical: '<script\n data-x="EARLY > MARK"\nHIDDEN_TOP_RAW\n</script>\nafter',
      token: 'HIDDEN_TOP_RAW',
      searchIncludes: [],
      searchExcludes: ['HIDDEN TOP RAW', 'after'],
      domIncludes: [],
      domExcludes: ['HIDDEN_TOP_RAW', 'after'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'top-level quote-aware attribute spans physical lines',
      logical: '<script\n data-x="EARLY\n > </script> STILL">\nHIDDEN_MULTILINE_QUOTE\n</script>\nafter',
      token: 'HIDDEN_MULTILINE_QUOTE',
      searchIncludes: ['after'],
      searchExcludes: ['HIDDEN MULTILINE QUOTE'],
      domIncludes: ['after'],
      domExcludes: ['HIDDEN_MULTILINE_QUOTE'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'top-level starter quote spans physical lines',
      logical: '<script data-x="EARLY\n > </script> STILL">\nHIDDEN_START_QUOTE\n</script>\nafter',
      token: 'HIDDEN_START_QUOTE',
      searchIncludes: ['after'],
      searchExcludes: ['HIDDEN START QUOTE'],
      domIncludes: ['after'],
      domExcludes: ['HIDDEN_START_QUOTE'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'type-6 starter quote spans physical lines',
      logical: '<div data-kind="EARLY\n > </div> STILL">\nVISIBLE_TYPE6_START\n</div>\nafter',
      token: 'VISIBLE_TYPE6_START',
      searchIncludes: ['VISIBLE TYPE6 START', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_TYPE6_START', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: false,
    },
    {
      name: 'blockquote raw opener without an unquoted close angle',
      logical: '> <script\n> HIDDEN_RAW_UNTERMINATED\n> </script>\n> after',
      token: 'HIDDEN_RAW_UNTERMINATED',
      searchIncludes: [],
      searchExcludes: ['HIDDEN RAW UNTERMINATED', 'after'],
      domIncludes: [],
      domExcludes: ['HIDDEN_RAW_UNTERMINATED', 'after'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'multiline div body remains reader-visible',
      logical: '<div\n data-kind="visible">\nVISIBLE_DIV_BODY\n</div>\nafter',
      token: 'VISIBLE_DIV_BODY',
      searchIncludes: ['VISIBLE DIV BODY', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_DIV_BODY', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: false,
    },
    {
      name: 'type-6 completion line preserves visible tail',
      logical: '<div\n data-kind="probe">VISIBLE_COMPLETION\nVISIBLE_NEXT\n\n</div>\nafter',
      token: 'VISIBLE_COMPLETION',
      searchIncludes: ['VISIBLE COMPLETION', 'VISIBLE NEXT', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_COMPLETION', 'VISIBLE_NEXT', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: false,
    },
    {
      name: 'non-inert raw pre completion line preserves visible tail',
      logical: '<pre\n data-kind="probe">VISIBLE_PRE_COMPLETION\nVISIBLE_PRE_NEXT\n</pre>\nafter',
      token: 'VISIBLE_PRE_COMPLETION',
      searchIncludes: ['VISIBLE PRE COMPLETION', 'VISIBLE PRE NEXT', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_PRE_COMPLETION', 'VISIBLE_PRE_NEXT', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: true,
    },
    {
      name: 'multiline pre body remains reader-visible',
      logical: '<pre\n data-kind="visible">\nVISIBLE_PRE_BODY\n</pre>\nafter',
      token: 'VISIBLE_PRE_BODY',
      searchIncludes: ['VISIBLE PRE BODY', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_PRE_BODY', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: true,
    },
    {
      name: 'multiline textarea body remains reader-visible',
      logical: '<textarea\n data-kind="visible">\nVISIBLE_TEXTAREA_BODY\n</textarea>\nafter',
      token: 'VISIBLE_TEXTAREA_BODY',
      searchIncludes: ['VISIBLE TEXTAREA BODY', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_TEXTAREA_BODY', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: false,
    },
    {
      name: 'list-contained multiline textarea body remains reader-visible',
      logical: '> - <textarea\n>     data-kind="visible">\n>     VISIBLE_NESTED_TEXTAREA\n>   </textarea>\n> after',
      token: 'VISIBLE_NESTED_TEXTAREA',
      searchIncludes: ['VISIBLE NESTED TEXTAREA', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_NESTED_TEXTAREA', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: false,
    },
    {
      name: 'blockquote-list type-6 completion line preserves visible tail',
      logical: '> - <div\n>     data-kind="probe">VISIBLE_NESTED_COMPLETION\n>     VISIBLE_NESTED_NEXT\n>   </div>\n> after',
      token: 'VISIBLE_NESTED_COMPLETION',
      searchIncludes: ['VISIBLE NESTED COMPLETION', 'VISIBLE NESTED NEXT', 'after'],
      searchExcludes: ['data kind'],
      domIncludes: ['VISIBLE_NESTED_COMPLETION', 'VISIBLE_NESTED_NEXT', 'after'],
      domExcludes: ['data-kind'],
      htmlMasksToken: false,
      preContainsToken: false,
    },
    {
      name: 'incomplete div control stays hidden',
      logical: '<div\nHIDDEN_INCOMPLETE_DIV\n\n</div>\nafter',
      token: 'HIDDEN_INCOMPLETE_DIV',
      searchIncludes: ['after'],
      searchExcludes: ['HIDDEN INCOMPLETE DIV'],
      domIncludes: ['after'],
      domExcludes: ['HIDDEN_INCOMPLETE_DIV'],
      htmlMasksToken: true,
      preContainsToken: false,
    },
    {
      name: 'lazy blockquote continuation interrupts ordered fence',
      logical: '> alpha\ntext\n2. ```text\nTOKEN_LAZY\n```\n> after',
      token: 'TOKEN_LAZY',
      searchIncludes: ['TOKEN LAZY'],
      searchExcludes: [],
      domIncludes: ['TOKEN_LAZY'],
      domExcludes: [],
      htmlMasksToken: false,
      preContainsToken: false,
      fenceOracle: true,
    },
    {
      name: 'empty unordered marker interrupts ordered fence',
      logical: '> alpha\n-\n2. ```text\nTOKEN_EMPTY\n```\n> after',
      token: 'TOKEN_EMPTY',
      searchIncludes: ['TOKEN EMPTY'],
      searchExcludes: [],
      domIncludes: ['TOKEN_EMPTY'],
      domExcludes: [],
      htmlMasksToken: false,
      preContainsToken: false,
      fenceOracle: true,
    },
    {
      name: 'lazy blockquote ordered fence with CommonMark body indent',
      logical: '> alpha\ntext\n2. ```text\n   TOKEN_LAZY_INDENTED\n   ```\n> after',
      token: 'TOKEN_LAZY_INDENTED',
      searchIncludes: ['alpha', 'text', 'after'],
      searchExcludes: ['TOKEN LAZY INDENTED'],
      domIncludes: ['alpha', 'text', 'after'],
      domExcludes: ['TOKEN_LAZY_INDENTED'],
      htmlMasksToken: false,
      preContainsToken: true,
      fenceOracle: true,
    },
    {
      name: 'empty unordered marker ordered fence with CommonMark body indent',
      logical: '> alpha\n-\n2. ```text\n   TOKEN_EMPTY_INDENTED\n   ```\n> after',
      token: 'TOKEN_EMPTY_INDENTED',
      searchIncludes: ['alpha', 'after'],
      searchExcludes: ['TOKEN EMPTY INDENTED'],
      domIncludes: ['alpha', 'after'],
      domExcludes: ['TOKEN_EMPTY_INDENTED'],
      htmlMasksToken: false,
      preContainsToken: true,
      fenceOracle: true,
    },
    {
      name: 'blank line interrupts blockquote before ordered fence',
      logical: '> alpha\n\n2. ```text\n   TOKEN_BLANK_LINE\n   ```\n> after',
      token: 'TOKEN_BLANK_LINE',
      searchIncludes: ['alpha', 'after'],
      searchExcludes: ['TOKEN BLANK LINE'],
      domIncludes: ['alpha', 'after'],
      domExcludes: ['TOKEN_BLANK_LINE'],
      htmlMasksToken: false,
      preContainsToken: true,
      fenceOracle: true,
    },
    {
      name: 'nested list blockquote empty marker starts fence',
      logical: '> - alpha\n>   -\n>   2. ```text\n>      TOKEN_NESTED_EMPTY\n>      ```\n> after',
      token: 'TOKEN_NESTED_EMPTY',
      searchIncludes: ['alpha', 'after'],
      searchExcludes: ['TOKEN NESTED EMPTY'],
      domIncludes: ['alpha', 'after'],
      domExcludes: ['TOKEN_NESTED_EMPTY'],
      htmlMasksToken: false,
      preContainsToken: true,
      fenceOracle: true,
    },
    {
      name: 'tabbed list continuation remains visible after a lazy interruption',
      logical: '> - alpha\n>\ttext\n>\t2. ```text\n>\tTOKEN_TAB_LAZY\n>\t```\n> after',
      token: 'TOKEN_TAB_LAZY',
      searchIncludes: ['TOKEN TAB LAZY'],
      searchExcludes: [],
      domIncludes: ['TOKEN_TAB_LAZY'],
      domExcludes: [],
      htmlMasksToken: false,
      preContainsToken: false,
      fenceOracle: true,
    },
  ];

  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const fixture of fixtures) {
      const source = fixture.logical.replaceAll('\n', terminator);
      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      const domText = fixture.fenceOracle ? (() => {
        const dom = new JSDOM(`<main>${rendered}</main>`);
        try {
          const root = dom.window.document.querySelector('main');
          for (const element of root.querySelectorAll('pre')) element.remove();
          return (root.textContent ?? '').replace(/\s+/gu, ' ').trim();
        } finally {
          dom.window.close();
        }
      })() : domVisibleText(rendered);
      const plain = toPlainText(source);
      for (const expected of fixture.searchIncludes) assert.match(plain, new RegExp(expected.replaceAll(' ', '[ _-]+'), 'u'), `${fixture.name} Search positive drifted for ${JSON.stringify(terminator)}`);
      for (const unexpected of fixture.searchExcludes) assert.doesNotMatch(plain, new RegExp(unexpected.replaceAll(' ', '[ _-]+'), 'u'), `${fixture.name} Search negative drifted for ${JSON.stringify(terminator)}`);
      for (const expected of fixture.domIncludes) assert.match(domText, new RegExp(expected.replaceAll(' ', '[ _-]+'), 'u'), `${fixture.name} DOM positive drifted for ${JSON.stringify(terminator)}`);
      for (const unexpected of fixture.domExcludes) assert.doesNotMatch(domText, new RegExp(unexpected.replaceAll(' ', '[ _-]+'), 'u'), `${fixture.name} DOM negative drifted for ${JSON.stringify(terminator)}`);

      const tokenStart = source.indexOf(fixture.token);
      assert.notEqual(tokenStart, -1, `${fixture.name} fixture token is missing`);
      const preContainsToken = [...rendered.matchAll(/<pre[^>]*>[\s\S]*?<\/pre>/gu)].some(([block]) => block.includes(fixture.token));
      assert.equal(preContainsToken, fixture.preContainsToken, `${fixture.name} Satteri pre oracle drifted for ${JSON.stringify(terminator)}`);
      if (fixture.fenceOracle) {
        const readerMasksToken = markdownFenceRanges(source).some(([start, end]) => tokenStart >= start && tokenStart < end);
        const productionMasksToken = fenceRanges(source).some(([start, end]) => tokenStart >= start && tokenStart < end);
        assert.equal(readerMasksToken, preContainsToken, `${fixture.name} reader fence visibility drifted for ${JSON.stringify(terminator)}`);
        assert.equal(productionMasksToken, preContainsToken, `${fixture.name} production fence visibility drifted for ${JSON.stringify(terminator)}`);
      } else {
        const readerMasksToken = markdownHtmlBlockRanges(source).some(([start, end]) => tokenStart >= start && tokenStart < end);
        assert.equal(readerMasksToken, fixture.htmlMasksToken, `${fixture.name} reader HTML-flow visibility drifted for ${JSON.stringify(terminator)}`);
      }
    }
  }
});

test('Search and reader fence scanners follow independent Satteri visibility for list and HTML edge cases', async () => {
  const fixtures = [
    {
      name: 'partial-tab visual surplus stays inline-visible',
      logical: '- outer\n\t  ```md\n\t  HIDDEN_TAB\n\t  ```\nafter',
      visible: 'HIDDEN_TAB',
      fenced: false,
    },
    {
      name: 'nested ancestor dedent recognizes fenced code',
      logical: '- outer\n  1. inner\n    ```md\n    HIDDEN_NEST\n    ```\nafter',
      visible: 'HIDDEN_NEST',
      fenced: true,
    },
    {
      name: 'empty ordered marker keeps the paragraph open',
      logical: 'before\n1.\n2. ```text\nHIDDEN_EMPTY\n```\nafter',
      visible: 'HIDDEN_EMPTY',
      fenced: false,
    },
    {
      name: 'empty unordered star keeps the paragraph open',
      logical: 'before\n*\n2. ```text\nHIDDEN_STAR\n```\nafter',
      visible: 'HIDDEN_STAR',
      fenced: false,
    },
    {
      name: 'empty unordered plus keeps the paragraph open',
      logical: 'before\n+\n2. ```text\nHIDDEN_PLUS\n```\nafter',
      visible: 'HIDDEN_PLUS',
      fenced: false,
    },
    {
      name: 'type six div does not end before an ordered fence',
      logical: 'before\n<div>html</div>\n2. ```text\nHIDDEN_DIV\n```\nafter',
      visible: 'HIDDEN_DIV',
      fenced: false,
    },
    {
      name: 'type seven span does not interrupt the paragraph',
      logical: 'before\n<span>inline</span>\n2. ```text\nHIDDEN_SPAN\n```\nafter',
      visible: 'HIDDEN_SPAN',
      fenced: false,
    },
    {
      name: 'type one pre ignores nested-looking tags',
      logical: '<pre>\n<div>nested-looking</div>\n</pre>\n2. ```text\nHIDDEN_PRE\n```\nafter',
      visible: 'HIDDEN_PRE',
      fenced: false,
    },
    {
      name: 'type one textarea ignores nested-looking tags',
      logical: '<textarea>\n<div>nested-looking</div>\n</textarea>\n2. ```text\nHIDDEN_TEXTAREA\n```\nafter',
      visible: 'HIDDEN_TEXTAREA',
      fenced: false,
    },
    {
      name: 'type six xmp is not raw type one',
      logical: '<xmp>\n<div>nested-looking</div>\n</xmp>\n2. ```text\nHIDDEN_XMP\n```\nafter',
      visible: 'HIDDEN_XMP',
      fenced: false,
    },
    {
      name: 'type two comment closes on its delimiter',
      logical: '<!-- comment -->\n2. ```text\nHIDDEN_COMMENT\n```\nafter',
      visible: 'HIDDEN_COMMENT',
      fenced: false,
    },
    {
      name: 'type three processing instruction closes on its delimiter',
      logical: '<?pi?>\n2. ```text\nHIDDEN_PROCESSING\n```\nafter',
      visible: 'HIDDEN_PROCESSING',
      fenced: false,
    },
    {
      name: 'type four declaration closes on its delimiter',
      logical: '<!DECL>\n2. ```text\nHIDDEN_DECLARATION\n```\nafter',
      visible: 'HIDDEN_DECLARATION',
      fenced: false,
    },
    {
      name: 'type five cdata closes on its delimiter',
      logical: '<![CDATA[x]]>\n2. ```text\nHIDDEN_CDATA\n```\nafter',
      visible: 'HIDDEN_CDATA',
      fenced: false,
    },
    ...['pre', 'script', 'style', 'textarea'].map((tag) => ({
      name: `type one same-line ${tag} close ends the raw block`,
      logical: `<${tag}>inline</${tag}>\n2. ${'```'}text\n   HIDDEN_SINGLE_${tag.toUpperCase()}\n   ${'```'}\nafter`,
      visible: `HIDDEN_SINGLE_${tag.toUpperCase()}`,
      fenced: true,
    })),
    ...['pre', 'script', 'style', 'textarea'].map((tag) => ({
      name: `type one incomplete ${tag} opener starts raw block`,
      logical: `<${tag}\nraw ${tag} body\n</${tag}>\n2. ${'```'}text\n   HIDDEN_AFTER_${tag.toUpperCase()}\n   ${'```'}\nafter`,
      visible: `HIDDEN_AFTER_${tag.toUpperCase()}`,
      fenced: true,
    })),
    {
      name: 'multiple fenced pre blocks stay independently searchable',
      logical: 'before\n```text\nHIDDEN_FIRST_BLOCK\n```\nmiddle\n```text\nHIDDEN_SECOND_BLOCK\n```\nafter',
      visible: 'HIDDEN_SECOND_BLOCK',
      fenced: true,
    },
  ];
  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const fixture of fixtures) {
      const source = fixture.logical.replaceAll('\n', terminator);
      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      const readerRanges = markdownFenceRanges(source);
      const searchRanges = fenceRanges(source);
      assert.deepEqual(searchRanges, readerRanges, `${fixture.name} scanner drifted for ${JSON.stringify(terminator)}`);
      const plain = toPlainText(source);
      const searchableForms = [fixture.visible, fixture.visible.replaceAll('_', ' ')];
      const renderedPreBlocks = [...rendered.matchAll(/<pre[^>]*>[\s\S]*?<\/pre>/gu)].map(([block]) => block);
      const fencedBody = renderedPreBlocks.some((block) => block.includes(fixture.visible));
      if (fixture.fenced) {
        assert.equal(fencedBody, true, `${fixture.name} was not a renderer fence`);
        assert.equal(searchableForms.some((value) => plain.includes(value)), false, `${fixture.name} leaked into Search`);
      } else {
        assert.equal(fencedBody, false, `${fixture.name} became an unexpected renderer fence`);
        assert.equal(searchableForms.some((value) => plain.includes(value)), true, `${fixture.name} disappeared from Search`);
      }
    }
  }
});

test('CommonMark HTML flow matrix keeps production Search, independent reader, and Satteri parity', async () => {
  const fence = (marker) => [
    `- ${marker}text`,
    '  HIDDEN_HTML_MATRIX',
    `  ${marker}`,
    'after',
  ].join('\n');
  const htmlMatrix = [
    ['type-2-comment', '<!-- complete -->'],
    ['type-3-processing', '<?complete?>'],
    ['type-4-declaration', '<!DECL>'],
    ['type-5-cdata', '<![CDATA[complete]]>'],
    ['type-6-div', '<div>'],
    ['type-6-frame', '<frame>'],
    ['type-6-search', '<search>'],
    ['type-6-incomplete-open-div', '<div', false, false],
    ['type-6-incomplete-close-div', '</div', false, false],
    ['type-6-incomplete-attribute-div', '<div foo', false, false],
    ['type-6-name-continuation', '<divider', true],
    ['type-7-span', '<span>'],
    ['type-7-name-colon', '<span:a>', true],
    ['type-7-name-underscore', '<span_a>', true],
  ];
  const fixtures = [];
  for (const [name, html, expectedFenced, expectedSearchVisible] of htmlMatrix) {
    for (const indent of ['', ' ', '  ', '   ', '    ']) {
      const type = name.slice(0, 6);
      fixtures.push({
        name: `${name}-${indent.length}-columns`,
        logical: `${indent}${html}\n${indent.length === 4 ? '\n' : ''}${fence('```')}`,
        visible: 'HIDDEN_HTML_MATRIX',
        fenced: (expectedFenced ?? (type === 'type-2' || type === 'type-3' || type === 'type-4' || type === 'type-5'))
          || indent.length === 4,
        searchVisible: expectedSearchVisible,
      });
    }
  }
  for (const [name, html, fenced, searchVisible] of [
    ['type-6-incomplete-open-div', '<div', false, false],
    ['type-6-incomplete-close-div', '</div', false, false],
    ['type-6-incomplete-attribute-div', '<div foo', false, false],
    ['type-6-name-continuation', '<divider', true],
    ['type-7-name-colon', '<span:a>', true],
    ['type-7-name-underscore', '<span_a>', true],
  ]) {
    fixtures.push(
      {
        name: `${name}-list-container`,
        logical: `${html}\n- ${'```'}text\n  HIDDEN_HTML_MATRIX\n  ${'```'}\nafter`,
        visible: 'HIDDEN_HTML_MATRIX',
        fenced,
        searchVisible,
      },
      {
        name: `${name}-blockquote-container`,
        logical: `> ${html}\n> - ${'```'}text\n>   HIDDEN_HTML_MATRIX\n>   ${'```'}\n> after`,
        visible: 'HIDDEN_HTML_MATRIX',
        fenced,
        searchVisible,
      },
    );
  }
  fixtures.push(
    {
      name: 'type-1-standard-same-line-close',
      logical: '<pre>inline</pre>\n2. ```text\n   HIDDEN_HTML_MATRIX\n   ```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: true,
    },
    {
      name: 'type-1-opener-without-close-angle',
      logical: '<pre\nraw body\n</pre>\n2. ```text\n   HIDDEN_HTML_MATRIX\n   ```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: true,
    },
    {
      name: 'type-1-incomplete-pre-prefix-parity',
      logical: '<pre\n\n2. ```text\n   HIDDEN_HTML_MATRIX\n   ```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
      searchVisible: false,
    },
    {
      name: 'type-6-incomplete-close-pre-prefix-parity',
      logical: '</pre\n\n2. ```text\n   HIDDEN_HTML_MATRIX\n   ```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: true,
    },
    {
      name: 'type-1-whitespace-close-is-not-a-close',
      logical: '<pre>inline</pre   >\n2. ```text\nHIDDEN_HTML_MATRIX\n```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
    },
    {
      name: 'type-7-complete-tag-remains-paragraph',
      logical: 'before\n<span data-kind="complete">\n2. ```text\nHIDDEN_HTML_MATRIX\n```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
    },
    {
      name: 'type-7-suffix-remains-paragraph',
      logical: 'before\n<span>suffix\n2. ```text\nHIDDEN_HTML_MATRIX\n```\nafter',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
    },
    ...[
      ['type-7-invalid-missing-attribute-name', '<span =bad>', true],
      ['type-7-invalid-missing-attribute-value', '<span a=>', true],
      ['type-7-invalid-quoted-value-separator', '<span a="x"junk>', true],
      ['type-7-invalid-self-closing-suffix', '<span / junk>', true],
      ['type-7-invalid-unquoted-slash-path', '<span a=b/c>', true],
      ['type-7-valid-attribute-punctuation', '<span :a_b.c-d=value>', false],
      ['type-7-valid-quoted-attributes', '<span a="x" b=value>', false],
      ['type-7-valid-unquoted-attribute', '<span a=value>', false],
      ['type-7-valid-unquoted-equals-transition', '<span a=b=c>', false],
      ['type-7-valid-self-closing', '<span/>', false],
      ['type-7-valid-self-closing-whitespace', '<span   />', false],
      ['type-7-valid-closing-whitespace', '</span   >', false],
    ].map(([name, tag, fenced]) => ({
      name: `${name}-list-fence`,
      logical: `${tag}\n- ${'```'}text\n  HIDDEN_HTML_MATRIX\n  ${'```'}\nafter`,
      visible: 'HIDDEN_HTML_MATRIX',
      fenced,
    })),
    {
      name: 'list-contained-type-6-suppresses-fence',
      logical: '- <div>\n  2. ```text\n     HIDDEN_HTML_MATRIX\n     ```\n  after',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
    },
    {
      name: 'blockquote-contained-type-6-suppresses-fence',
      logical: '> <div>\n> 2. ```text\n>    HIDDEN_HTML_MATRIX\n>    ```\n> after',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
    },
    {
      name: 'nested-container-type-6-suppresses-fence',
      logical: '> - <div>\n>   2. ```text\n>      HIDDEN_HTML_MATRIX\n>      ```\n>   after',
      visible: 'HIDDEN_HTML_MATRIX',
      fenced: false,
    },
  );

  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const fixture of fixtures) {
      const source = fixture.logical.replaceAll('\n', terminator);
      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      const readerRanges = markdownFenceRanges(source);
      const searchRanges = fenceRanges(source);
      assert.deepEqual(searchRanges, readerRanges, `${fixture.name} scanner drifted for ${JSON.stringify(terminator)}`);
      const plain = toPlainText(source);
      const renderedPreBlocks = [...rendered.matchAll(/<pre[^>]*>[\s\S]*?<\/pre>/gu)].map(([block]) => block);
      const fencedBody = renderedPreBlocks.some((block) => block.includes(fixture.visible));
      const searchableForms = [fixture.visible, fixture.visible.replaceAll('_', ' ')];
      assert.equal(fencedBody, fixture.fenced, `${fixture.name} renderer fence state drifted for ${JSON.stringify(terminator)}`);
      assert.equal(searchableForms.some((value) => plain.includes(value)), fixture.searchVisible ?? !fixture.fenced, `${fixture.name} Search visibility drifted for ${JSON.stringify(terminator)}`);
    }
  }
});

test('list-contained fence bodies preserve indentation until a true dedent', async () => {
  const fixtures = [
    {
      name: 'non-shell list fence keeps indented inline env hidden',
      logical: '- Example:\n    ```text\n        `env`\n    ```',
      visible: 'env',
      fenced: true,
      unsafe: false,
    },
    {
      name: 'shell list fence remains an executable diagnostic',
      logical: '- Example:\n    ```bash\n        `env`\n    ```',
      visible: 'env',
      fenced: true,
      unsafe: true,
    },
    {
      name: 'true list fence dedent exposes following prose',
      logical: '- Example:\n    ```text\n        HIDDEN_BODY\nVISIBLE_DEDENT',
      visible: 'VISIBLE_DEDENT',
      searchable: 'VISIBLE DEDENT',
      fenced: false,
      unsafe: false,
    },
  ];
  for (const terminator of ['\n', '\r\n', '\r']) {
    for (const fixture of fixtures) {
      const source = fixture.logical.replaceAll('\n', terminator);
      const rendererSource = source.replace(/\r\n?|\n/gu, '\n');
      const rendered = (await satteriMarkdownProcessor.render(rendererSource)).code;
      const readerRanges = markdownFenceRanges(source);
      const searchRanges = fenceRanges(source);
      assert.deepEqual(searchRanges, readerRanges, `${fixture.name} scanner drifted for ${JSON.stringify(terminator)}`);
      const plain = toPlainText(source);
      const renderedPreBlocks = [...rendered.matchAll(/<pre[^>]*>[\s\S]*?<\/pre>/gu)].map(([block]) => block);
      const fencedBody = renderedPreBlocks.some((block) => block.includes(fixture.visible));
      assert.equal(fencedBody, fixture.fenced, `${fixture.name} renderer visibility drifted for ${JSON.stringify(terminator)}`);
      const searchable = fixture.searchable ?? fixture.visible;
      assert.equal(plain.includes(searchable), !fixture.fenced, `${fixture.name} Search visibility drifted for ${JSON.stringify(terminator)}`);
      if (fixture.fenced) {
        assert.ok(readerRanges[0][1] > source.indexOf(fixture.visible), `${fixture.name} did not preserve its body`);
      } else {
        assert.ok(readerRanges[0][1] <= source.indexOf(fixture.visible), `${fixture.name} did not close at the dedent`);
      }
      if (fixture.unsafe) {
        assert.throws(() => assertNoUnsafeLgtmDiagnostics(source, `${fixture.name}.md`), /LGTM diagnostics/u);
      } else {
        assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(source, `${fixture.name}.md`));
      }
    }
  }
});

test('Search plain text removes only internal MDX reader-outcome comments', () => {
  const marker = '{/* blackops-reader-outcome: internal boundary */}';
  assert.equal(toPlainText(`before ${marker} after`), 'before after');
  assert.match(toPlainText(`inline \`${marker}\` after`), /blackops-reader-outcome: internal boundary/u);
  assert.equal(toPlainText('<Component data="{/* blackops-reader-outcome: attribute */}" />'), '');
});

test('all generated Search records contain no internal MDX reader-outcome marker', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-search-markers-'));
  try {
    const artifactDirectory = path.join(temporary, 'artifact');
    await writeSyntheticCompleteReaderArtifact({ artifactDirectory, contentMap });
    const records = JSON.parse(await readFile(path.join(artifactDirectory, 'blume-search.json'), 'utf8'));
    assert.equal(records.length, 40);
    for (const record of records) {
      assert.doesNotMatch(JSON.stringify(record), /\{\/\*\s*blackops-reader-outcome:/u, `Search marker leaked for ${record.route}`);
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('visible HTML inline-code inventory excludes non-reader boundaries exactly once', () => {
  const html = [
    '<main>',
    '<code>&lt;command&gt;</code>',
    '<code><code>&lt;operation-id&gt;</code></code>',
    '<pre><code>&lt;application-root&gt;</code></pre>',
    '<div hidden><code>&lt;command&gt;</code></div>',
    '<div aria-hidden="true"><code>&lt;command&gt;</code></div>',
    '<div inert><code>&lt;application-root&gt;</code></div>',
    '<div hidden style="display:block"><code>&lt;operation-id&gt;</code></div>',
    '<div style="visibility:hidden"><span style="visibility:visible"><code>&lt;application-root&gt;</code></span></div>',
    '<div style="display:none"><span style="display:block"><code>&lt;command&gt;</code></span></div>',
    '<div style="content-visibility:hidden"><code>&lt;operation-id&gt;</code></div>',
    '<details><summary><code>&lt;operation-id&gt;</code></summary><p><code>&lt;application-root&gt;</code></p></details>',
    '<details open><p><code>&lt;application-root&gt;</code></p></details>',
    '<dialog><code>&lt;command&gt;</code></dialog><dialog open><code>&lt;operation-id&gt;</code></dialog><dialog style="display:block"><code>&lt;command&gt;</code></dialog>',
    '<script type="application/ld+json">{"description":"&lt;command&gt;"}</script>',
    '</main>',
  ].join('');
  assert.deepEqual(
    extractVisibleInlineCodeInventory(html, 'visible-boundary-fixture'),
    placeholderInventory('<command> <command> <command> <operation-id> <operation-id> <operation-id> <operation-id> <application-root> <application-root> <application-root>', { inlineCodeOnly: false }),
  );
  assert.equal(
    extractVisibleInlineCodeInventory('<main><div hidden><code>command></code></div><script>command></script></main>', 'hidden-malformed-fixture').total,
    0,
  );
  assert.throws(
    () => extractVisibleInlineCodeInventory('<main><code>command></code></main>', 'visible-malformed-placeholder-fixture'),
    /dangling/,
  );
  assert.throws(
    () => extractVisibleInlineCodeInventory('<main><code>&lt;command&gt;</main>', 'malformed-html-fixture'),
    /HTML (?:closing tag does not match|reader surface has an unclosed|start tag)/,
  );
  assert.doesNotThrow(
    () => extractVisibleInlineCodeInventory('<main><code>&lt;command&gt;</code><p>command></p></main>', 'visible-prose-placeholder-fragment-fixture'),
  );
});

test('CSS visibility follows CSSStyleDeclaration cascade while aria-hidden and inert stay reader-visible', () => {
  const html = [
    '<main>',
    '<div style="display: none !important"><code>&lt;command&gt;</code></div>',
    '<div style="display:none!important;display:block"><code>&lt;operation-id&gt;</code></div>',
    '<div style="display:none;display:block !important"><code>&lt;application-root&gt;</code></div>',
    '<div style="visibility: hidden !important"><code>&lt;command&gt;</code></div>',
    '<div style="visibility: hidden !important"><span style="visibility: visible"><code>&lt;operation-id&gt;</code></span></div>',
    '<div style="visibility:hidden!important;visibility:visible"><code>&lt;application-root&gt;</code></div>',
    '<div style="visibility:hidden;visibility:visible !important"><code>&lt;command&gt;</code></div>',
    '<div style="content-visibility: hidden !important"><code>&lt;operation-id&gt;</code></div>',
    '<div style="content-visibility:hidden!important;content-visibility:visible"><code>&lt;application-root&gt;</code></div>',
    '<div style="content-visibility:hidden;content-visibility:visible !important"><code>&lt;command&gt;</code></div>',
    '<div aria-hidden="true"><code>&lt;operation-id&gt;</code></div>',
    '<div inert><code>&lt;application-root&gt;</code></div>',
    '<div hidden style="display:block"><code>&lt;command&gt;</code></div>',
    '<details><summary><code>&lt;operation-id&gt;</code></summary><p><code>&lt;application-root&gt;</code></p></details>',
    '<dialog><code>&lt;command&gt;</code></dialog><dialog open><code>&lt;operation-id&gt;</code></dialog><dialog style="display:block"><code>&lt;application-root&gt;</code></dialog>',
    '</main>',
  ].join('');
  assert.deepEqual(
    extractVisibleInlineCodeInventory(html, 'css-cascade-fixture'),
    placeholderInventory('<application-root> <operation-id> <command> <operation-id> <command> <operation-id> <application-root> <command> <operation-id> <application-root>'),
  );
});

test('reader visibility applies initial reset and only the first direct closed-details summary', () => {
  const html = [
    '<main>',
    '<div style="visibility:hidden"><span style="visibility:initial"><code>&lt;command&gt;</code></span></div>',
    '<div style="visibility:hidden"><span style="visibility:unset"><code>&lt;operation-id&gt;</code></span></div>',
    '<details><summary><code>&lt;application-root&gt;</code></summary><summary><code>&lt;operation-id&gt;</code></summary><p><code>&lt;command&gt;</code></p></details>',
    '</main>',
  ].join('');
  assert.deepEqual(
    extractVisibleInlineCodeInventory(html, 'visibility-initial-and-summary-fixture'),
    placeholderInventory('<command> <application-root>'),
  );
});

test('reader contract rejects duplicate outcomes, missing roles, and broken next pages', () => {
  const duplicate = structuredClone(contentMap);
  duplicate['core-concepts.md'].reader.outcome = duplicate['why-blackops.md'].reader.outcome;
  assert.throws(() => validateReaderContract(duplicate), /outcomes must be unique/);

  const missingRole = structuredClone(contentMap);
  delete missingRole['installation.md'].reader.roles.failure;
  assert.throws(() => validateReaderContract(missingRole), /role failure is missing/);

  const brokenNext = structuredClone(contentMap);
  brokenNext['installation.md'].reader.next = ['missing.md'];
  assert.throws(() => validateReaderContract(brokenNext), /next-page target is broken/);

  const selfNext = structuredClone(contentMap);
  selfNext['installation.md'].reader.next = ['installation.md'];
  assert.throws(() => validateReaderContract(selfNext), /must not be self-referential/);

  const missingTopicOwner = structuredClone(contentMap);
  delete missingTopicOwner['installation.md'].reader.topic;
  assert.throws(() => validateReaderContract(missingTopicOwner), /topic identity is missing/);

  const duplicateRecipeOwner = structuredClone(contentMap);
  duplicateRecipeOwner['mvp-sample.md'].reader.recipe = {
    ...duplicateRecipeOwner['installation.md'].reader.recipe,
    reference: 'mvp-sample.md',
  };
  assert.throws(() => validateReaderContract(duplicateRecipeOwner), /recipe identity owner is duplicated/);

  const wrongOwnerReference = structuredClone(contentMap);
  wrongOwnerReference['execution.md'].reader.recipe.reference = 'why-blackops.md';
  assert.throws(() => validateReaderContract(wrongOwnerReference), /recipe identity reference must resolve to its owner/);

  const nonSelfOwner = structuredClone(contentMap);
  nonSelfOwner['outbox.md'].reader.recipe.reference = 'execution.md';
  assert.throws(() => validateReaderContract(nonSelfOwner), /recipe identity owner must reference itself/);

  const missingOwner = structuredClone(contentMap);
  missingOwner['outbox.md'].reader.recipe.role = 'reference';
  assert.throws(() => validateReaderContract(missingOwner), /recipe identity has no owner/);

  const duplicateFullRecipe = structuredClone(contentMap);
  duplicateFullRecipe['execution.md'].reader.recipe = { ...duplicateFullRecipe['outbox.md'].reader.recipe, role: 'owner', reference: 'execution.md' };
  assert.throws(() => validateReaderContract(duplicateFullRecipe), /recipe identity owner is duplicated/);
});

test('source fixtures fail closed for protected payload decode and Stable main-only claims', () => {
  assert.doesNotThrow(() => assertNoProtectedDecode('SELECT sequence, event FROM blackops.journal;', 'positive-source'));
  assert.throws(() => assertNoProtectedDecode("SELECT convert_from(encoded_record, 'UTF8')::jsonb;", 'negative-source'), /Protected Blob/);
  assert.throws(() => assertNoProtectedDecode('event = \'retry.scheduled\'', 'negative-source'), /retry event/);
  assert.doesNotThrow(() => assertNoCurrentMainOnly('### Repository main Preview\nHistorical anchor only.', 'positive-source'));
  assert.throws(() => assertNoCurrentMainOnly('### prefix Repository main Preview', 'negative-source-preview-prefix'), /exact heading/);
  assert.throws(() => assertNoCurrentMainOnly('### Repository main Preview suffix', 'negative-source-preview-suffix'), /exact heading/);
  assert.throws(() => assertNoCurrentMainOnly('## Repository main Preview', 'negative-source-preview-h2'), /exact heading/);
  assert.throws(() => assertNoCurrentMainOnly('#### Repository main Preview', 'negative-source-preview-h4'), /exact heading/);
  assert.doesNotThrow(() => assertNoCurrentMainOnly('<h3 id="repository-main-preview"><a href="#repository-main-preview">Repository main Preview</a></h3>', 'positive-source-preview-html-h3'));
  assert.throws(() => assertNoCurrentMainOnly('<h2 id="repository-main-preview">Repository main Preview</h2>', 'negative-source-preview-html-h2'), /exact heading/);
  assert.throws(() => assertNoCurrentMainOnly('<h4 id="repository-main-preview">Repository main Preview</h4>', 'negative-source-preview-html-h4'), /exact heading/);
  for (const variant of ['### Repository  main Preview', '### repository main preview', '### Repository-main-Preview', '<h3 id="other">Repository&nbsp;main Preview</h3>', '<h3 id="REPOSITORY-MAIN-PREVIEW">Repository main Preview</h3>', '<a href="#REPOSITORY-MAIN-PREVIEW">Repository main Preview</a>']) {
    assert.throws(() => assertNoCurrentMainOnly(variant, `negative-source-preview-variant-${variant}`), /exact (?:heading|anchored unit)/);
  }
  assert.throws(() => assertNoCurrentMainOnly('Stable 1.2.0 is main-only here.', 'negative-source'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('公開済みStable 1.2.0（main）だけで利用できます。', 'negative-source'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('Stable 1.2.0はmainでは提供されない機能だけです。', 'negative-source-mainでは'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('Stable 1.2.0はmainのbuild:compileだけで利用できます。', 'negative-source-mainのbuild'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('Stable 1.2.0はmain Sourceだけを現行手順に使います。', 'negative-source-main-source'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('## Stableとmain', 'negative-source-stale-heading'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('## Stable／main境界', 'negative-source-stale-boundary'), /main-only/);
  assert.throws(() => assertNoCurrentMainOnly('Stableと`main`の差を確認してください。', 'negative-source-stale-body'), /main-only/);
});

test('LGTM diagnostics stay secret-safe on a forced health failure', async () => {
  const observability = await readFile(path.join(repositoryRoot, 'docs/guide/observability.md'), 'utf8');
  const outcome = contentMap['installation.md'].reader.outcome;
  assert.equal(normalizeArtifactVisibleText('&amp; &lt; &gt; &quot; &apos; &nbsp; &#x41; &#65;'), '& < > " \' \u00a0 A A');
  assert.match(
    normalizeArtifactVisibleText('<h2>Diagnostics</h2><pre><code>export -p</code></pre>'),
    /Diagnostics\s+export -p/u,
    'Visible block tags must preserve a boundary before Shell validation.',
  );
  const interactive = observability.match(/画面と実際のTelemetryを確認する場合[\s\S]*?```bash\n([\s\S]*?)\n```/u)?.[1];
  assert.ok(interactive, 'Interactive LGTM lane must remain extractable as a shell journey.');
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(interactive, 'interactive-lgtm-positive'));

  for (const unsafe of [
    'docker inspect "$LGTM" >&2',
    'docker inspect "${LGTM}" >&2',
    `docker inspect --format '{{json .Config.Env}}' "$LGTM"`,
    `docker inspect --format '{{json .}}' "\${LGTM}"`,
    `docker container inspect --format '{{.Config.Env}}' "\${LGTM}"`,
    `docker inspect --format '{{.State.Status}}' "$LGTM" --format '{{json .Config.Env}}'`,
    `docker inspect --format '{{.State.Status}}' "$LGTM"; docker inspect "$LGTM"`,
    `docker inspect --format '{{.State.Status}} $GRAFANA_PASSWORD' "$LGTM"`,
    'printf \'%s\\n\' "$GRAFANA_PASSWORD"',
    'echo "${GF_SECURITY_ADMIN_PASSWORD}"',
    'docker exec "$LGTM" env',
    'printenv GRAFANA_PASSWORD',
    `docker inspect ${'\\'}
  "$LGTM"`,
    `docker inspect ${'\\'}
  --format '{{json .Config.Env}}' ${'\\'}
  "$LGTM"`,
  ]) {
    assert.throws(() => assertNoUnsafeLgtmDiagnostics(unsafe, 'interactive-lgtm-negative'), /LGTM diagnostics/);
    assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${unsafe}`, { outcome, location: 'interactive-lgtm-negative-artifact' }), /LGTM diagnostics/);
  }

  for (const safe of [
    `docker inspect --format '{{.State.Status}}' "$LGTM"`,
    `docker inspect --format '{{.State.Status}}' "\${LGTM}"`,
    `docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}not-configured{{end}}' "\${LGTM}"`,
    `docker container inspect --format '{{.State.Status}}' "\${LGTM}"`,
    `<code>docker inspect --format '{{.State.Status}}' "$LGTM"</code><code>docker inspect --format '{{.State.Health.Status}}' "\${LGTM}"</code>`,
    'printf \'configured GRAFANA_PASSWORD is never printed\'',
    'GRAFANA_PASSWORD="${GRAFANA_PASSWORD-local-admin}"',
    'docker run --env GF_SECURITY_ADMIN_PASSWORD="$GRAFANA_PASSWORD" grafana/otel-lgtm:fixed',
    `docker inspect ${'\\'}
  --format '{{.State.Status}}' ${'\\'}
  "$LGTM"`,
  ]) {
    assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(safe, 'interactive-lgtm-braced-positive'));
    assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${safe}`, { outcome, location: 'interactive-lgtm-braced-positive-artifact' }));
  }

  const shikiLine = (lineNumber, tokens) => `<span class="line highlighted" data-line="${lineNumber}">${tokens.map(([className, value]) => `<span class="${className}">${value}</span>`).join('')}</span>`;
  const shikiBash = (lines) => `<pre class="astro-code astro-code-themes github-light github-dark" data-language="bash"><code>${lines.map((tokens, index) => shikiLine(index + 1, tokens)).join('\n')}</code></pre>`;
  const slash = '\\';
  const shikiFixtures = [
    ['shiki-unformatted', shikiBash([
      [['token keyword', 'docker'], ['token plain', ' inspect '], ['token string', '&#x22;$LGTM&#x22;']],
    ]), false],
    ['shiki-config-env', shikiBash([
      [['token keyword', 'docker'], ['token plain', ' inspect --format '], ['token string', '&#x27;{{json .Config.Env}}&#x27;'], ['token plain', ' &#x22;$LGTM&#x22;']],
    ]), false],
    ['shiki-state-health', shikiBash([
      [['token keyword', 'docker'], ['token plain', ' inspect --format '], ['token string', '&#x27;{{.State.Health.Status}}&#x27;'], ['token plain', ' &#x22;${LGTM}&#x22;']],
    ]), true],
    ['multiline-unformatted', shikiBash([
      [['token keyword', 'docker'], ['token plain', ` inspect ${slash}`]],
      [['token plain', '  &#x22;$LGTM&#x22;']],
    ]), false],
    ['multiline-config-env', shikiBash([
      [['token keyword', 'docker'], ['token plain', ` inspect ${slash}`]],
      [['token plain', ` --format `], ['token string', '&#x27;{{json .Config.Env}}&#x27;'], ['token plain', ` ${slash}`]],
      [['token plain', ' &#x22;$LGTM&#x22;']],
    ]), false],
    ['multiline-state-health', shikiBash([
      [['token keyword', 'docker'], ['token plain', ` container inspect ${slash}`]],
      [['token plain', ` --format `], ['token string', '&#x27;{{.State.Status}}&#x27;'], ['token plain', ` ${slash}`]],
      [['token plain', ' &#x22;${LGTM}&#x22;']],
    ]), true],
  ];
  for (const [name, fixture, safe] of shikiFixtures) {
    const fixtureLocation = `interactive-lgtm-${name}`;
    if (safe) {
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(fixture, fixtureLocation));
      assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${fixture}`, { outcome, location: `${fixtureLocation}-artifact` }));
    } else {
      assert.throws(() => assertNoUnsafeLgtmDiagnostics(fixture, fixtureLocation), /LGTM diagnostics/);
      assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${fixture}`, { outcome, location: `${fixtureLocation}-artifact` }), /LGTM diagnostics/);
    }
  }

  const br = '\\';
  const prettyBrFixtures = [
    ['br-unformatted', `<pre><code>docker inspect ${br}<br>\n  &quot;$LGTM&quot;</code></pre>`, false],
    ['br-config-env', `<pre><code>docker inspect ${br}<br data-line="2" />\n  --format &#x27;{{json .Config.Env}}&#x27; ${br}<br class="line-break"/>\n  &quot;$LGTM&quot;</code></pre>`, false],
    ['br-state-health', `<pre><code>docker inspect ${br}<br class="line-break" />\n  --format &#x27;{{.State.Health.Status}}&#x27; ${br}<br data-line="3">\n  &quot;${'${LGTM}'}&quot;</code></pre>`, true],
  ];
  for (const [name, fixture, safe] of prettyBrFixtures) {
    const fixtureLocation = `interactive-lgtm-${name}`;
    if (safe) {
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(fixture, fixtureLocation));
      assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${fixture}`, { outcome, location: `${fixtureLocation}-artifact` }));
    } else {
      assert.throws(() => assertNoUnsafeLgtmDiagnostics(fixture, fixtureLocation), /LGTM diagnostics/);
      assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${fixture}`, { outcome, location: `${fixtureLocation}-artifact` }), /LGTM diagnostics/);
    }
  }

  const tenthFixtures = [
    ['parameter-required-unformatted', `docker inspect "${'${LGTM:?required}'}"`, false],
    ['parameter-default-unformatted', `docker inspect "${'${LGTM:-fallback}'}"`, false],
    ['parameter-question-unformatted', `docker inspect "${'${LGTM?required}'}"`, false],
    ['parameter-prefix-unformatted', `docker inspect "${'${LGTM#prefix}'}"`, false],
    ['parameter-percent-unformatted', `docker inspect "${'${LGTM%?}'}"`, false],
    ['parameter-substitution-unformatted', `docker inspect "${'${LGTM/foo/bar}'}"`, false],
    ['parameter-slice-unformatted', `docker inspect "${'${LGTM:1}'}"`, false],
    ['parameter-case-unformatted', `docker inspect "${'${LGTM^^}'}"`, false],
    ['parameter-nested-unformatted', `docker inspect "${'${LGTM:-${FALLBACK}}'}"`, false],
    ['context-unformatted', 'docker --context default inspect "$LGTM"', false],
    ['context-equals-unformatted', 'docker --context=default inspect "$LGTM"', false],
    ['host-unformatted', 'docker -H /var/run/docker.sock inspect "$LGTM"', false],
    ['host-equals-unformatted', 'docker --host=unix:///var/run/docker.sock inspect "$LGTM"', false],
    ['debug-unformatted', 'docker --debug inspect "$LGTM"', false],
    ['comment-fake-format', `docker inspect "$LGTM" # --format '{{.State.Status}}'`, false],
    ['cat-secret', 'cat <<< "$GRAFANA_PASSWORD"', false],
    ['tee-secret', 'tee /tmp/out <<< "$GRAFANA_PASSWORD"', false],
    ['curl-secret', 'curl --data "$GRAFANA_PASSWORD" https://example.test', false],
    ['secret-substitution', `printf '%s\\n' "${'${GRAFANA_PASSWORD#prefix}'}"`, false],
    ['secret-nested', `printf '%s\\n' "${'${GRAFANA_PASSWORD:-${GF_SECURITY_ADMIN_PASSWORD}}'}"`, false],
    ['safe-inspect-then-sink', `docker inspect --format '{{.State.Status}}' "$LGTM"; echo "$GRAFANA_PASSWORD"`, false],
    ['env-input-then-sink', `docker run --env GF_SECURITY_ADMIN_PASSWORD="$GRAFANA_PASSWORD" image; echo "$GRAFANA_PASSWORD"`, false],
    ['shiki-secret-sink', `docker --context default inspect --format '{{.State.Status}}' "${'${LGTM:?required}'}" | echo "$GRAFANA_PASSWORD"`, false, true],
    ['shiki-global-parameter', `docker --context default inspect "${'${LGTM:?required}'}"`, false, true],
    ['assignment-substitution-unformatted', 'status=$(docker inspect "$LGTM")', false],
    ['assignment-substitution-state-positive', 'status=$(docker inspect --format "{{.State.Status}}" "$LGTM")', true],
    ['final-substitution-unformatted', `FINAL=$(docker --context default inspect "${'${LGTM:?required}'}")`, false],
    ['final-substitution-health-positive', `FINAL=$(docker --context default inspect --format "{{.State.Health.Status}}" "${'${LGTM:?required}'}")`, true],
    ['if-prefix-unformatted', 'if docker inspect "$LGTM"; then true; fi', false],
    ['if-prefix-state-positive', 'if docker inspect --format "{{.State.Status}}" "$LGTM"; then true; fi', true],
    ['sudo-prefix-unformatted', 'sudo docker inspect "$LGTM"', false],
    ['sudo-prefix-state-positive', 'sudo docker --debug container inspect --format "{{.State.Status}}" "$LGTM"', true],
    ['shiki-assignment-substitution', 'status=$(docker inspect "$LGTM")', false, true],
    ['shiki-assignment-state-positive', 'status=$(docker inspect --format "{{.State.Status}}" "$LGTM")', true, true],
    ['global-config-unformatted', 'docker --config /tmp inspect "$LGTM"', false, true],
    ['global-short-context-unformatted', 'docker -c default inspect "$LGTM"', false],
    ['global-log-level-unformatted', 'docker -l debug inspect "$LGTM"', false],
    ['global-tls-cacert-unformatted', 'docker --tlscacert ca.pem inspect "$LGTM"', false],
    ['global-tls-cert-unformatted', 'docker --tlscert cert.pem inspect "$LGTM"', false],
    ['global-tls-key-unformatted', 'docker --tlskey key.pem inspect "$LGTM"', false],
    ['global-options-state-positive', `docker --config /tmp -c default -l debug --tlscacert ca.pem --tlscert cert.pem --tlskey key.pem -D --tls --tlsverify inspect --format '{{.State.Status}}' "$LGTM"`, true, true],
    ['bare-env-dump', 'env', false],
    ['bare-printenv-dump', 'printenv', false],
    ['sudo-printenv-dump', 'sudo printenv GRAFANA_PASSWORD', false],
    ['substitution-printenv-dump', 'OUT=$(printenv GRAFANA_PASSWORD)', false, true],
    ['bare-dot-format', `docker inspect --format '{{printf "%v" .}} {{.State.Status}}' "$LGTM"`, false, true],
    ['quoted-docker-path-unformatted', `'/usr/bin/docker' inspect "$LGTM"`, false, true],
    ['quoted-docker-path-state-positive', `"/usr/bin/docker" inspect --format '{{.State.Status}}' "$LGTM"`, true, true],
    ['password-here-string-redirection', 'cat <<<"$GRAFANA_PASSWORD" >/tmp/out', false, true],
    ['literal-here-string-redirection', 'cat <<<"literal" >/tmp/out', true],
    ['brace-group-unformatted', '{ docker inspect "$LGTM"; }', false, true],
    ['paren-group-unformatted', '( docker inspect "$LGTM" )', false],
    ['command-end-unformatted', 'command -- docker inspect "$LGTM"', false],
    ['nice-wrapper-unformatted', 'nice -n 5 docker inspect "$LGTM"', false],
    ['time-wrapper-unformatted', 'time -p docker inspect "$LGTM"', false],
    ['backtick-unformatted', '`docker inspect "$LGTM"`', false, true],
    ['dynamic-command-unformatted', '$DOCKER inspect "$LGTM"', false, true],
    ['unknown-prefix-unformatted', 'wrapper -- docker inspect "$LGTM"', false],
    ['brace-group-state-positive', "{ docker inspect --format '{{.State.Status}}' \"$LGTM\"; }", true],
    ['paren-group-state-positive', "( docker inspect --format '{{.State.Status}}' \"$LGTM\" )", true],
    ['command-end-state-positive', "command -- docker inspect --format '{{.State.Status}}' \"$LGTM\"", true],
    ['nice-wrapper-state-positive', "nice -n 5 docker inspect --format '{{.State.Status}}' \"$LGTM\"", true],
    ['time-wrapper-state-positive', "time -p docker inspect --format '{{.State.Status}}' \"$LGTM\"", true],
    ['backtick-state-positive', "`docker inspect --format '{{.State.Status}}' \"$LGTM\"`", true, true],
    ['parameter-extra-identifier', `docker inspect "${'${LGTM_EXTRA}'}"`, true],
    ['context-state-positive', `docker --context default inspect --format '{{.State.Status}}' "${'${LGTM:?required}'}"`, true],
    ['context-equals-health-positive', `docker --context=default inspect --format '{{.State.Health.Status}}' "${'${LGTM:-fallback}'}"`, true],
    ['host-state-positive', `docker -H /var/run/docker.sock inspect --format '{{.State.Status}}' "$LGTM"`, true],
    ['host-equals-health-positive', `docker --host=unix:///var/run/docker.sock inspect --format '{{.State.Health}}' "$LGTM"`, true],
    ['debug-container-state-positive', `docker --debug container inspect --format '{{.State.Status}}' "$LGTM"`, true],
    ['container-after-global-state-positive', `docker container --context default inspect --format '{{.State.Status}}' "$LGTM"`, true],
    ['nested-state-positive', `docker inspect --format '{{.State.Status}}' "${'${LGTM:-${FALLBACK}}'}"`, true],
    ['pure-assignment', 'GRAFANA_PASSWORD="${GRAFANA_PASSWORD-local-admin}"', true],
    ['env-input', 'docker run --env GF_SECURITY_ADMIN_PASSWORD="$GRAFANA_PASSWORD" grafana/otel-lgtm:fixed', true],
    ['literal-password-prompt', 'printf \'configured GRAFANA_PASSWORD is never printed\'', true],
    ['safe-comment', `docker inspect --format '{{.State.Status}}' "$LGTM" # ordinary comment`, true],
  ];
  for (const [name, fixture, safe, syntheticShiki] of tenthFixtures) {
    const fixtureLocation = `interactive-lgtm-tenth-${name}`;
    const assertFixture = (value, location) => {
      if (safe) {
        assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(value, location));
        assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${value}`, { outcome, location: `${location}-artifact` }));
      } else {
        assert.throws(() => assertNoUnsafeLgtmDiagnostics(value, location), /LGTM diagnostics/);
        assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${value}`, { outcome, location: `${location}-artifact` }), /LGTM diagnostics/);
      }
    };
    assertFixture(fixture, fixtureLocation);
    const actualShiki = await codeToHtml(fixture, {
      lang: 'bash',
      themes: { light: 'github-light', dark: 'github-dark' },
    });
    assertFixture(actualShiki, `interactive-lgtm-tenth-actual-shiki-${name}`);
    if (syntheticShiki) {
      const rendered = shikiBash([[["token plain", fixture]]]);
      if (safe) {
        assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(rendered, `${fixtureLocation}-synthetic-shiki`));
        assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${rendered}`, { outcome, location: `${fixtureLocation}-synthetic-shiki-artifact` }));
      } else {
        assert.throws(() => assertNoUnsafeLgtmDiagnostics(rendered, `${fixtureLocation}-synthetic-shiki`), /LGTM diagnostics/);
        assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${rendered}`, { outcome, location: `${fixtureLocation}-synthetic-shiki-artifact` }), /LGTM diagnostics/);
      }
    }
  }

  const markdownProseFixtures = [
    ['markdown-japanese', 'ValueとOutcomeは空の`readonly` Classです。'],
    ['markdown-ascii', 'The type is `readonly` Class metadata.'],
    ['markdown-use', 'Use `readonly`.'],
    ['markdown-value', '値は`readonly`。'],
    ['markdown-parenthesized', '（`readonly`）'],
  ];
  for (const [name, fixture] of markdownProseFixtures) {
    assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(fixture, 'interactive-lgtm-' + name + '.md'));
    const inlineHtml = fixture.replace(/`([^`]+)`/gu, '<code>$1</code>');
    assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics('<p>' + inlineHtml + '</p>', 'interactive-lgtm-' + name + '-inline-html'));
    assert.doesNotThrow(() => assertArtifactReaderText('description: ' + outcome + '\n<p>' + inlineHtml + '</p>', { outcome, location: 'interactive-lgtm-' + name + '-inline-html-artifact' }));
  }

  const inlineCodeMark = String.fromCharCode(96);
  const markdownProtectedFixtures = [
    ['unformatted', 'Run ' + inlineCodeMark + 'docker inspect "$LGTM"' + inlineCodeMark + '.', false],
    ['config-env', 'See ' + inlineCodeMark + 'docker inspect --format "{{json .Config.Env}}" "$LGTM"' + inlineCodeMark + '.', false],
    ['secret-expansion', 'Never use ' + inlineCodeMark + 'printf "%s" "$GRAFANA_PASSWORD"' + inlineCodeMark + '.', false],
    ['state-health', 'Check ' + inlineCodeMark + 'docker inspect --format "{{.State.Health.Status}}" "$LGTM"' + inlineCodeMark + '.', true],
  ];
  for (const [name, fixture, safe] of markdownProtectedFixtures) {
    const markdownLocation = 'interactive-lgtm-markdown-protected-' + name + '.md';
    const parts = fixture.split(inlineCodeMark);
    const inlineHtml = '<p>' + parts[0] + '<code>' + parts[1] + '</code>' + parts[2] + '</p>';
    if (safe) {
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(fixture, markdownLocation));
      assert.doesNotThrow(() => assertArtifactReaderText('description: ' + outcome + '\n' + fixture, { outcome, location: markdownLocation }));
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(inlineHtml, markdownLocation + '-inline-html'));
      assert.doesNotThrow(() => assertArtifactReaderText('description: ' + outcome + '\n' + inlineHtml, { outcome, location: markdownLocation + '-inline-html-artifact' }));
    } else {
      assert.throws(() => assertNoUnsafeLgtmDiagnostics(fixture, markdownLocation), /LGTM diagnostics/);
      assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + fixture, { outcome, location: markdownLocation }), /LGTM diagnostics/);
      assert.throws(() => assertNoUnsafeLgtmDiagnostics(inlineHtml, markdownLocation + '-inline-html'), /LGTM diagnostics/);
      assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + inlineHtml, { outcome, location: markdownLocation + '-inline-html-artifact' }), /LGTM diagnostics/);
    }
  }
  const inlineEnvironmentFixtures = [
    ['printenv-single', 'Run ', 'printenv GRAFANA_PASSWORD', '.', 'md', 1],
    ['env-single', 'Run ', 'env', '.', 'md', 1],
    ['printenv-double', 'Run ', 'printenv GF_SECURITY_ADMIN_PASSWORD', '.', 'md', 2],
    ['declare-dump', 'See ', 'declare -p GRAFANA_PASSWORD', '.', 'md', 1],
    ['printenv-llms-full', 'Run ', 'printenv GRAFANA_PASSWORD', '.', 'llms-full.txt', 1],
  ];
  for (const [name, prefix, body, suffix, extension, runLength] of inlineEnvironmentFixtures) {
    const delimiter = inlineCodeMark.repeat(runLength);
    const fixture = prefix + delimiter + body + delimiter + suffix;
    const location = 'interactive-lgtm-inline-environment-' + name + '.' + extension;
    const inlineHtml = '<p>' + prefix + '<code>' + body + '</code>' + suffix + '</p>';
    assert.throws(() => assertNoUnsafeLgtmDiagnostics(fixture, location), /LGTM diagnostics/);
    assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + fixture, { outcome, location }), /LGTM diagnostics/);
    assert.throws(() => assertNoUnsafeLgtmDiagnostics(inlineHtml, location + '-inline-html'), /LGTM diagnostics/);
    assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + inlineHtml, { outcome, location: location + '-inline-html-artifact' }), /LGTM diagnostics/);
  }
  const i18nContinuationFixtures = [
    ['japanese-execute-env', 'Paragraph\n    次を実行します: `env`。', false],
    ['japanese-command-execute-env', 'Paragraph\n    コマンド `env` を実行します。', false],
    ['english-dump-env', 'Paragraph\n    Use `env` to dump all environment variables.', false],
    ['english-try-env', 'Paragraph\n    Try `env`.', false],
    ['japanese-technical-env', 'Paragraph\n    コマンド名として `env` を説明します。', true],
    ['japanese-technical-readonly', 'Paragraph\n    PHPキーワードとして `readonly` を説明します。', true],
    ['english-technical-env', 'Paragraph\n    Use `env` as the command name.', true],
    ['english-technical-readonly', 'Paragraph\n    Use `readonly` as a PHP keyword.', true],
    ['english-technical-env-suffix', 'Paragraph\n    Use `env` as the command name. Then execute it.', false],
    ['english-technical-readonly-suffix', 'Paragraph\n    Use `readonly` as a PHP keyword. Then execute it.', false],
    ['english-technical-env-prefix', 'Paragraph\n    Execute this now: Use `env` as the command name.', false],
    ['english-technical-readonly-prefix', 'Paragraph\n    Execute this now: Use `readonly` as a PHP keyword.', false],
    ['japanese-technical-env-prefix', 'Paragraph\n    今すぐ実行: コマンド名として `env` を説明します。', false],
    ['japanese-technical-readonly-prefix', 'Paragraph\n    今すぐ実行: PHPキーワードとして `readonly` を説明します。', false],
  ];
  for (const [name, fixture, safe] of i18nContinuationFixtures) {
    const sourceLocation = `interactive-lgtm-i18n-continuation-${name}.md`;
    const htmlLocation = `interactive-lgtm-i18n-continuation-${name}.html`;
    const rendered = (await satteriMarkdownProcessor.render(fixture)).code;
    for (const [value, location] of [[fixture, sourceLocation], [rendered, htmlLocation]]) {
      const sourceCheck = () => assertNoUnsafeLgtmDiagnostics(value, location);
      const artifactLocation = location.replace(/\.(md|html)$/u, '-artifact.$1');
      const artifactCheck = () => assertArtifactReaderText(`description: ${outcome}\n${value}`, { outcome, location: artifactLocation });
      if (safe) {
        assert.doesNotThrow(sourceCheck);
        assert.doesNotThrow(artifactCheck);
      } else {
        assert.throws(sourceCheck, /LGTM diagnostics/);
        assert.throws(artifactCheck, /LGTM diagnostics/);
      }
    }
  }
  const technicalProseFixtures = ['GRAFANA_PASSWORD', 'readonly', 'Class', 'public', 'protected', 'function'];
  for (const [index, fixture] of technicalProseFixtures.entries()) {
    const location = 'interactive-lgtm-technical-prose-' + index + '.md';
    const markdown = 'Use ' + inlineCodeMark + fixture + inlineCodeMark + ' as documentation text.';
    const inlineHtml = '<p>Use <code>' + fixture + '</code> as documentation text.</p>';
    assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(markdown, location));
    assert.doesNotThrow(() => assertArtifactReaderText('description: ' + outcome + '\n' + markdown, { outcome, location }));
    assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(inlineHtml, location + '-inline-html'));
    assert.doesNotThrow(() => assertArtifactReaderText('description: ' + outcome + '\n' + inlineHtml, { outcome, location: location + '-inline-html-artifact' }));
  }
  const multilineInlineFixtures = [
    ['single-env', 'Run ' + inlineCodeMark + 'printenv\nGRAFANA_PASSWORD' + inlineCodeMark + '.', false],
    ['double-env', 'Run ' + inlineCodeMark + inlineCodeMark + 'printenv\nGF_SECURITY_ADMIN_PASSWORD' + inlineCodeMark + inlineCodeMark + '.', false],
    ['double-inspect', 'Run ' + inlineCodeMark + inlineCodeMark + 'docker inspect\n"$LGTM"' + inlineCodeMark + inlineCodeMark + '.', false],
    ['technical-prose', 'Use ' + inlineCodeMark + 'readonly\nkeyword' + inlineCodeMark + ' as text.', true],
  ];
  for (const [name, fixture, safe] of multilineInlineFixtures) {
    const location = 'interactive-lgtm-multiline-inline-' + name + '.md';
    const artifact = 'description: ' + outcome + '\n' + fixture;
    if (safe) {
      assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(fixture, location));
      assert.doesNotThrow(() => assertArtifactReaderText(artifact, { outcome, location }));
    } else {
      assert.throws(() => assertNoUnsafeLgtmDiagnostics(fixture, location), /LGTM diagnostics/);
      assert.throws(() => assertArtifactReaderText(artifact, { outcome, location }), /LGTM diagnostics/);
    }
  }
  const doubleBacktickSafe = 'Use ' + inlineCodeMark + inlineCodeMark + 'readonly' + inlineCodeMark + inlineCodeMark + '.';
  const doubleBacktickLiteral = 'Use ' + inlineCodeMark + inlineCodeMark + 'literal ' + inlineCodeMark + ' token' + inlineCodeMark + inlineCodeMark + '.';
  const doubleBacktickProtected = 'Run ' + inlineCodeMark + inlineCodeMark + 'docker inspect "$LGTM"' + inlineCodeMark + inlineCodeMark + '.';
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(doubleBacktickSafe, 'interactive-lgtm-double-backtick-safe.md'));
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(doubleBacktickLiteral, 'interactive-lgtm-double-backtick-literal.md'));
  assert.throws(() => assertNoUnsafeLgtmDiagnostics(doubleBacktickProtected, 'interactive-lgtm-double-backtick-protected.md'), /LGTM diagnostics/);
  assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + doubleBacktickProtected, { outcome, location: 'interactive-lgtm-double-backtick-protected.md' }), /LGTM diagnostics/);
  const standaloneInlineHtml = '<p>Use</p>\n<code>readonly</code>\n<p>.</p>';
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(standaloneInlineHtml, 'interactive-lgtm-standalone-inline-html'));
  assert.doesNotThrow(() => assertArtifactReaderText('description: ' + outcome + '\n' + standaloneInlineHtml, { outcome, location: 'interactive-lgtm-standalone-inline-html-artifact' }));
  const executablePreHtml = '<pre><code><span class="line"><span>readonly</span></span></code></pre>';
  assert.throws(() => assertNoUnsafeLgtmDiagnostics(executablePreHtml, 'interactive-lgtm-executable-pre-html'), /LGTM diagnostics/);
  assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + executablePreHtml, { outcome, location: 'interactive-lgtm-executable-pre-html-artifact' }), /LGTM diagnostics/);

  const actualShikiFixtures = [
    ['braced-required-unformatted', 'docker inspect ${LGTM:?LGTM required}', false],
    ['braced-nested-unformatted', 'docker inspect ${LGTM:-${FALLBACK:-fallback container}}', false],
    ['braced-state-positive', 'docker inspect --format "{{.State.Status}}" ${LGTM:?LGTM required}', true],
    ['shell-payload-inspect-unformatted', 'sh -c \'docker inspect "$LGTM"\'', false],
    ['shell-payload-secret', 'bash -c \'printf "%s\\n" "$GRAFANA_PASSWORD"\'', false],
    ['docker-exec-shell-payload-secret', 'docker exec "$LGTM" sh -c \'printenv GRAFANA_PASSWORD\'', false],
    ['xargs-printenv-secret', "printf '%s\\n' GRAFANA_PASSWORD | xargs printenv", false],
    ['shell-payload-literal-safe', "sh -c 'printf literal-only'", true],
    ['shell-payload-inspect-state-safe', 'sh -c \'docker inspect --format "{{.State.Status}}" "$LGTM"\'', true],
    ['template-bare-root', 'docker inspect --format \'{{.State.Status}} {{printf "%#v" $}}\' "$LGTM"', false],
    ['template-index-root', 'docker inspect --format \'{{.State.Status}} {{printf "%v" (index $ "Config")}}\' "$LGTM"', false],
    ['template-dot-root', 'docker inspect --format \'{{.State.Status}} {{printf "%v" $.Config}}\' "$LGTM"', false],
    ['template-pipeline-root', 'docker inspect --format \'{{.State.Status}} {{printf "%v" $|printf "%v"}}\' "$LGTM"', false],
    ['template-state-safe', 'docker inspect --format \'{{.State.Status}}\' "$LGTM"', true],
    ['xargs-n1-printenv', "printf '%s\\n' GRAFANA_PASSWORD | xargs -n1 printenv", false],
    ['xargs-n-space-printenv', "printf '%s\\n' GRAFANA_PASSWORD | xargs -n 1 printenv", false],
    ['xargs-max-args-printenv', "printf '%s\\n' GRAFANA_PASSWORD | xargs --max-args=1 printenv", false],
    ['xargs-end-printenv', "printf '%s\\n' GRAFANA_PASSWORD | xargs -- printenv", false],
    ['xargs-safe-printf', "printf '%s\\n' literal | xargs -n1 printf", true],
    ['shell-combined-inspect', 'bash -lc \'docker inspect "$LGTM"\'', false],
    ['shell-combined-printenv', 'sh -ec \'printenv GRAFANA_PASSWORD\'', false],
    ['docker-exec-combined-secret', 'docker exec "$LGTM" bash -lc \'printf "%s\\n" "$GRAFANA_PASSWORD"\'', false],
    ['shell-option-inspect', 'bash -o pipefail -c \'docker inspect "$LGTM"\'', false],
    ['shell-option-rcfile-printenv', 'bash --rcfile /tmp/x -c \'printenv GRAFANA_PASSWORD\'', false],
    ['shell-option-init-file-inspect', 'sh -o errexit -c \'docker inspect "$LGTM"\'', false],
   ['shell-option-literal-safe', "bash -o pipefail -c 'printf literal-only'", true],
   ['shell-option-rcfile-safe', "bash --rcfile /tmp/x -c 'printf literal-only'", true],
    ['outer-wrapper-unresolved', 'wrapper inspect "$LGTM"', false],
    ['outer-command-wrapper-unresolved', 'command-wrapper -- "$DOCKER" inspect "$LGTM"', false],
    ['outer-nested-wrapper-unresolved', 'status=$(wrapper inspect "$LGTM")', false],
    ['outer-wrapper-with-nested-docker', 'wrapper inspect "$LGTM" "$(docker inspect --format "{{.State.Status}}" "$OTHER")"', false],
    ['outer-nested-wrapper-with-nested-docker', 'status=$(wrapper inspect "$LGTM" "$(docker inspect --format "{{.State.Status}}" "$OTHER")")', false],
    ['outer-and-nested-docker-state-safe', 'docker inspect --format "{{.State.Status}}" "$LGTM" "$(docker inspect --format "{{.State.Status}}" "$OTHER")"', true],
    ['outer-substitution-and-nested-docker-state-safe', 'status=$(docker inspect --format "{{.State.Status}}" "$LGTM" "$(docker inspect --format "{{.State.Status}}" "$OTHER")")', true],
    ['safe-inspect-unrelated-substitution', 'docker inspect --format "{{.State.Status}}" "$LGTM" "$(printf unrelated)"', true],
    ['process-inspect-unformatted', '<(docker inspect "$LGTM")', false],
    ['process-secret-output', '>(printf "%s\\n" "$GRAFANA_PASSWORD")', false],
    ['eval-inspect-unformatted', `eval 'docker inspect "$LGTM"'`, false],
    ['eval-secret-output', `eval 'printf "%s\\n" "$GRAFANA_PASSWORD"'`, false],
    ['here-string-inspect-unformatted', `bash <<< 'docker inspect "$LGTM"'`, false],
    ['here-string-printenv-secret', `sh <<< 'printenv GRAFANA_PASSWORD'`, false],
    ['safe-process-inspect', '<(docker inspect --format "{{.State.Status}}" "$LGTM")', true],
    ['safe-eval-literal', "eval 'printf literal-only'", true],
    ['safe-here-string-literal', "bash <<< 'printf literal-only'", true],
    ['bare-set-dump', 'set', false],
    ['set-options-safe', 'set -Eeuo pipefail', true],
    ['export-dump', 'export -p', false],
    ['declare-dump', 'declare -p', false],
    ['typeset-dump', 'typeset -p', false],
    ['readonly-dump', 'readonly -p', false],
    ['export-assignment-safe', 'export GRAFANA_PASSWORD=local-admin', true],
    ['literal-brace-unformatted', 'docker inspect ${LGTM:-foo{bar}', false],
    ['nested-brace-unformatted', 'docker inspect ${LGTM:-${FALLBACK:-fallback container}}', false],
   ['literal-brace-state-positive', 'docker inspect --format "{{.State.Status}}" ${LGTM:-foo{bar}', true],
    ['nested-process-printenv', 'cat <(echo <(printenv GRAFANA_PASSWORD))', false],
    ['nested-process-env', 'cat <(cat <(env))', false],
   ['nested-process-group-printenv', 'cat <( ( printenv GRAFANA_PASSWORD ) )', false],
   ['nested-process-literal-safe', 'cat <(echo <(printf literal-only))', true],
    ['process-shell-inspect-producer', `bash <(printf 'docker inspect "$LGTM"')`, false],
    ['process-shell-printenv-producer', `bash <(printf 'printenv GRAFANA_PASSWORD')`, false],
    ['pipeline-shell-inspect-producer', `printf 'docker inspect "$LGTM"' | bash`, false],
    ['pipeline-shell-printenv-producer', `printf 'printenv GRAFANA_PASSWORD' | bash`, false],
    ['pipeline-nospace-inspect-producer', `printf 'docker inspect "$LGTM"'|bash`, false],
    ['pipeline-nospace-printenv-producer', `printf 'printenv GRAFANA_PASSWORD'|bash`, false],
    ['process-shell-literal-safe', `bash <(printf 'literal-only')`, true],
   ['pipeline-shell-literal-safe', `printf 'literal-only' | bash`, true],
    ['pipeline-nospace-literal-safe', `printf 'literal-only'|bash`, true],
    ['group-printenv-no-space', '(printenv GRAFANA_PASSWORD)', false],
    ['group-env-no-space', '(env)', false],
    ['group-inspect-state-safe', '(docker inspect --format "{{.State.Status}}" "$LGTM")', true],
    ['case-printenv-protected', 'case "$MODE" in ready) printenv GRAFANA_PASSWORD ;; esac', false],
    ['case-secret-expansion', 'case "$MODE" in ready) printf "%s" "$GRAFANA_PASSWORD" ;; esac', false],
    ['case-nested-process-printenv-protected', 'cat <(case "$MODE" in ready) printenv GRAFANA_PASSWORD ;; esac)', false],
    ['case-literal-safe', 'case "$MODE" in ready) printf literal-only ;; esac', true],
    ['case-bare-env', "bash -c 'case x in x) env;; esac'", false],
    ['case-multi-pattern-bare-env', "bash -c 'case x in x|y) env;; esac'", false],
    ['case-nested-process-bare-env', "cat <(bash -c 'case x in x) env;; esac')", false],
    ['brace-group-bare-env', '{ env; }', false],
    ['brace-group-bare-readonly', '{ readonly; }', false],
    ['leading-redirection-bare-env', '>/tmp/lgtm-env env', false],
    ['leading-redirection-bare-export', '>/tmp/lgtm-env export', false],
    ['builtin-bare-export', 'builtin export', false],
    ['builtin-bare-declare', 'builtin declare', false],
    ['leading-redirection-literal-safe', '>/tmp/lgtm-status printf literal-only', true],
    ['builtin-named-export-safe', 'builtin export GRAFANA_PASSWORD', true],
    ['case-state-inspect-safe', 'case x in x) docker inspect --format "{{.State.Status}}" "$LGTM";; esac', true],
    ['brace-group-state-inspect-safe', '{ docker inspect --format "{{.State.Status}}" "$LGTM"; }', true],
    ['literal-case-word-safe', "printf '%s\\n' case env", true],
    ['literal-in-word-safe', 'echo in env', true],
    ['literal-brace-word-safe', 'printf %s { env', true],
    ['literal-quoted-close-safe', "printf '%s' ')' env", true],
    ['subshell-bare-export', '(export)', false],
    ['subshell-bare-declare', '(declare)', false],
    ['trailing-redirection-export', 'export >/tmp/lgtm-env', false],
    ['trailing-redirection-readonly', 'readonly 2>/tmp/lgtm-env', false],
    ['fd-dup-export', 'export 2>&1', false],
    ['spaced-redirection-declare', 'declare > /tmp/lgtm-env', false],
    ['named-export-redirection-safe', 'export NAME >/tmp/x', true],
    ['named-declare-redirection-safe', 'declare NAME > /tmp/x', true],
    ['option-only-export-dump', 'export --', false],
    ['option-only-declare-dump', 'declare --', false],
    ['option-only-typeset-dump', 'typeset -x', false],
    ['option-only-readonly-dump', 'readonly --', false],
    ['option-named-declare-safe', 'declare -x GRAFANA_PASSWORD', true],
    ['option-named-export-safe', 'export -- GRAFANA_PASSWORD', true],
    ['option-named-readonly-safe', 'readonly -- GF_SECURITY_ADMIN_PASSWORD', true],
    ['option-named-typeset-safe', 'typeset -x GF_SECURITY_ADMIN_PASSWORD=local', true],
    ['command-preserve-path-bare-env', 'command -p env', false],
    ['nice-value-option-bare-env', 'nice -n 5 env', false],
    ['time-option-bare-env', 'time -p env', false],
    ['setsid-option-bare-env', 'setsid -f env', false],
    ['nohup-end-option-bare-env', 'nohup -- env', false],
    ['exec-name-option-bare-env', 'exec -a diagnostic env', false],
    ['builtin-end-option-bare-export', 'builtin -- export', false],
    ['command-lookup-v-safe', 'command -v env', true],
    ['command-lookup-V-safe', 'command -V env', true],
    ['literal-xargs-env-safe', 'echo xargs env', true],
    ['xargs-literal-env-safe', 'printf x | xargs printf env', true],
    ['bare-export-dump', 'export', false],
    ['bare-declare-dump', 'declare', false],
    ['bare-typeset-dump', 'typeset', false],
    ['bare-readonly-dump', 'readonly', false],
    ['named-declare-safe', 'declare GRAFANA_PASSWORD', true],
    ['named-typeset-safe', 'typeset GF_SECURITY_ADMIN_PASSWORD', true],
    ['named-export-safe', 'export GRAFANA_PASSWORD', true],
    ['named-readonly-safe', 'readonly GF_SECURITY_ADMIN_PASSWORD', true],
    ['wrapper-unresolved-with-substitution', 'wrapper inspect "$LGTM" "$(printf ok)"', false],
    ['variable-wrapper-unresolved-with-substitution', '$DOCKER inspect "$LGTM" "$(true)"', false],
    ['command-wrapper-unresolved-with-substitution', 'command wrapper inspect "$LGTM" "$(echo safe)"', false],
    ['process-printenv-exact', 'cat <(printenv GRAFANA_PASSWORD)', false],
    ['process-env-exact', 'diff /dev/null <(env)', false],
    ['eval-printenv-exact', "eval 'printenv GRAFANA_PASSWORD'", false],
    ['declare-named-dump', 'declare -p GRAFANA_PASSWORD', false],
    ['typeset-named-dump', 'typeset -p GF_SECURITY_ADMIN_PASSWORD', false],
    ['literal-brace-followed-by-dump', 'docker inspect --format "{{.State.Status}}" ${LGTM:-foo{bar}; printenv GRAFANA_PASSWORD', false],
    ['literal-brace-followed-by-literal-safe', 'docker inspect --format "{{.State.Status}}" ${LGTM:-foo{bar}; printf literal-only', true],
    ['nested-process-no-space-group', 'cat <((printenv GRAFANA_PASSWORD))', false],
    ['nice-adjustment-env', 'nice --adjustment 5 env', false],
    ['time-format-env', '/usr/bin/time --format %E env', false],
    ['time-output-env', '/usr/bin/time --output /tmp/lgtm-time env', false],
    ['sudo-chdir-env', 'sudo --chdir /tmp env', false],
    ['xargs-replace-env', 'printf x | xargs -I REPL env REPL', false],
    ['nice-adjustment-literal-safe', 'nice --adjustment 5 printf literal-only', true],
    ['time-format-literal-safe', '/usr/bin/time --format %E printf literal-only', true],
    ['xargs-replace-literal-safe', 'xargs -I REPL printf literal-only REPL', true],
    ['sudo-close-from-env', 'sudo -C 3 env', false],
    ['sudo-close-from-long-env', 'sudo --close-from 3 env', false],
    ['sudo-role-env', 'sudo -r staff_r env', false],
    ['time-help-safe', '/usr/bin/time -h env', true],
    ['time-version-short-safe', '/usr/bin/time -V env', true],
    ['xargs-replace-bare-safe', 'printf x | xargs --replace printf env', true],
    ['xargs-eof-bare-safe', 'printf x | xargs --eof printf env', true],
    ['xargs-replace-equals-env', 'printf x | xargs --replace=REPL env REPL', false],
    ['xargs-eof-equals-env', 'printf x | xargs --eof=END env END', false],
    ['xargs-max-lines-bare-safe', 'printf x | xargs --max-lines printf env', true],
    ['xargs-max-lines-equals-env', 'printf x | xargs --max-lines=1 env', false],
    ['xargs-max-lines-short-required-env', 'printf x | xargs -L 1 env', false],
    ['xargs-max-args-equals-env', 'printf x | xargs --max-args=1 env', false],
    ['xargs-max-args-short-required-env', 'printf x | xargs -n 1 env', false],
    ['xargs-l-optional-safe', 'printf x | xargs -l printf env', true],
    ['xargs-l-attached-env', 'printf x | xargs -l2 env', false],
    ['xargs-i-optional-safe', 'printf x | xargs -i printf env', true],
    ['xargs-e-optional-safe', 'printf x | xargs -e printf env', true],
    ['unknown-sudo-option-env', 'sudo --mystery 3 env', false],
    ['xargs-show-limits-env', "printf '' | xargs --show-limits env", false],
    ['xargs-show-limits-literal-safe', "printf '' | xargs --show-limits printf literal-only", true],
    ['time-verbose-literal-safe', '/usr/bin/time -v printf literal-only', true],
    ['setsid-ctty-env', 'setsid -c env', false],
    ['setsid-wait-literal-safe', 'setsid -w printf literal-only', true],
    ['setsid-help-safe', 'setsid -h env', true],
    ['exec-clear-env', 'exec -c env', false],
    ['exec-login-env', 'exec -l env', false],
    ['nice-attached-adjustment-env', 'nice -5 env', false],
    ['nice-attached-adjustment-literal-safe', 'nice -5 printf literal-only', true],
    ['sudo-bell-env', 'sudo -B env', false],
    ['sudo-bell-literal-safe', 'sudo -B printf literal-only', true],
    ['sudo-login-class-env', 'sudo -c staff env', false],
    ['sudo-login-class-literal-safe', 'sudo -c staff printf literal-only', true],
    ['sudo-no-update-env', 'sudo -N env', false],
    ['sudo-no-update-literal-safe', 'sudo -N printf literal-only', true],
    ['sudo-shell-env', 'sudo -s env', false],
    ['sudo-shell-literal-safe', 'sudo -s printf literal-only', true],
    ['sudo-auth-type-env', 'sudo --auth-type type env', false],
    ['sudo-auth-type-literal-safe', 'sudo --auth-type type printf literal-only', true],
    ['sudo-preserve-env-equals-env', 'sudo --preserve-env=FOO env', false],
    ['sudo-preserve-env-equals-literal-safe', 'sudo --preserve-env=FOO printf literal-only', true],
    ['sudo-edit-terminal-safe', 'sudo -e env', true],
    ['sudo-remove-timestamp-terminal-safe', 'sudo -K env', true],
    ['setsid-combined-safe', 'setsid -fw printf literal-only', true],
    ['setsid-combined-env', 'setsid -fw env', false],
    ['time-combined-safe', '/usr/bin/time -pv printf literal-only', true],
    ['time-combined-env', '/usr/bin/time -pv env', false],
    ['exec-combined-safe', 'exec -cl printf literal-only', true],
    ['exec-combined-env', 'exec -cl env', false],
    ['xargs-combined-safe', "printf 'x\\0' | xargs -0r printf env", true],
    ['xargs-combined-env', "printf 'x\\0' | xargs -0r env", false],
    ['sudo-combined-safe', 'sudo -nS printf literal-only', true],
    ['sudo-combined-env', 'sudo -nS env', false],
    ['time-combined-terminal-safe', '/usr/bin/time -pV env', true],
    ['setsid-combined-terminal-safe', 'setsid -fV env', true],
    ['sudo-combined-terminal-safe', 'sudo -nV env', true],
    ['nice-negative-adjustment-safe', 'nice --5 printf literal-only', true],
    ['nice-negative-adjustment-env', 'nice --5 env', false],
    ['nice-positive-adjustment-safe', 'nice -+5 printf literal-only', true],
    ['nice-positive-adjustment-env', 'nice -+5 env', false],
    ['backtick-unknown-command', 'custom-tool prefix `env` suffix', false],
    ['backtick-absolute-command', '/usr/local/bin/custom-tool prefix `readonly` suffix', false],
    ['backtick-assignment-command', 'MODE=check custom-tool prefix `printenv` suffix', false],
    ['backtick-assignment-adjacent-command', 'result=pre`env`post', false],
    ['backtick-adjacent-command', 'prefix`env`suffix', false],
    ['punctuated-command', 'custom-tool prefix `env`.', false],
    ['parenthesized-command', '(`readonly`)', false],
    ['quoted-punctuated-command', 'printf "`env`."', false],
    ['backtick-readonly-dump', '`readonly`', false],
    ['backtick-env-dump-assignment', 'result=`env`', false],
    ['backtick-literal-safe-assignment', 'result=`printf literal-only`', true],
    ['i18n-japanese-execute-env-shell', 'Paragraph\n    次を実行します: `env`。', false],
    ['i18n-japanese-command-execute-env-shell', 'Paragraph\n    コマンド `env` を実行します。', false],
    ['i18n-english-dump-env-shell', 'Paragraph\n    Use `env` to dump all environment variables.', false],
    ['i18n-english-try-env-shell', 'Paragraph\n    Try `env`.', false],
    ['i18n-japanese-technical-env-shell', 'Paragraph\n    コマンド名として `env` を説明します。', false],
    ['i18n-japanese-technical-readonly-shell', 'Paragraph\n    PHPキーワードとして `readonly` を説明します。', false],
  ];
  for (const [name, fixture, safe] of actualShikiFixtures) {
    const fixtureLocation = `interactive-lgtm-thirteenth-actual-shiki-${name}`;
    const rendered = await codeToHtml(fixture, {
      lang: 'bash',
      themes: { light: 'github-light', dark: 'github-dark' },
    });
    for (const [value, suffix] of [[fixture, 'source'], [rendered, 'actual-shiki']]) {
      if (safe) {
        assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(value, `${fixtureLocation}-${suffix}`));
        assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n${value}`, { outcome, location: `${fixtureLocation}-${suffix}-artifact` }));
      } else {
        assert.throws(() => assertNoUnsafeLgtmDiagnostics(value, `${fixtureLocation}-${suffix}`), /LGTM diagnostics/);
        assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${value}`, { outcome, location: `${fixtureLocation}-${suffix}-artifact` }), /LGTM diagnostics/);
      }
    }
  }

  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-lgtm-'));
  try {
    const dockerShim = path.join(temporary, 'docker');
    const curlShim = path.join(temporary, 'curl');
    const scriptPath = path.join(temporary, 'interactive.sh');
    await writeFile(dockerShim, `#!/usr/bin/env bash
set -Eeuo pipefail
case "\${1:-}" in
  inspect) printf 'unhealthy\\n' ;;
  port) printf '127.0.0.1:3000\\n' ;;
  *) : ;;
esac
`, { encoding: 'utf8', mode: 0o755 });
    await writeFile(curlShim, '#!/usr/bin/env bash\nexit 22\n', { encoding: 'utf8', mode: 0o755 });
    await writeFile(scriptPath, interactive.replaceAll('seq 1 90', 'seq 1 1').replaceAll('sleep 1', 'sleep 0'), { encoding: 'utf8', mode: 0o700 });
    const sentinel = 'fifth-correction-secret-sentinel';
    let output = '';
    try {
      await execFileAsync('bash', [scriptPath], {
        cwd: repositoryRoot,
        env: { ...process.env, PATH: `${temporary}${path.delimiter}${process.env.PATH ?? ''}`, GRAFANA_PASSWORD: sentinel },
      });
      assert.fail('Forced LGTM health failure unexpectedly passed.');
    } catch (error) {
      assert.notEqual(error?.code, 0);
      output = `${error?.stdout ?? ''}${error?.stderr ?? ''}`;
    }
    assert.doesNotMatch(output, new RegExp(sentinel));
    assert.match(output, /LGTM failure diagnostics: state=unhealthy health=unhealthy/);
    assert.match(output, /LGTM startup diagnostic: Grafana health endpoint did not report database ok/);
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('artifact reader validation routes only reader surfaces through the LGTM parser', () => {
  const readerCases = [
    ['guide.md', '## Unsafe shell\n\n```bash\nexport -p\n```'],
    ['guide/index.html', '<html><body><pre><code>export -p</code></pre></body></html>'],
    ['blume-search.json', JSON.stringify([{ route: '/guide', description: 'Run `export -p`.' }])],
    ['llms.txt', '- [Guide](/guide): Run `export -p`.'],
    ['llms-full.txt', '# Guide\nSource: https://blackops.local/guide\n\nRun `export -p`.'],
  ];
  for (const [location, text] of readerCases) {
    assert.throws(() => assertArtifactReaderFile(text, { location }), /LGTM diagnostics/);
  }

  for (const [location, text] of [
    ['_astro/base-path.Ds54SYed.js', 'export{n,t}'],
    ['_astro/runtime.js', 'set'],
    ['_astro/runtime.js', 'export{n,t}; set'],
    ['assets/notes.txt', 'export -p'],
    ['guide/index.html', '<html><head><script>export{n,t}; set</script><style>export -p</style></head><body><p>Reader text</p></body></html>'],
  ]) {
    assert.doesNotThrow(() => assertArtifactReaderFile(text, { location }));
  }

  for (const [name, text, unsafe] of [
    ['jsonld-type-after-quoted-gt', '<script data-x=">" type="application/ld+json">{"description":"Run `export -p`."}</script>', true],
    ['jsonld-type-before-quoted-gt', '<script type="application/ld+json" data-x=">">{"description":"Run `export -p`."}</script>', true],
    ['jsonld-single-quoted-gt', "<script data-x='>' type='application/ld+json'>{\"description\":\"Run `export -p`.\"}</script>", true],
    ['jsonld-safe-quoted-gt', '<script data-x=">" type="application/ld+json">{"description":"Reader description"}</script>', false],
    ['ordinary-script-quoted-gt', '<script data-x=">" type="text/javascript">export -p</script>', false],
    ['ordinary-script-single-quoted-gt', "<script data-x='>' type='text/javascript'>export -p</script>", false],
  ]) {
    if (unsafe) {
      assert.throws(() => assertArtifactReaderFile(text, { location: `guide/${name}.html` }), /LGTM diagnostics/);
    } else {
      assert.doesNotThrow(() => assertArtifactReaderFile(text, { location: `guide/${name}.html` }));
    }
  }

  for (const [name, text] of [
    ['heading-pre-adjacent', '<h2>Diagnostics</h2><pre><code>export -p</code></pre>'],
    ['paragraph-pre-adjacent', '<p>Run this:</p><pre><code>export -p</code></pre>'],
    ['nested-list-pre-adjacent', '<ul><li>Run this:<pre><code>export -p</code></pre></li></ul>'],
    ['details-summary-pre-adjacent', '<details open><summary>Diagnostics</summary><pre><code>export -p</code></pre></details>'],
    ['div-pre-adjacent', '<div>Diagnostics</div><pre><code>export -p</code></pre>'],
  ]) {
    assert.throws(() => assertArtifactReaderFile(text, { location: `guide/${name}.html` }), /LGTM diagnostics/);
  }

  assert.throws(() => assertArtifactReaderFile(
    '<html><head><meta name="description" content="Run `export -p`." /></head><body>Reader</body></html>',
    { location: 'guide/index.html' },
  ), /LGTM diagnostics/);
  assert.throws(() => assertArtifactReaderFile(
    '<html><head><title>Run `export -p`.</title></head><body>Reader</body></html>',
    { location: 'guide/index.html' },
  ), /LGTM diagnostics/);
  assert.throws(() => assertArtifactReaderFile(
    '<html><head><script type="application/ld+json">{"description":"Run `export -p`."}</script></head><body>Reader</body></html>',
    { location: 'guide/index.html' },
  ), /LGTM diagnostics/);
  assert.doesNotThrow(() => assertArtifactReaderFile(
    '<html><head><!-- Run `export -p`. --></head><body>Reader</body></html>',
    { location: 'guide/index.html' },
  ));
  for (const jsonLd of [
    '<script type=application/ld+json>{"description":"Run `export -p`."}</script>',
    '<script TYPE = "Application/LD+JSON; charset=utf-8">{"description":"Run `export -p`."}</script>',
    '<script type="application/ld+json">{"description":"Run `export -p`",}</script>',
  ]) {
    assert.throws(() => assertArtifactReaderFile(jsonLd, { location: 'guide/index.html' }), /LGTM diagnostics/);
  }
  assert.doesNotThrow(() => assertArtifactReaderFile(
    '<script type=application/ld+json>{"description":"Reader description"}</script>',
    { location: 'guide/index.html' },
  ));
  assert.doesNotThrow(() => assertArtifactReaderFile('<template>Run `export -p`.</template>', { location: 'guide/index.html' }));
  assert.doesNotThrow(() => assertArtifactReaderFile(
    '<template data-x=">"><script type=application/ld+json>{"description":"Run `export -p`."}</script></template>',
    { location: 'guide/index.html' },
  ));
  assert.doesNotThrow(() => assertArtifactReaderFile(
    '<style data-x=">"><script type=application/ld+json>{"description":"Run `export -p`."}</script></style>',
    { location: 'guide/index.html' },
  ));
  for (const [name, text] of [
    ['script-comment-literal-followed-visible', '<script>const marker="<!--";</script><pre><code>export -p</code></pre><!-- end -->'],
    ['style-comment-literal-followed-visible', '<style>const marker="<!--";</style><pre><code>export -p</code></pre><!-- end -->'],
    ['svg-cdata-template-followed-active-jsonld', '<template><svg><![CDATA[<template>]]></svg></template><script type="application/ld+json">{"description":"Run `export -p`."}</script>'],
    ['math-cdata-template-followed-active-jsonld', '<template><math><![CDATA[<template>]]></math></template><script type="application/ld+json">{"description":"Run `export -p`."}</script>'],
    ['comment-close-followed-active-jsonld', '<!-- end --><script type="application/ld+json">{"description":"Run `export -p`."}</script>'],
  ]) {
    assert.throws(() => assertArtifactReaderFile(text, { location: `guide/${name}.html` }), /LGTM diagnostics/);
  }
  assert.doesNotThrow(() => assertArtifactReaderFile(
    '<template><svg><![CDATA[<template>]]></svg></template>',
    { location: 'guide/svg-cdata-template-inert.html' },
  ));
  for (const [name, text] of [
    ['template-nested-inert', '<template><template>Inner</template><script type=application/ld+json>{"description":"Run `export -p`."}</script></template>'],
    ['template-mixed-case-nested-inert', '<TeMpLaTe><tEmPlAtE>Inner</tEmPlAtE><SCRIPT type=application/ld+json>{"description":"Run `export -p`."}</SCRIPT></TeMpLaTe>'],
    ['template-script-literal-inert', '<template><script type="text/javascript">const marker = "</template>"; const jsonLdLike = {"description":"Run `export -p`."};</script></template>'],
    ['template-style-literal-inert', '<template><style>/* "</template>" */ .diagnostic::before { content: "Run `export -p`."; }</style></template>'],
    ['template-missing-close-inert', '<template><script type=application/ld+json>{"description":"Run `export -p`."}</script>'],
  ]) {
    assert.doesNotThrow(() => assertArtifactReaderFile(text, { location: `guide/${name}.html` }));
  }
  assert.throws(() => assertArtifactReaderFile(
    '<template><template>Inner</template></template><script type=application/ld+json>{"description":"Run `export -p`."}</script>',
    { location: 'guide/template-active-jsonld-after-close.html' },
  ), /LGTM diagnostics/);
  assert.throws(() => assertArtifactReaderFile(
    '<template data-x="><script type=application/ld+json>{"description":"Run `export -p`."}</script>',
    { location: 'guide/template-unterminated-start.html' },
  ), /Unterminated HTML start tag/);
  assert.throws(() => assertArtifactReaderFile(
    '<script data-x=">" type="application/ld+json">{"description":"Run `export -p`."}</script>',
    { location: 'guide/index.html' },
  ), /LGTM diagnostics/);
  assert.throws(() => assertArtifactReaderFile('<noscript><pre><code>export -p</code></pre></noscript>', { location: 'guide/index.html' }), /LGTM diagnostics/);
  assert.doesNotThrow(() => assertArtifactReaderFile('<noscript><p>Reader fallback</p></noscript>', { location: 'guide/index.html' }));
});

test('synthetic complete artifact validation applies HTML reader routing to generated pages', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-html-'));
  const artifactDirectory = path.join(temporary, 'synthetic-artifact');
  try {
    await writeSyntheticCompleteReaderArtifact({ artifactDirectory, contentMap });
    const htmlPath = path.join(artifactDirectory, ...contentMap['installation.md'].slug.split('/'), 'index.html');
    const original = await readFile(htmlPath, 'utf8');
    const injectBeforeBodyClose = (suffix) => {
      const bodyClose = original.lastIndexOf('</body>');
      return bodyClose < 0
        ? `${original}${suffix}`
        : `${original.slice(0, bodyClose)}${suffix}${original.slice(bodyClose)}`;
    };
    await assert.doesNotReject(() => validateArtifactReaderContract({ contentMap, artifactDirectory }));

    const wrapCodeToken = (html, token, suffix = '') => {
      const dom = new JSDOM(html);
      try {
        const code = [...dom.window.document.querySelectorAll('article.prose pre code')]
          .find((element) => (element.textContent ?? '').includes(token));
        assert.ok(code, `Shiki control fixture must contain a pre code token: ${token}.`);
        const walker = dom.window.document.createTreeWalker(code, 4);
        while (walker.nextNode()) {
          const node = walker.currentNode;
          const value = node.nodeValue ?? '';
          const index = value.indexOf(token);
          if (index < 0) continue;
          const fragment = dom.window.document.createDocumentFragment();
          fragment.append(value.slice(0, index));
          const span = dom.window.document.createElement('span');
          span.textContent = `${token}${suffix}`;
          fragment.append(span, value.slice(index + token.length));
          node.replaceWith(fragment);
          return dom.serialize();
        }
        assert.fail(`Shiki control fixture could not locate a text node for: ${token}.`);
      } finally {
        dom.window.close();
      }
    };
    const shikiControlBaseline = await readFile(htmlPath, 'utf8');
    const shikiNestedSpans = wrapCodeToken(shikiControlBaseline, 'stat');
    await writeFile(htmlPath, shikiNestedSpans, 'utf8');
    try {
      await assert.doesNotReject(
        () => validateArtifactReaderContract({ contentMap, artifactDirectory }),
        'Shiki-like nested spans must preserve visible text semantics.',
      );
    } finally {
      await writeFile(htmlPath, shikiControlBaseline, 'utf8');
    }
    const shikiCharacterMutation = wrapCodeToken(shikiControlBaseline, 'stat', 'x');
    await writeFile(htmlPath, shikiCharacterMutation, 'utf8');
    try {
      await assert.rejects(
        validateArtifactReaderContract({ contentMap, artifactDirectory }),
        /HTML semantic body drifted/,
        'A character added inside a Shiki-like span must fail visible text semantics.',
      );
    } finally {
      await writeFile(htmlPath, shikiControlBaseline, 'utf8');
    }

    const cases = [
      ['comment-only', '<!-- Run `export -p`. -->', false],
      ['visible-code', '\n<pre><code>export -p</code></pre>', true],
      ['visible-heading-pre-adjacent', '<h2>Diagnostics</h2><pre><code>export -p</code></pre>', true],
      ['visible-paragraph-pre-adjacent', '<p>Run this:</p><pre><code>export -p</code></pre>', true],
      ['visible-list-pre-adjacent', '<ul><li>Run this:<pre><code>export -p</code></pre></li></ul>', true],
      ['visible-details-pre-adjacent', '<details open><summary>Diagnostics</summary><pre><code>export -p</code></pre></details>', true],
      ['visible-div-pre-adjacent', '<div>Diagnostics</div><pre><code>export -p</code></pre>', true],
      ['visible-noscript', '<noscript><pre><code>export -p</code></pre></noscript>', true],
      ['visible-noscript-safe', '<noscript><p>Reader fallback</p></noscript>', false],
      ['visible-meta', '\n<meta name="description" content="Run `export -p`." />', true],
      ['visible-title', '\n<title>Run `export -p`.</title>', true],
      ['jsonld-unquoted', '<script type=application/ld+json>{"description":"Run `export -p`."}</script>', true],
      ['jsonld-charset', '<script TYPE = "Application/LD+JSON; charset=utf-8">{"description":"Run `export -p`."}</script>', true],
      ['jsonld-malformed', '<script type="application/ld+json">{"description":"Run `export -p`",}</script>', true],
      ['jsonld-safe', '<script type=application/ld+json>{"description":"Reader description"}</script>', false],
      ['jsonld-type-after-quoted-gt', '<script data-x=">" type="application/ld+json">{"description":"Run `export -p`."}</script>', true],
      ['jsonld-type-before-quoted-gt', '<script type="application/ld+json" data-x=">">{"description":"Run `export -p`."}</script>', true],
      ['jsonld-single-quoted-gt', "<script data-x='>' type='application/ld+json'>{\"description\":\"Run `export -p`.\"}</script>", true],
      ['jsonld-safe-quoted-gt', '<script data-x=">" type="application/ld+json">{"description":"Reader description"}</script>', false],
      ['ordinary-script-quoted-gt', '<script data-x=">" type="text/javascript">export -p</script>', false],
      ['ordinary-script-single-quoted-gt', "<script data-x='>' type='text/javascript'>export -p</script>", false],
      ['template-inert-jsonld', '<template data-x=">"><script type=application/ld+json>{"description":"Run `export -p`."}</script></template>', false],
      ['template-nested-inert', '<template><template>Inner</template><script type=application/ld+json>{"description":"Run `export -p`."}</script></template>', false],
      ['template-mixed-case-nested-inert', '<TeMpLaTe><tEmPlAtE>Inner</tEmPlAtE><SCRIPT type=application/ld+json>{"description":"Run `export -p`."}</SCRIPT></TeMpLaTe>', false],
      ['template-script-literal-inert', '<template><script type="text/javascript">const marker = "</template>"; const jsonLdLike = {"description":"Run `export -p`."};</script></template>', false],
      ['template-style-literal-inert', '<template><style>/* "</template>" */ .diagnostic::before { content: "Run `export -p`."; }</style></template>', false],
      ['template-missing-close-inert', '<template><script type=application/ld+json>{"description":"Run `export -p`."}</script>', true, /HTML/],
      ['template-active-jsonld-after-close', '<template><template>Inner</template></template><script type=application/ld+json>{"description":"Run `export -p`."}</script>', true],
      ['template-unterminated-start', '<template data-x="><script type=application/ld+json>{"description":"Run `export -p`."}</script>', true, /Unterminated HTML start tag/],
      ['script-comment-literal-followed-visible', '<script>const marker="<!--";</script><pre><code>export -p</code></pre><!-- end -->', true],
      ['style-comment-literal-followed-visible', '<style>const marker="<!--";</style><pre><code>export -p</code></pre><!-- end -->', true],
      ['svg-cdata-template-followed-active-jsonld', '<template><svg><![CDATA[<template>]]></svg></template><script type="application/ld+json">{"description":"Run `export -p`."}</script>', true],
      ['math-cdata-template-followed-active-jsonld', '<template><math><![CDATA[<template>]]></math></template><script type="application/ld+json">{"description":"Run `export -p`."}</script>', true],
      ['svg-cdata-template-inert', '<template><svg><![CDATA[<template>]]></svg></template>', false],
      ['comment-close-followed-active-jsonld', '<!-- end --><script type="application/ld+json">{"description":"Run `export -p`."}</script>', true],
      ['style-inert-jsonld', '<style data-x=">"><script type=application/ld+json>{"description":"Run `export -p`."}</script></style>', false],
      ['active-jsonld-quoted-gt', '<script data-x=">" type="application/ld+json">{"description":"Run `export -p`."}</script>', true],
    ];
    for (const [name, suffix, unsafe, errorPattern = /LGTM diagnostics/] of cases) {
      await writeFile(htmlPath, injectBeforeBodyClose(suffix), 'utf8');
      if (unsafe) {
        await assert.rejects(validateArtifactReaderContract({ contentMap, artifactDirectory }), errorPattern,
          `HTML ${name} must be rejected by the full Artifact validator.`);
      } else {
        await assert.doesNotReject(() => validateArtifactReaderContract({ contentMap, artifactDirectory }),
          `HTML ${name} must remain outside the Shell reader surface.`);
      }
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('full-path artifact validation requires source, raw, and Search placeholder parity', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-placeholders-'));
  const artifactDirectory = path.join(temporary, 'synthetic-artifact');
  const sourceDirectory = path.join(repositoryRoot, 'docs/guide');
  try {
    const fixturePipeline = await writeSyntheticCompleteReaderArtifact({ artifactDirectory, contentMap });
    const validateFixture = (overrides = {}) => validateArtifactReaderContract({
      contentMap,
      artifactDirectory,
      sourceDirectory,
      manifestPath: fixturePipeline.manifestPath,
      contentRoot: fixturePipeline.contentRoot,
      ...overrides,
    });
    await assert.doesNotReject(() => validateFixture());
    await assert.doesNotReject(() => validateFixture({ sourceDirectory: null }),
      'Null sourceDirectory must use the real Source root instead of disabling parity.');

    const htmlPathForRoute = (route) => path.join(artifactDirectory, ...route.slice(1).split('/'), 'index.html');
    const mutateHtmlDocument = (html, callback) => {
      const dom = new JSDOM(html);
      try {
        const article = dom.window.document.querySelector('article.prose');
        assert.ok(article, 'HTML mutation fixture must contain article.prose.');
        callback(article, dom.window.document);
        return dom.serialize();
      } finally {
        dom.window.close();
      }
    };
    const assertHtmlMutationRejects = async (route, mutate, message, pattern = /HTML semantic body drifted/) => {
      const htmlPath = htmlPathForRoute(route);
      const baseline = await readFile(htmlPath, 'utf8');
      const mutated = await mutate(baseline);
      assert.notEqual(mutated, baseline, `${message} must not be a no-op.`);
      await writeFile(htmlPath, mutated, 'utf8');
      try {
        await assert.rejects(validateFixture(), pattern, message);
      } finally {
        await writeFile(htmlPath, baseline, 'utf8');
      }
    };
    const assertHtmlMutationAccepts = async (route, mutate, message) => {
      const htmlPath = htmlPathForRoute(route);
      const baseline = await readFile(htmlPath, 'utf8');
      const mutated = await mutate(baseline);
      assert.notEqual(mutated, baseline, `${message} must not be a no-op.`);
      await writeFile(htmlPath, mutated, 'utf8');
      try {
        await assert.doesNotReject(() => validateFixture(), message);
      } finally {
        await writeFile(htmlPath, baseline, 'utf8');
      }
    };
    const findBodyTextNode = (article) => {
      const walker = article.ownerDocument.createTreeWalker(article, 4);
      while (walker.nextNode()) {
        const node = walker.currentNode;
        if (node.parentElement?.closest('h1, p.text-lg') !== null) continue;
        if (/[A-Za-z\u3040-\u30ff]/u.test(node.nodeValue ?? '')) return node;
      }
      return null;
    };
    const mutateBodyTextCharacter = (html, mode) => mutateHtmlDocument(html, (article) => {
      const node = findBodyTextNode(article);
      assert.ok(node, 'HTML body mutation fixture must contain an ordinary visible text node.');
      const value = node.nodeValue ?? '';
      const index = value.search(/[A-Za-z\u3040-\u30ff]/u);
      assert.ok(index >= 0, 'HTML body mutation fixture must contain an ordinary character.');
      node.nodeValue = mode === 'delete'
        ? `${value.slice(0, index)}${value.slice(index + 1)}`
        : `${value.slice(0, index + 1)}x${value.slice(index + 1)}`;
    });

    const htmlSwapTargetRoute = '/concepts/why-blackops';
    const htmlSwapDonorRoute = '/getting-started/installation';
    const htmlSwapTargetPath = htmlPathForRoute(htmlSwapTargetRoute);
    const htmlSwapDonorPath = htmlPathForRoute(htmlSwapDonorRoute);
    const htmlSwapTargetBaseline = await readFile(htmlSwapTargetPath, 'utf8');
    const htmlSwapDonorBaseline = await readFile(htmlSwapDonorPath, 'utf8');
    const swappedHtml = (() => {
      const targetDom = new JSDOM(htmlSwapTargetBaseline);
      const donorDom = new JSDOM(htmlSwapDonorBaseline);
      try {
        const target = targetDom.window.document.querySelector('article.prose');
        const donor = donorDom.window.document.querySelector('article.prose');
        assert.ok(target && donor, 'HTML body swap fixtures must contain article.prose.');
        const targetTitle = target.querySelector('h1');
        const targetOutcome = target.querySelector('p.text-lg');
        const donorTitle = donor.querySelector('h1');
        const donorOutcome = donor.querySelector('p.text-lg');
        assert.ok(targetTitle && targetOutcome && donorTitle && donorOutcome, 'HTML body swap fixtures must contain H1 and lead outcome.');
        assert.notEqual(targetTitle.textContent, donorTitle.textContent, 'HTML body swap fixtures must have distinct titles.');
        assert.notEqual(targetOutcome.textContent, donorOutcome.textContent, 'HTML body swap fixtures must have distinct outcomes.');
        const donorBody = [...donor.children].filter((element) => element !== donorTitle && element !== donorOutcome);
        target.replaceChildren(targetTitle, targetOutcome, ...donorBody.map((element) => element.cloneNode(true)));
        return targetDom.serialize();
      } finally {
        targetDom.window.close();
        donorDom.window.close();
      }
    })();
    await writeFile(htmlSwapTargetPath, swappedHtml, 'utf8');
    try {
      await assert.rejects(validateFixture(), /HTML semantic body drifted/, 'A zero-placeholder HTML body swap must fail semantic Raw binding.');
    } finally {
      await writeFile(htmlSwapTargetPath, htmlSwapTargetBaseline, 'utf8');
    }

    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateBodyTextCharacter(html, 'add'), 'A visible one-character HTML addition must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateBodyTextCharacter(html, 'delete'), 'A visible one-character HTML deletion must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article, document) => {
      const heading = article.querySelector('h2');
      assert.ok(heading, 'Heading-level mutation fixture must contain an h2.');
      const replacement = document.createElement('h3');
      for (const attribute of heading.attributes) replacement.setAttribute(attribute.name, attribute.value);
      replacement.innerHTML = heading.innerHTML;
      heading.replaceWith(replacement);
    }), 'A heading-level HTML mutation must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article) => {
      const heading = article.querySelector('h2');
      assert.ok(heading, 'Heading-text mutation fixture must contain an h2.');
      heading.append(' substituted');
    }), 'A heading-text HTML mutation must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article) => {
      const link = [...article.querySelectorAll('a[href]')]
        .find((element) => !element.classList.contains('blume-heading-anchor') && element.closest('h1, h2, h3, h4, h5, h6') === null);
      assert.ok(link, 'Link-href mutation fixture must contain an ordinary link.');
      link.setAttribute('href', `${link.getAttribute('href')}?mutated=1`);
    }), 'A link-href HTML mutation must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article) => {
      const link = [...article.querySelectorAll('a[href]')]
        .find((element) => !element.classList.contains('blume-heading-anchor') && element.closest('h1, h2, h3, h4, h5, h6') === null);
      assert.ok(link, 'Link-text mutation fixture must contain an ordinary link.');
      link.append(' substituted');
    }), 'A link-text HTML mutation must fail.');

    const preRoute = '/getting-started/installation';
    await assertHtmlMutationRejects(preRoute, (html) => mutateHtmlDocument(html, (article) => {
      const code = article.querySelector('pre code');
      assert.ok(code, 'Pre character mutation fixture must contain a fenced code block.');
      code.textContent = `${code.textContent}x`;
    }), 'A fenced-code character mutation must fail.');
    await assertHtmlMutationRejects(preRoute, (html) => mutateHtmlDocument(html, (article) => {
      const code = article.querySelector('pre code');
      assert.ok(code, 'Pre whitespace mutation fixture must contain a fenced code block.');
      const value = code.textContent ?? '';
      assert.match(value, / /u, 'Pre whitespace mutation fixture must contain a space.');
      code.textContent = value.replace(' ', '  ');
    }), 'A fenced-code whitespace mutation must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article) => {
      const code = [...article.querySelectorAll('code')].find((element) => element.closest('pre') === null);
      assert.ok(code, 'Inline-code character mutation fixture must contain inline code.');
      code.textContent = `${code.textContent}x`;
    }), 'An inline-code character mutation must fail.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article) => {
      const code = [...article.querySelectorAll('code')].find((element) => element.closest('pre') === null);
      assert.ok(code, 'Inline-code whitespace mutation fixture must contain inline code.');
      code.textContent = `${code.textContent} `;
    }), 'An inline-code whitespace mutation must fail.');

    const imageRoute = '/testing/community-board';
    await assertHtmlMutationRejects(imageRoute, (html) => mutateHtmlDocument(html, (article) => {
      const image = article.querySelector('img[alt]');
      assert.ok(image, 'Image-alt mutation fixture must contain an image with alt text.');
      image.setAttribute('alt', `${image.getAttribute('alt')} substituted`);
    }), 'An image-alt HTML mutation must fail.');

    const diagramRoute = '/concepts/core-concepts';
    const diagramManifest = await loadDiagramManifest();
    const runtimeDiagram = diagramManifest.diagrams.find((entry) => entry.id === 'runtime');
    assert.ok(runtimeDiagram, 'Core Concepts mutation fixture must have the registered runtime diagram.');
    const runtimeImageName = path.posix.basename(runtimeDiagram.pngPath);
    await assertHtmlMutationRejects(diagramRoute, (html) => mutateHtmlDocument(html, (article) => {
      const diagram = [...article.querySelectorAll('img[src]')].find((element) => {
        const source = element.getAttribute('src') ?? '';
        return source.endsWith(`/diagrams/${runtimeImageName}`);
      });
      assert.ok(diagram, `Core Concepts mutation fixture must contain the registered ${runtimeDiagram.pngPath} image.`);
      diagram.setAttribute('alt', `${diagram.getAttribute('alt')}x`);
    }), 'A registered Archify diagram image-alt mutation must fail.');

    await assertHtmlMutationAccepts(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article, document) => {
      const hidden = document.createElement('p');
      hidden.hidden = true;
      hidden.textContent = 'hidden mutation';
      article.append(hidden);
    }), 'A hidden HTML mutation must remain outside the reader body.');
    await assertHtmlMutationAccepts(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article, document) => {
      const hidden = document.createElement('p');
      hidden.style.display = 'none';
      hidden.textContent = 'display-none mutation';
      article.append(hidden);
    }), 'A display-none HTML mutation must remain outside the reader body.');
    await assertHtmlMutationAccepts(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article, document) => {
      const node = findBodyTextNode(article);
      assert.ok(node, 'Visibility-initial fixture must contain an ordinary body text node.');
      const parent = node.parentNode;
      assert.ok(parent, 'Visibility-initial fixture text node must have a parent.');
      const hidden = document.createElement('span');
      hidden.style.visibility = 'hidden';
      const reset = document.createElement('span');
      reset.style.visibility = 'initial';
      hidden.append(document.createTextNode('hidden visibility sibling'));
      hidden.append(reset);
      parent.insertBefore(hidden, node);
      reset.append(node);
      const details = document.createElement('details');
      const first = document.createElement('summary');
      const second = document.createElement('summary');
      const closedBody = document.createElement('p');
      first.textContent = '';
      second.textContent = 'hidden second summary';
      closedBody.textContent = 'hidden closed details body';
      details.append(first, second, closedBody);
      article.append(details);
    }), 'Visibility initial and first direct closed-details summary controls must preserve the reader body.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article, document) => {
      const node = findBodyTextNode(article);
      assert.ok(node, 'Visibility-unset fixture must contain an ordinary body text node.');
      const hidden = document.createElement('span');
      hidden.style.visibility = 'hidden';
      const reset = document.createElement('span');
      reset.style.visibility = 'unset';
      hidden.append(reset);
      reset.append(node);
      article.append(hidden);
    }), 'Visibility unset must not reset a hidden ancestor.');
    await assertHtmlMutationRejects(htmlSwapTargetRoute, (html) => mutateHtmlDocument(html, (article, document) => {
      const visible = document.createElement('p');
      visible.textContent = 'visible mutation';
      article.append(visible);
    }), 'A visible HTML mutation must fail.');

    const scheduledRoute = '/operations/scheduled-operation';
    const scheduledHtmlPath = htmlPathForRoute(scheduledRoute);
    const scheduledBaseline = await readFile(scheduledHtmlPath, 'utf8');
    const scheduledPhrase = '毎日0時00分だけを対象にするため';
    assert.match(scheduledBaseline, new RegExp(scheduledPhrase, 'u'), 'Synthetic scheduled HTML must contain the corrected complete phrase.');
    await assertHtmlMutationRejects(scheduledRoute, (html) => html.replace(
      `${scheduledPhrase}、任意時刻に`,
      '毎日0、任意時刻に',
    ), 'The scheduled-operation old truncated phrase must fail semantic binding.');

    const fixtureManifestPath = fixturePipeline.manifestPath;
    const fixtureManifest = JSON.parse(await readFile(fixtureManifestPath, 'utf8'));
    const fixtureManifestBaseline = JSON.stringify(fixtureManifest, null, 2) + '\n';
    const firstNonLandingPage = fixtureManifest.pages.find((page) => page.source !== 'README.md');
    assert.ok(firstNonLandingPage, 'The synthetic manifest must contain a non-Landing page.');
    firstNonLandingPage.extra = 'unknown manifest member';
    await writeFile(fixtureManifestPath, `${JSON.stringify(fixtureManifest, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateFixture(),
      /closed .*schema|closed source\/generated\/slug\/title\/hash schema/,
      'An unknown manifest member must fail the closed canonical manifest contract.',
    );
    delete firstNonLandingPage.extra;
    await writeFile(fixtureManifestPath, fixtureManifestBaseline, 'utf8');

    const originalManifestTitle = firstNonLandingPage.title;
    firstNonLandingPage.title = `${originalManifestTitle} substituted`;
    await writeFile(fixtureManifestPath, `${JSON.stringify(fixtureManifest, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateFixture(),
      /title drifted from the Source H1/,
      'A substituted manifest title must fail the Source-derived title binding.',
    );
    firstNonLandingPage.title = originalManifestTitle;
    await writeFile(fixtureManifestPath, fixtureManifestBaseline, 'utf8');

    const fencedCanonicalPage = fixtureManifest.pages.find((page) => page.source !== 'README.md'
      && page.generated.endsWith('.md'));
    assert.ok(fencedCanonicalPage, 'The synthetic manifest must contain a Markdown page for fenced-code mutation.');
    const fencedCanonicalPath = path.join(fixturePipeline.contentRoot, ...fencedCanonicalPage.generated.split('/'));
    const fencedCanonicalBaseline = await readFile(fencedCanonicalPath, 'utf8');
    const fencedMatch = fencedCanonicalBaseline.match(/```[^\n]*\n([\s\S]*?)\n```/u);
    assert.ok(fencedMatch, 'The synthetic canonical Markdown must contain a fenced code block.');
    const fencedBodyStart = (fencedMatch.index ?? 0) + fencedMatch[0].indexOf(fencedMatch[1]);
    const fencedBodyOffset = fencedCanonicalBaseline.slice(fencedBodyStart, fencedBodyStart + fencedMatch[1].length).search(/[A-Za-z0-9]/u);
    assert.ok(fencedBodyOffset >= 0, 'The fenced canonical body must contain an ordinary byte.');
    const fencedMutationOffset = fencedBodyStart + fencedBodyOffset;
    const fencedOriginalByte = fencedCanonicalBaseline[fencedMutationOffset];
    const fencedReplacementByte = fencedOriginalByte === 'x' ? 'y' : 'x';
    const fencedCanonicalMutation = `${fencedCanonicalBaseline.slice(0, fencedMutationOffset)}${fencedReplacementByte}${fencedCanonicalBaseline.slice(fencedMutationOffset + 1)}`;
    assert.equal(Buffer.byteLength(fencedCanonicalBaseline), Buffer.byteLength(fencedCanonicalMutation),
      'The fenced-code mutation must preserve the canonical byte length.');
    const fencedManifestHash = fencedCanonicalPage.hash;
    const fencedSearchRoute = `/${fencedCanonicalPage.slug}`;
    const fencedSearchPath = path.join(artifactDirectory, 'blume-search.json');
    const fencedSearchBaseline = JSON.parse(await readFile(fencedSearchPath, 'utf8'))
      .find((record) => record.route === fencedSearchRoute)?.content;
    await writeFile(fencedCanonicalPath, fencedCanonicalMutation, 'utf8');
    fencedCanonicalPage.hash = createHash('sha256').update(fencedCanonicalMutation).digest('hex');
    await writeFile(fixtureManifestPath, `${JSON.stringify(fixtureManifest, null, 2)}\n`, 'utf8');
    const fencedSearchAfterMutation = JSON.parse(await readFile(fencedSearchPath, 'utf8'))
      .find((record) => record.route === fencedSearchRoute)?.content;
    assert.equal(fencedSearchAfterMutation, fencedSearchBaseline,
      'The fenced-code-only canonical mutation must be omitted by the Search fixture.');
    await assert.rejects(
      validateFixture(),
      /source-derived/,
      'A coherent canonical fenced-code body and manifest-hash mutation must fail Source binding.'
    );
    await writeFile(fencedCanonicalPath, fencedCanonicalBaseline, 'utf8');
    fencedCanonicalPage.hash = fencedManifestHash;
    await writeFile(fixtureManifestPath, fixtureManifestBaseline, 'utf8');

    const searchPath = path.join(artifactDirectory, 'blume-search.json');
    const search = JSON.parse(await readFile(searchPath, 'utf8'));
    const zeroPlaceholderRoutes = ['/concepts/why-blackops', '/getting-started/installation'];
    const zeroPlaceholderRecords = zeroPlaceholderRoutes.map((route) => search.find((record) => record.route === route));
    assert.ok(zeroPlaceholderRecords.every((record) => record), 'The zero-placeholder Search fixtures must exist.');
    assert.ok(zeroPlaceholderRecords.every((record) => placeholderInventory(record.content).total === 0), 'The zero-placeholder Search fixtures must remain placeholder-free.');
    const zeroPlaceholderContent = zeroPlaceholderRecords.map((record) => record.content);
    zeroPlaceholderRecords[0].content = zeroPlaceholderContent[1];
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /source-derived/,
      'A zero-placeholder route body swap must fail the exact Source-derived Search binding.',
    );
    zeroPlaceholderRecords[0].content = zeroPlaceholderContent[0];

    const sameMultisetBuckets = new Map();
    for (const record of search) {
      const key = JSON.stringify(placeholderInventory(record.content).counts);
      const bucket = sameMultisetBuckets.get(key) ?? [];
      bucket.push(record);
      sameMultisetBuckets.set(key, bucket);
    }
    const sameMultisetEntry = [...sameMultisetBuckets.entries()]
      .find(([key, bucket]) => Object.keys(JSON.parse(key)).length > 0 && bucket.length > 1 && bucket[0].content !== bucket[1].content);
    const sameMultisetPair = sameMultisetEntry?.[1];
    assert.ok(sameMultisetPair, 'A same-placeholder-multiset Search fixture must exist.');
    const [sameMultisetFirst, sameMultisetSecond] = sameMultisetPair;
    const sameMultisetContent = sameMultisetFirst.content;
    sameMultisetFirst.content = sameMultisetSecond.content;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /source-derived/,
      'A same-placeholder-multiset route body swap must fail the exact Source-derived Search binding.',
    );
    sameMultisetFirst.content = sameMultisetContent;

    const operation = search.find((record) => record.route === '/reference/project-cli');
    assert.ok(operation, 'A representative Search record must exist.');
    const originalContent = operation.content;
    const originalTitle = operation.title;
    const originalUrl = operation.url;
    operation.content = operation.content.replace('<operation-id>', '');
    operation.title = `${operation.title} <operation-id>`;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /drifted/,
      'A placeholder moved from Search content into title must fail content parity.',
    );
    operation.content = originalContent;
    operation.title = originalTitle;
    operation.url = '/swapped-route';
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /route\/url mismatch/,
      'A Search route/url swap must fail closed.',
    );
    operation.url = originalUrl;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    operation.content = originalContent.replace('<operation-id>', '<application-root>');
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /drifted/,
      'A balanced placeholder replacement must fail exact multiset parity.',
    );
    operation.content = originalContent;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    operation.content = operation.content.replace('<operation-id>', '<operation-id');
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /dangling/,
      'A missing closing marker must fail the full source/raw/Search path.',
    );
    operation.content = originalContent;
    operation.title = originalTitle;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');

    operation.content = originalContent.slice(0, -1);
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /source-derived/,
      'A one-character Search body deletion must fail the exact Source-derived binding.',
    );
    operation.content = originalContent;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');

    operation.content = `${originalContent}x`;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /source-derived/,
      'A one-character Search body addition must fail the exact Source-derived binding.',
    );
    operation.content = originalContent;
    await writeFile(searchPath, `${JSON.stringify(search, null, 2)}\n`, 'utf8');

    const manifestPagesBySource = new Map(fixtureManifest.pages
      .filter(({ source }) => source !== 'README.md')
      .map((page) => [page.source, page]));
    const rawPathForSource = (source) => {
      const page = manifestPagesBySource.get(source);
      assert.ok(page, `The synthetic manifest must map ${source}.`);
      return path.join(artifactDirectory, ...page.generated.split('/'));
    };
    const splitRaw = (text) => {
      const frontmatterMatch = text.match(/^---\n[\s\S]*?\n---\n/u);
      assert.ok(frontmatterMatch, 'Canonical generated Raw must contain frontmatter.');
      const markerMatch = text.match(/\n+(?:<!-- blackops-reader-outcome:[\s\S]*?-->|\{\/\* blackops-reader-outcome:[\s\S]*?\*\/\})\s*$/u);
      return {
        frontmatter: frontmatterMatch[0],
        body: text.slice(frontmatterMatch[0].length, markerMatch?.index ?? text.length),
        marker: markerMatch?.[0] ?? '',
      };
    };
    const rebuildRaw = (parts, body) => `${parts.frontmatter}${body}${parts.marker}`;
    const rawSwapTarget = 'why-blackops.md';
    const rawSwapDonor = 'installation.md';
    const rawSwapTargetPath = rawPathForSource(rawSwapTarget);
    const rawSwapTargetText = await readFile(rawSwapTargetPath, 'utf8');
    const rawSwapDonorText = await readFile(rawPathForSource(rawSwapDonor), 'utf8');
    const rawSwapTargetParts = splitRaw(rawSwapTargetText);
    const rawSwapDonorParts = splitRaw(rawSwapDonorText);
    assert.equal(placeholderInventory(rawSwapTargetText, { inlineCodeOnly: true }).total, 0,
      'Raw swap target must be a zero-placeholder page.');
    assert.equal(placeholderInventory(rawSwapDonorText, { inlineCodeOnly: true }).total, 0,
      'Raw swap donor must be a zero-placeholder page.');
    assert.notEqual(rawSwapTargetParts.body, rawSwapDonorParts.body, 'Raw swap fixtures must have distinct bodies.');

    await writeFile(rawSwapTargetPath, rebuildRaw(rawSwapTargetParts, rawSwapDonorParts.body), 'utf8');
    await assert.rejects(
      validateFixture(),
      /Raw .*byte-exact source-derived/,
      'A non-placeholder Raw body swap must fail the exact source-derived binding.',
    );
    await writeFile(rawSwapTargetPath, rawSwapTargetText, 'utf8');

    const ordinaryBodyIndex = rawSwapTargetParts.body.search(/[A-Za-z\u3040-\u30ff]/u);
    assert.ok(ordinaryBodyIndex >= 0, 'Raw swap target must contain an ordinary body character.');
    const addedBody = `${rawSwapTargetParts.body.slice(0, ordinaryBodyIndex + 1)}x${rawSwapTargetParts.body.slice(ordinaryBodyIndex + 1)}`;
    await writeFile(rawSwapTargetPath, rebuildRaw(rawSwapTargetParts, addedBody), 'utf8');
    await assert.rejects(
      validateFixture(),
      /Raw .*byte-exact source-derived/,
      'A one-character Raw body addition must fail the exact source-derived binding.',
    );
    await writeFile(rawSwapTargetPath, rawSwapTargetText, 'utf8');

    const deletedBody = `${rawSwapTargetParts.body.slice(0, ordinaryBodyIndex)}${rawSwapTargetParts.body.slice(ordinaryBodyIndex + 1)}`;
    await writeFile(rawSwapTargetPath, rebuildRaw(rawSwapTargetParts, deletedBody), 'utf8');
    await assert.rejects(
      validateFixture(),
      /Raw .*byte-exact source-derived/,
      'A one-character Raw body deletion must fail the exact source-derived binding.',
    );
    await writeFile(rawSwapTargetPath, rawSwapTargetText, 'utf8');

    const llmsFullPath = path.join(artifactDirectory, 'llms-full.txt');
    const llmsFullBaseline = await readFile(llmsFullPath, 'utf8');
    const llmsRouteSegments = (text, route) => {
      const segments = text.split('\n---\n\n');
      const sourceLine = `\nSource: https://docs.example.test${route}\n\n`;
      const index = segments.findIndex((segment) => segment.includes(sourceLine));
      assert.ok(index >= 0, `Synthetic llms-full must contain ${route}.`);
      const segment = segments[index];
      const bodyStart = segment.indexOf(sourceLine) + sourceLine.length;
      return { segments, index, segment, bodyStart, body: segment.slice(bodyStart) };
    };
    const updateLlmSegment = (text, route, update) => {
      const selected = llmsRouteSegments(text, route);
      selected.segments[selected.index] = update(selected.segment, selected.bodyStart, selected.body);
      return selected.segments.join('\n---\n\n');
    };
    const assertLlmMutationRejects = async (mutated, pattern, message) => {
      await writeFile(llmsFullPath, mutated, 'utf8');
      try {
        await assert.rejects(validateFixture(), pattern, message);
      } finally {
        await writeFile(llmsFullPath, llmsFullBaseline, 'utf8');
      }
    };
    const llmsZeroPlaceholderTarget = '/concepts/why-blackops';
    const llmsZeroPlaceholderDonor = '/getting-started/installation';
    const llmsTargetBody = llmsRouteSegments(llmsFullBaseline, llmsZeroPlaceholderTarget).body;
    const llmsDonorBody = llmsRouteSegments(llmsFullBaseline, llmsZeroPlaceholderDonor).body;
    assert.equal(placeholderInventory(llmsTargetBody, { inlineCodeOnly: true }).total, 0,
      'llms-full swap target must be a zero-placeholder page.');
    assert.equal(placeholderInventory(llmsDonorBody, { inlineCodeOnly: true }).total, 0,
      'llms-full swap donor must be a zero-placeholder page.');
    assert.notEqual(llmsTargetBody, llmsDonorBody, 'llms-full swap fixtures must have distinct bodies.');
    await assertLlmMutationRejects(
      updateLlmSegment(llmsFullBaseline, llmsZeroPlaceholderTarget, (segment, bodyStart) => `${segment.slice(0, bodyStart)}${llmsDonorBody}`),
      /body drifted from validated public Raw/,
      'A zero-placeholder llms-full body swap must fail exact public Raw binding.',
    );

    const llmsOrdinaryIndex = llmsTargetBody.search(/[A-Za-z\u3040-\u30ff]/u);
    assert.ok(llmsOrdinaryIndex >= 0, 'llms-full target must contain an ordinary body character.');
    await assertLlmMutationRejects(
      updateLlmSegment(llmsFullBaseline, llmsZeroPlaceholderTarget, (segment, bodyStart, body) => `${segment.slice(0, bodyStart)}${body.slice(0, llmsOrdinaryIndex + 1)}x${body.slice(llmsOrdinaryIndex + 1)}`),
      /body drifted from validated public Raw/,
      'A one-character llms-full body addition must fail exact public Raw binding.',
    );
    await assertLlmMutationRejects(
      updateLlmSegment(llmsFullBaseline, llmsZeroPlaceholderTarget, (segment, bodyStart, body) => `${segment.slice(0, bodyStart)}${body.slice(0, llmsOrdinaryIndex)}${body.slice(llmsOrdinaryIndex + 1)}`),
      /body drifted from validated public Raw/,
      'A one-character llms-full body deletion must fail exact public Raw binding.',
    );

    await assertLlmMutationRejects(
      updateLlmSegment(llmsFullBaseline, llmsZeroPlaceholderTarget, (segment) => segment.replace(/^# ([^\n]+)(\nSource:)/u, '# $1 substituted$2')),
      /title drifted from the canonical manifest/,
      'A substituted llms-full title must fail canonical manifest binding.',
    );
    await assertLlmMutationRejects(
      updateLlmSegment(llmsFullBaseline, llmsZeroPlaceholderTarget, (segment) => segment.replace(
        `Source: https://docs.example.test${llmsZeroPlaceholderTarget}`,
        `Source: https://docs.example.test${llmsZeroPlaceholderDonor}`,
      )),
      /(?:duplicate route|Source URL route drifted|route inventory)/,
      'A substituted llms-full Source route must fail canonical route binding.',
    );

    const rawPath = path.join(artifactDirectory, 'reference', 'project-cli.md');
    const raw = await readFile(rawPath, 'utf8');
    await writeFile(rawPath, raw.replace('<operation-id>', '<operation-id '), 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /(?:dangling|byte-exact source-derived)/,
      'Malformed raw Markdown placeholder fragments must fail closed.',
    );
    await writeFile(rawPath, raw, 'utf8');

    const htmlPath = path.join(artifactDirectory, ...contentMap['project-cli.md'].slug.split('/'), 'index.html');
    const html = await readFile(htmlPath, 'utf8');
    await writeFile(htmlPath, `${html}<p><operation-id</p>\n`, 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /(?:HTML (?:closing tag does not match|reader surface has an unclosed|(?:start )?tag)|Artifact contains dangling)/,
      'Malformed HTML placeholder fragments must fail closed.',
    );
    await writeFile(htmlPath, html, 'utf8');

    const llmsFull = await readFile(llmsFullPath, 'utf8');
    const llmsMarker = '<!-- blackops-reader-outcome: OperationをCLIへ公開し、Help、Human／JSON結果、Exit Codeを確認する。 -->';
    assert.ok(llmsFull.includes(llmsMarker));
    await writeFile(llmsFullPath, llmsFull.replace(llmsMarker, `<operation-id\n${llmsMarker}`), 'utf8');
    await assert.rejects(
      validateArtifactReaderContract({ contentMap, artifactDirectory, sourceDirectory }),
      /dangling <operation-id fragment/,
      'Malformed llms-full placeholder fragments must fail closed.',
    );
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('legacy backticks distinguish Markdown prose from executable code blocks', async () => {
  const fence = "`".repeat(3);
  const prose = [
    'Use `readonly`.',
    '値は`readonly`。',
    '（`readonly`）',
  ].join('\n');
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(prose, 'inline-prose.md'));

  const shellFence = [
    'A shell example follows.',
    fence + 'bash',
    'custom-tool prefix `env` suffix',
    fence,
  ].join('\n');
  assert.throws(() => assertNoUnsafeLgtmDiagnostics(shellFence, 'inline-shell-fence.md'), /LGTM diagnostics/);

  const nonShellFence = [
    fence + 'text',
    'custom-tool prefix `env` suffix',
    fence,
  ].join('\n');
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(nonShellFence, 'inline-text-fence.md'));

  const nonShellFenceIndentedProse = [
    fence + 'text',
    '    `readonly`',
    fence,
  ].join('\n');
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(nonShellFenceIndentedProse, 'inline-text-fence-indented-prose.md'));

  const nonShellFenceIndentedEnvironment = [
    fence + 'text',
    '    `env`',
    fence,
  ].join('\n');
  assert.doesNotThrow(() => assertNoUnsafeLgtmDiagnostics(nonShellFenceIndentedEnvironment, 'inline-text-fence-indented-environment.md'));

  const outcome = contentMap['installation.md'].reader.outcome;
  const commonMarkContainerFixtures = [
    [
      'paragraph-continuation',
      'Paragraph text\n    Use `env` as the command name.',
      '<p>Paragraph text\n    Use <code>env</code> as the command name.</p>\n',
      true,
    ],
    [
      'paragraph-continuation-protected-printenv',
      'Paragraph text\n    Run `printenv GRAFANA_PASSWORD`.',
      '<p>Paragraph text\n    Run <code>printenv GRAFANA_PASSWORD</code>.</p>\n',
      false,
    ],
    [
      'paragraph-continuation-printenv-dump',
      'Paragraph text\n    Run `printenv`.',
      '<p>Paragraph text\n    Run <code>printenv</code>.</p>\n',
      false,
    ],
    [
      'paragraph-continuation-set-dump',
      'Paragraph text\n    Run `set`.',
      '<p>Paragraph text\n    Run <code>set</code>.</p>\n',
      false,
    ],
    [
      'paragraph-continuation-readonly-dump',
      'Paragraph text\n    Run `readonly`.',
      '<p>Paragraph text\n    Run <code>readonly</code>.</p>\n',
      false,
    ],
    [
      'paragraph-continuation-env-dump',
      'Paragraph text\n    Run `env`.',
      '<p>Paragraph text\n    Run <code>env</code>.</p>\n',
      false,
    ],
    [
      'list-continuation',
      '- Paragraph text\n    Use `env` as the command name.',
      '<ul>\n<li>Paragraph text\n  Use <code>env</code> as the command name.</li>\n</ul>\n',
      true,
    ],
    [
      'list-lazy-continuation',
      '- Paragraph text\nUse `env` as the command name.',
      '<ul>\n<li>Paragraph text\nUse <code>env</code> as the command name.</li>\n</ul>\n',
      true,
    ],
    [
      'list-lazy-continuation-protected-declare',
      '- Paragraph text\nRun `declare -p GRAFANA_PASSWORD`.',
      '<ul>\n<li>Paragraph text\nRun <code>declare -p GRAFANA_PASSWORD</code>.</li>\n</ul>\n',
      false,
    ],
    [
      'list-continuation-declare-dump',
      '- Paragraph text\n    Run `declare -p`.',
      '<ul>\n<li>Paragraph text\n  Run <code>declare -p</code>.</li>\n</ul>\n',
      false,
    ],
    [
      'list-lazy-export-dump',
      '- Paragraph text\nRun `export -p`.',
      '<ul>\n<li>Paragraph text\nRun <code>export -p</code>.</li>\n</ul>\n',
      false,
    ],
    [
      'list-lazy-named-declaration',
      '- Paragraph text\nUse `declare GRAFANA_PASSWORD` as a declaration.',
      '<ul>\n<li>Paragraph text\nUse <code>declare GRAFANA_PASSWORD</code> as a declaration.</li>\n</ul>\n',
      true,
    ],
    [
      'list-after-blank-exit',
      '- Paragraph text\n\nRun `env`.',
      '<ul>\n<li><p>Paragraph text</p>\n</li>\n</ul>\n<p>Run <code>env</code>.</p>\n',
      false,
    ],
    [
      'unordered-marker-env-dump',
      '- Run `env`.',
      '<ul>\n<li>Run <code>env</code>.</li>\n</ul>\n',
      false,
    ],
    [
      'ordered-marker-env-dump',
      '1. Run `env`.',
      '<ol>\n<li>Run <code>env</code>.</li>\n</ol>\n',
      false,
    ],
    [
      'unordered-marker-printenv-dump',
      '- Run `printenv`.',
      '<ul>\n<li>Run <code>printenv</code>.</li>\n</ul>\n',
      false,
    ],
    [
      'ordered-marker-printenv-dump',
      '1. Run `printenv`.',
      '<ol>\n<li>Run <code>printenv</code>.</li>\n</ol>\n',
      false,
    ],
    [
      'unordered-marker-declare-dump',
      '- Run `declare -p`.',
      '<ul>\n<li>Run <code>declare -p</code>.</li>\n</ul>\n',
      false,
    ],
    [
      'ordered-marker-declare-dump',
      '1. Run `declare -p`.',
      '<ol>\n<li>Run <code>declare -p</code>.</li>\n</ol>\n',
      false,
    ],
    [
      'unordered-marker-technical-prose',
      '- Use `env` as prose.',
      '<ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n',
      true,
    ],
    [
      'ordered-marker-technical-prose',
      '1. Use `env` as prose.',
      '<ol>\n<li>Use <code>env</code> as prose.</li>\n</ol>\n',
      true,
    ],
    [
      'nested-unordered-technical-prose',
      '- - Use `env` as prose.',
      '<ul>\n<li><ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n</li>\n</ul>\n',
      true,
    ],
    [
      'nested-unordered-env-dump',
      '- - Run `env`.',
      '<ul>\n<li><ul>\n<li>Run <code>env</code>.</li>\n</ul>\n</li>\n</ul>\n',
      false,
    ],
    [
      'nested-ordered-technical-prose',
      '- 1. Use `env` as prose.',
      '<ul>\n<li><ol>\n<li>Use <code>env</code> as prose.</li>\n</ol>\n</li>\n</ul>\n',
      true,
    ],
    [
      'nested-ordered-printenv-dump',
      '- 1. Run `printenv`.',
      '<ul>\n<li><ol>\n<li>Run <code>printenv</code>.</li>\n</ol>\n</li>\n</ul>\n',
      false,
    ],
    [
      'nested-reversed-technical-prose',
      '1. - Use `env` as prose.',
      '<ol>\n<li><ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n</li>\n</ol>\n',
      true,
    ],
    [
      'nested-reversed-declare-dump',
      '1. - Run `declare -p`.',
      '<ol>\n<li><ul>\n<li>Run <code>declare -p</code>.</li>\n</ul>\n</li>\n</ol>\n',
      false,
    ],
    [
      'nested-blockquote-technical-prose',
      '> - - Use `env` as prose.',
      '<blockquote>\n<ul>\n<li><ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n</li>\n</ul>\n</blockquote>\n',
      true,
    ],
    [
      'nested-blockquote-env-dump',
      '> - - Run `env`.',
      '<blockquote>\n<ul>\n<li><ul>\n<li>Run <code>env</code>.</li>\n</ul>\n</li>\n</ul>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-continuation',
      '> Paragraph text\n>     Use `env` as the command name.',
      '<blockquote>\n<p>Paragraph text\n    Use <code>env</code> as the command name.</p>\n</blockquote>\n',
      true,
    ],
    [
      'blockquote-lazy-indented-continuation',
      '> Paragraph text\n    Use `env` as the command name.',
      '<blockquote>\n<p>Paragraph text\n    Use <code>env</code> as the command name.</p>\n</blockquote>\n',
      true,
    ],
    [
      'blockquote-lazy-continuation',
      '> Paragraph text\nUse `env` as the command name.',
      '<blockquote>\n<p>Paragraph text\nUse <code>env</code> as the command name.</p>\n</blockquote>\n',
      true,
    ],
    [
      'blockquote-lazy-continuation-protected-inspect',
      '> Paragraph text\nRun `docker inspect "$LGTM"`.',
      '<blockquote>\n<p>Paragraph text\nRun <code>docker inspect "$LGTM"</code>.</p>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-lazy-continuation-protected-secret',
      '> Paragraph text\n    Run `printf "%s" "$GRAFANA_PASSWORD"`.',
      '<blockquote>\n<p>Paragraph text\n    Run <code>printf "%s" "$GRAFANA_PASSWORD"</code>.</p>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-continuation-export-dump',
      '> Paragraph text\n>     Run `export -p`.',
      '<blockquote>\n<p>Paragraph text\n    Run <code>export -p</code>.</p>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-lazy-typeset-dump',
      '> Paragraph text\nRun `typeset`.',
      '<blockquote>\n<p>Paragraph text\nRun <code>typeset</code>.</p>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-after-blank-exit',
      '> Paragraph text\n\nRun `env`.',
      '<blockquote>\n<p>Paragraph text</p>\n</blockquote>\n<p>Run <code>env</code>.</p>\n',
      false,
    ],
    [
      'blockquote-shell-fence',
      '> ```bash\n> custom prefix `env` suffix\n> ```',
      '<blockquote>\n<pre><code class="language-bash">custom prefix `env` suffix\n</code></pre>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-indented-shell',
      '>     custom prefix `env` suffix',
      '<blockquote>\n<pre><code>custom prefix `env` suffix\n</code></pre>\n</blockquote>\n',
      false,
    ],
    [
      'blockquote-text-fence-exit-negative',
      '> ```text\n> sample\nRun `env`.',
      '<blockquote>\n<pre><code class="language-text">sample\n</code></pre>\n</blockquote>\n<p>Run <code>env</code>.</p>\n',
      false,
    ],
    [
      'blockquote-shell-fence-exit-prose',
      '> ```bash\n> printf ok\nUse `readonly` as a PHP keyword.',
      '<blockquote>\n<pre><code class="language-bash">printf ok\n</code></pre>\n</blockquote>\n<p>Use <code>readonly</code> as a PHP keyword.</p>\n',
      true,
    ],
    [
      'list-nonshell-fence',
      '- Example:\n    ```text\n        `env`\n    ```',
      '<ul>\n<li>Example:<pre><code class="language-text">    `env`\n</code></pre>\n</li>\n</ul>\n',
      true,
    ],
    [
      'list-blankline-nonshell-fence',
      '- Example:\n\n    ```text\n    Use `env` as the command name.\n    ```',
      '<ul>\n<li><p>Example:</p>\n<pre><code class="language-text">Use `env` as the command name.\n</code></pre>\n</li>\n</ul>\n',
      true,
    ],
    [
      'list-blankline-paragraph-four-space',
      '- Example:\n\n    Use `env` as the command name.',
      '<ul>\n<li><p>Example:</p>\n<p>  Use <code>env</code> as the command name.</p>\n</li>\n</ul>\n',
      true,
    ],
    [
      'list-blankline-code-six-space',
      '- Example:\n\n      custom prefix `env` suffix',
      '<ul>\n<li><p>Example:</p>\n<pre><code>custom prefix `env` suffix\n</code></pre>\n</li>\n</ul>\n',
      false,
    ],
    [
      'unordered-marker-padding-one-space',
      '- Example\n\n    Use `env` as prose.',
      '<ul>\n<li><p>Example</p>\n<p>  Use <code>env</code> as prose.</p>\n</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-marker-padding-four-space',
      '-    Use `env` as prose.',
      '<ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-marker-padding-five-space',
      '-     Example\n\n      custom prefix `env` suffix',
      '<ul>\n<li><pre><code>Example\n\ncustom prefix `env` suffix\n</code></pre>\n</li>\n</ul>\n',
      false,
    ],
    [
      'ordered-blankline-paragraph-four-space',
      '1. Example:\n\n    Use `env` as the command name.',
      '<ol>\n<li><p>Example:</p>\n<p> Use <code>env</code> as the command name.</p>\n</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-blankline-code-seven-space',
      '1. Example:\n\n       custom prefix `env` suffix',
      '<ol>\n<li><p>Example:</p>\n<pre><code>custom prefix `env` suffix\n</code></pre>\n</li>\n</ol>\n',
      false,
    ],
    [
      'ordered-marker-padding-one-space',
      '1. Example\n\n    Use `env` as prose.',
      '<ol>\n<li><p>Example</p>\n<p> Use <code>env</code> as prose.</p>\n</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-marker-padding-four-space',
      '1.    Use `env` as prose.',
      '<ol>\n<li>Use <code>env</code> as prose.</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-marker-padding-five-space',
      '1.     Example\n\n       custom prefix `env` suffix',
      '<ol>\n<li><pre><code>Example\n\ncustom prefix `env` suffix\n</code></pre>\n</li>\n</ol>\n',
      false,
    ],
    [
      'unordered-marker-tab-padding',
      '-\tUse `env` as prose.',
      '<ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-marker-space-tab-padding',
      '- \tUse `env` as prose.',
      '<ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-marker-two-space-tab-padding',
      '-  \tUse `env` as prose.',
      '<ul>\n<li>Use <code>env</code> as prose.</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-marker-three-space-tab-code',
      '-   \tcustom prefix `env` suffix',
      '<ul>\n<li>\n<pre><code>  custom prefix `env` suffix\n</code></pre>\n</li>\n</ul>\n',
      false,
    ],
    [
      'ordered-marker-tab-padding',
      '1.\tUse `env` as prose.',
      '<ol>\n<li>Use <code>env</code> as prose.</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-marker-space-tab-padding',
      '1. \tUse `env` as prose.',
      '<ol>\n<li>Use <code>env</code> as prose.</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-marker-two-space-tab-code',
      '1.  \tcustom prefix `env` suffix',
      '<ol>\n<li>\n<pre><code> custom prefix `env` suffix\n</code></pre>\n</li>\n</ol>\n',
      false,
    ],
    [
      'unordered-tab-marker-blankline-four-space-prose',
      '-\tExample\n\n    Use `env` as prose.',
      '<ul>\n<li>\n<p>Example</p>\n<p>Use <code>env</code> as prose.</p>\n</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-tab-marker-blankline-seven-space-prose',
      '-\tExample\n\n       Use `env` as prose.',
      '<ul>\n<li><p>Example</p>\n<p>Use <code>env</code> as prose.</p>\n</li>\n</ul>\n',
      true,
    ],
    [
      'unordered-tab-marker-blankline-eight-space-code',
      '-\tExample\n\n        custom prefix `env` suffix',
      '<ul>\n<li><p>Example</p>\n<pre><code>custom prefix `env` suffix\n</code></pre>\n</li>\n</ul>\n',
      false,
    ],
    [
      'ordered-tab-marker-blankline-five-space-prose',
      '1.\tExample\n\n     Use `env` as prose.',
      '<ol>\n<li><p>Example</p>\n<p>Use <code>env</code> as prose.</p>\n</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-tab-marker-blankline-seven-space-prose',
      '1.\tExample\n\n       Use `env` as prose.',
      '<ol>\n<li><p>Example</p>\n<p>Use <code>env</code> as prose.</p>\n</li>\n</ol>\n',
      true,
    ],
    [
      'ordered-tab-marker-blankline-eight-space-code',
      '1.\tExample\n\n        custom prefix `env` suffix',
      '<ol>\n<li><p>Example</p>\n<pre><code>custom prefix `env` suffix\n</code></pre>\n</li>\n</ol>\n',
      false,
    ],
  ];
  for (const [name, source, syntheticHtml, safe] of commonMarkContainerFixtures) {
    const sourceLocation = `interactive-lgtm-commonmark-${name}.md`;
    const htmlLocation = `interactive-lgtm-commonmark-${name}.html`;
    const assertFixture = (value, location, artifact = false) => {
      const check = () => (artifact
        ? assertArtifactReaderText('description: ' + outcome + '\n' + value, { outcome, location })
        : assertNoUnsafeLgtmDiagnostics(value, location));
      if (safe) assert.doesNotThrow(check);
      else assert.throws(check, /LGTM diagnostics/);
    };
    assertFixture(source, sourceLocation);
    assertFixture(source, sourceLocation, true);
    const rendered = (await satteriMarkdownProcessor.render(source)).code;
    assertFixture(rendered, htmlLocation);
    assertFixture(rendered, htmlLocation, true);
    assertFixture(syntheticHtml, `${htmlLocation}-synthetic`, false);
    assertFixture(syntheticHtml, `${htmlLocation}-synthetic`, true);
  }

  const markerCodeWithOutcome = `description: ${outcome}\n-   \tcustom prefix \`env\` suffix`;
  assert.throws(() => assertArtifactReaderText(markerCodeWithOutcome, {
    outcome,
    location: 'interactive-lgtm-commonmark-marker-code-prefixed-artifact.md',
  }), /LGTM diagnostics/);
  const markerProseWithOutcome = `description: ${outcome}\n- \tUse \`env\` as prose.`;
  assert.doesNotThrow(() => assertArtifactReaderText(markerProseWithOutcome, {
    outcome,
    location: 'interactive-lgtm-commonmark-marker-prose-prefixed-artifact.md',
  }));

  const topLevelIndentedFixtures = [
    ['env', '    env', '<pre><code>env\n</code></pre>\n'],
    ['protected-dump', '    declare -p GRAFANA_PASSWORD', '<pre><code>declare -p GRAFANA_PASSWORD\n</code></pre>\n'],
  ];
  for (const [name, source, html] of topLevelIndentedFixtures) {
    const sourceLocation = `interactive-lgtm-top-level-indented-${name}.md`;
    const htmlLocation = `interactive-lgtm-top-level-indented-${name}.html`;
    assert.throws(() => assertNoUnsafeLgtmDiagnostics(source, sourceLocation), /LGTM diagnostics/);
    assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + source, { outcome, location: sourceLocation }), /LGTM diagnostics/);
    assert.throws(() => assertNoUnsafeLgtmDiagnostics(html, htmlLocation), /LGTM diagnostics/);
    assert.throws(() => assertArtifactReaderText('description: ' + outcome + '\n' + html, { outcome, location: htmlLocation }), /LGTM diagnostics/);
  }

  const indentedShell = '    custom-tool prefix `env` suffix';
  assert.throws(() => assertNoUnsafeLgtmDiagnostics(indentedShell, 'inline-indented-shell.md'), /LGTM diagnostics/);
});

test('artifact fixtures reject foreign outcome, protected decode, and stale availability injection', () => {
  const outcome = contentMap['installation.md'].reader.outcome;
  const foreign = contentMap['mvp-sample.md'].reader.outcome;
  assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}`, { outcome, outcomes: [outcome, foreign], location: 'positive-artifact' }));
  assert.throws(() => assertArtifactReaderText(`description: ${foreign}`, { outcome, outcomes: [outcome, foreign], location: 'foreign-artifact' }), /missing its mapped reader outcome/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nStable 1.2.0 is main-only.`, { outcome, location: 'stale-artifact' }), /main-only/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nStable 1.2.0はmainでは提供されない機能だけです。`, { outcome, location: 'stale-artifact-mainでは' }), /main-only/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nStable 1.2.0はmainのbuild:compileだけで利用できます。`, { outcome, location: 'stale-artifact-main-build' }), /main-only/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nStable 1.2.0はmain Sourceだけを現行手順に使います。`, { outcome, location: 'stale-artifact-main-source' }), /main-only/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nconvert_from(encoded_record, 'UTF8')`, { outcome, location: 'protected-artifact' }), /Protected Blob/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n## Stable／main境界`, { outcome, location: 'stale-heading-artifact' }), /main-only/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n### prefix Repository main Preview`, { outcome, location: 'stale-preview-prefix-artifact' }), /exact heading/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n### Repository main Preview suffix`, { outcome, location: 'stale-preview-suffix-artifact' }), /exact heading/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n## Repository main Preview`, { outcome, location: 'stale-preview-h2-artifact' }), /exact heading/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n#### Repository main Preview`, { outcome, location: 'stale-preview-h4-artifact' }), /exact heading/);
  assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n<h3 id="repository-main-preview"><a href="#repository-main-preview">Repository main Preview</a></h3>`, { outcome, location: 'positive-preview-html-h3-artifact' }));
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n<h2 id="repository-main-preview">Repository main Preview</h2>`, { outcome, location: 'stale-preview-html-h2-artifact' }), /exact heading/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n<h4 id="repository-main-preview">Repository main Preview</h4>`, { outcome, location: 'stale-preview-html-h4-artifact' }), /exact heading/);
  assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n<a href="#repository-main-preview">Repository main Preview</a>`, { outcome, location: 'positive-preview-anchor-artifact' }));
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nprefix Repository main Preview`, { outcome, location: 'stale-preview-plain-prefix-artifact' }), /anchored unit/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nRepository main Preview suffix`, { outcome, location: 'stale-preview-plain-suffix-artifact' }), /anchored unit/);
  assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n{"title":"Repository main Preview","href":"#repository-main-preview"}`, { outcome, location: 'positive-preview-search-artifact' }));
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n{"title":"prefix Repository main Preview","href":"#repository-main-preview"}`, { outcome, location: 'stale-preview-search-prefix-artifact' }), /anchored unit/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n{"title":"Repository main Preview suffix","href":"#repository-main-preview"}`, { outcome, location: 'stale-preview-search-suffix-artifact' }), /anchored unit/);
  for (const fragment of ['#repository-main-preview-suffix', '#repository-main-preview?x=1', '#repository-main-preview/extra', '#REPOSITORY-MAIN-PREVIEW']) {
    assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n{"title":"Repository main Preview","href":"${fragment}"}`, { outcome, location: `stale-preview-search-fragment-${fragment}` }), /anchored unit/);
  }
  assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n<a href="#repository-main-preview">Repository main Preview</a>`, { outcome, location: 'positive-preview-toc-artifact' }));
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n<a href="#repository-main-preview">prefix Repository main Preview</a>`, { outcome, location: 'stale-preview-anchor-prefix-artifact' }), /anchored unit/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n<a href="#repository-main-preview">Repository main Preview suffix</a>`, { outcome, location: 'stale-preview-anchor-suffix-artifact' }), /anchored unit/);
  assert.doesNotThrow(() => assertArtifactReaderText(`description: ${outcome}\n[Repository main Preview](#repository-main-preview)`, { outcome, location: 'positive-preview-llm-artifact' }));
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n[prefix Repository main Preview](#repository-main-preview)`, { outcome, location: 'stale-preview-llm-prefix-artifact' }), /anchored unit/);
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n[Repository main Preview suffix](#repository-main-preview)`, { outcome, location: 'stale-preview-llm-suffix-artifact' }), /anchored unit/);
  for (const variant of ['Repository  main Preview', 'repository main preview', 'Repository-main-Preview']) {
    assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n${variant}`, { outcome, location: `stale-preview-plain-variant-${variant}` }), /anchored unit/);
  }
  assert.throws(() => assertArtifactReaderText(`description: ${outcome}\n<h3 id="other">Repository&nbsp;main Preview</h3>`, { outcome, location: 'stale-preview-html-space-artifact' }), /exact heading/);
  assert.throws(() => assertNoInternalEvidenceVoice('Consumer E2E is the evidence.', 'negative-artifact'), /Internal evidence voice/);
  for (const phrase of ['Remote create-project smoke', 'Local／CI only', 'Real Browser E2E', 'Local／CI Build']) {
    assert.throws(() => assertNoInternalEvidenceVoice(`Current release uses ${phrase}.`, 'negative-release-voice'), /Internal evidence voice/);
    assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nCurrent release uses ${phrase}.`, { outcome, location: `negative-release-artifact-${phrase}` }), /Internal evidence voice/);
  }
  for (const phrase of ['Local／CIだけで検証', 'Local/CIで確認', 'ローカル／CI検証', 'ローカル/CIでテスト', 'ローカルとCIのみで再現', 'Repository CIで検証']) {
    assert.throws(() => assertNoInternalEvidenceVoice(`Applicationは${phrase}します。`, 'negative-japanese-release-voice'), /Internal evidence voice/);
    assert.throws(() => assertArtifactReaderText(`description: ${outcome}\nApplicationは${phrase}します。`, { outcome, location: `negative-japanese-release-artifact-${phrase}` }), /Internal evidence voice/);
  }
  assert.doesNotThrow(() => assertNoInternalEvidenceVoice('公開PackageのComposer／Generator更新は、実際のannotated Tag `1.1.0`を起点にしたFramework Update Consumerで検証済みです。', 'mvp-status.md'));
});

test('artifact route inventories reject unknown Search and llms routes', () => {
  const expected = new Set(['/getting-started/installation']);
  assert.doesNotThrow(() => validateSearchRouteInventory([{ route: '/getting-started/installation' }], expected));
  assert.throws(() => validateSearchRouteInventory([{ route: '/getting-started/installation' }, { route: '/unknown' }], expected), /unknown route/);
  assert.doesNotThrow(() => validateLlmRouteInventory('- [Install](/getting-started/installation): outcome', expected));
  assert.throws(() => validateLlmRouteInventory('- [Install](/getting-started/installation): outcome\n- [Unknown](/unknown): stale', expected), /unknown route/);
});

test('full artifact route inventory rejects unknown raw Markdown and HTML paths', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-'));
  try {
    await mkdir(path.join(temporary, 'getting-started/installation'), { recursive: true });
    await mkdir(path.join(temporary, 'unknown'), { recursive: true });
    await writeFile(path.join(temporary, 'getting-started/installation.md'), 'raw', 'utf8');
    await writeFile(path.join(temporary, 'getting-started/installation/index.html'), 'html', 'utf8');
    await writeFile(path.join(temporary, 'unknown.md'), 'extra raw', 'utf8');
    await writeFile(path.join(temporary, 'unknown/index.html'), 'extra html', 'utf8');
    const expected = new Set(['/getting-started/installation']);
    await assert.rejects(validateArtifactPageRouteInventory({ artifactDirectory: temporary, expectedRoutes: expected }), /Raw Markdown artifact contains unknown route.*unknown/);
    await rm(path.join(temporary, 'unknown.md'));
    await assert.rejects(validateArtifactPageRouteInventory({ artifactDirectory: temporary, expectedRoutes: expected }), /HTML artifact contains unknown route.*unknown/);
    await rm(path.join(temporary, 'unknown/index.html'));
    await writeFile(path.join(temporary, '404.html'), 'not found', 'utf8');
    await assert.doesNotReject(validateArtifactPageRouteInventory({ artifactDirectory: temporary, expectedRoutes: expected }));
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('full artifact route inventory admits only manifest-registered supplemental viewers', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-diagram-contract-'));
  try {
    await mkdir(path.join(temporary, 'getting-started/installation'), { recursive: true });
    await mkdir(path.join(temporary, 'diagrams'), { recursive: true });
    await writeFile(path.join(temporary, 'getting-started/installation.md'), 'raw', 'utf8');
    await writeFile(path.join(temporary, 'getting-started/installation/index.html'), 'html', 'utf8');
    await writeFile(path.join(temporary, 'diagrams/runtime.html'), '<!doctype html>viewer', 'utf8');
    const manifestPath = path.join(temporary, 'manifest.json');
    await writeFile(manifestPath, `${JSON.stringify(await loadDiagramManifest(), null, 2)}\n`, 'utf8');
    const expected = new Set(['/getting-started/installation']);
    await assert.doesNotReject(validateArtifactPageRouteInventory({
      artifactDirectory: temporary,
      expectedRoutes: expected,
      diagramManifestPath: manifestPath,
    }));
    await writeFile(path.join(temporary, 'diagrams/unknown.html'), '<!doctype html>unknown', 'utf8');
    await assert.rejects(
      validateArtifactPageRouteInventory({ artifactDirectory: temporary, expectedRoutes: expected, diagramManifestPath: manifestPath }),
      /unknown flat viewer path/,
    );
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('artifact reader claims scan registered viewer text without scanning viewer runtime scripts', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-diagram-claims-'));
  const artifactDirectory = path.join(temporary, 'synthetic-artifact');
  try {
    const fixturePipeline = await writeSyntheticCompleteReaderArtifact({ artifactDirectory, contentMap });
    const diagramManifestPath = path.join(temporary, 'diagram-manifest.json');
    await writeFile(diagramManifestPath, `${JSON.stringify(await loadDiagramManifest(), null, 2)}\n`, 'utf8');
    const viewerPath = path.join(artifactDirectory, 'diagrams/runtime.html');
    await mkdir(path.dirname(viewerPath), { recursive: true });
    const viewer = '<!doctype html><html lang="en"><head><title>Runtime diagram</title><script>function focusDiagram(main) { bringNodeIntoWindow(main); }</script></head><body><main><figure><figcaption>Runtime relationships</figcaption><svg role="img"><text>Runtime</text></svg></figure></main></body></html>\n';
    await writeFile(viewerPath, viewer, 'utf8');
    const validateFixture = () => validateArtifactReaderContract({
      contentMap,
      artifactDirectory,
      manifestPath: fixturePipeline.manifestPath,
      contentRoot: fixturePipeline.contentRoot,
      diagramManifestPath,
    });
    await assert.doesNotReject(validateFixture, 'Viewer runtime identifiers must not be treated as reader claims.');

    await writeFile(viewerPath, viewer.replace('Runtime relationships', 'Stable 1.2.0 is main-only.'), 'utf8');
    await assert.rejects(
      validateFixture(),
      /Current Stable main-only availability claim is forbidden in diagrams\/runtime\.html/,
      'A visible viewer caption must remain subject to the current-release claim guard.',
    );
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('Troubleshooting classification rejects unclassified, symptom-only, and FAQ/group drift', async () => {
  const markdown = await readFile(path.join(repositoryRoot, 'docs/guide/troubleshooting.md'), 'utf8');
  const sourceTexts = new Map([['troubleshooting.md', markdown]]);
  const unclassifiedText = markdown.replace('# Troubleshooting', '# Troubleshooting\n\n## Unclassified diagnostic');
  assert.throws(() => validateReaderContract(contentMap, { sourceTexts: new Map([['troubleshooting.md', unclassifiedText]]) }), /unclassified/);

  const symptomOnlyText = markdown.replace(/\n\*\*考えられる原因:\*\*/u, '\n**原因を省略:**');
  assert.throws(() => validateReaderContract(contentMap, { sourceTexts: new Map([['troubleshooting.md', symptomOnlyText]]) }), /missing 原因/);

  const wrongClassification = structuredClone(contentMap);
  wrongClassification['troubleshooting.md'].reader.roles.diagnostics.push({ heading: 'FAQ: 202は完了を意味しますか', classification: 'diagnostic' });
  wrongClassification['troubleshooting.md'].reader.roles.faq.shift();
  assert.throws(() => validateReaderContract(wrongClassification, { sourceTexts }), /must be explicitly classified as diagnostic|classified more than once|missing 症状|FAQ\/group heading/);
});

test('source-derived Reference fixtures require exact signature lookup fields', () => {
  const sourceType = 'BlackOps\\Example';
  const sourceAttribute = 'BlackOps\\Attribute\\Example';
  const coreApi = [
    '### Source-derived lookup fields',
    'Signature Parameter Return Default Error Typical Use Source-derived exact signature index',
    '### Source-derived exact signature index',
    '#### Namespace `BlackOps`',
    '| Type | Signature | Parameter | Return | Default | Error / Safe Code (source-observed) | Typical Use | Enum backing / cases | Public constants (name / type / value) |',
    '| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
    `| \`${sourceType}\` | Example::run(string $value = "x"): string | run: string $value = "x" | run: string | run.value="x" | run: Source body: no direct throw or bounded helper error observed (non-exhaustive) | \`Example::run()\`を呼ぶ | not an enum (source-derived) | none (source-derived) |`,
  ].join('\n');
  const attributes = [
    `| Attribute | 用途 | 付与対象 | 最小例 |`,
    '| --- | --- | --- | --- |',
    `| \`${sourceAttribute}\` | Example | Class | \`#[Example]\` |`,
  ].join('\n');
  const noError = 'Source body: no direct throw or bounded helper error observed (non-exhaustive)';
  const base = { coreApi, attributes, cli: '`demo:run`', configuration: '`app.php`', publicTypes: [{ name: sourceType, kind: 'class', methods: [{ name: 'run', parameters: 'string $value = "x"', returnType: 'string', errorContract: noError }] }], publicAttributes: [sourceAttribute], publicCommands: ['demo:run'], configurationKeys: ['app'] };
  assert.doesNotThrow(() => validateReferenceDocumentation(base));
  const drifted = { ...base, coreApi: coreApi.replace('Example::run(string', 'Example::broken(string') };
  assert.throws(() => validateReferenceDocumentation(drifted), /exact signature index is missing/);
  const wrongParameter = { ...base, coreApi: coreApi.replace('run: string $value = "x"', 'run: int $value = "x"') };
  assert.throws(() => validateReferenceDocumentation(wrongParameter), /parameter lookup drifted/);
  const wrongError = { ...base, coreApi: coreApi.replace(noError, 'Error placeholder') };
  assert.throws(() => validateReferenceDocumentation(wrongError), /Error／Safe Code(?: Method mapping)? .*drifted/);
  const wrongEnum = { ...base, coreApi: coreApi.replace('not an enum (source-derived)', 'string; Run=run') };
  assert.throws(() => validateReferenceDocumentation(wrongEnum), /enum backing／case lookup drifted/);
  const wrongConstant = { ...base, coreApi: coreApi.replace('none (source-derived)', 'RUN: string=run') };
  assert.throws(() => validateReferenceDocumentation(wrongConstant), /public constant lookup drifted/);
  const helperError = 'propagates InvalidArgumentException via missing()';
  const helperBase = {
    ...base,
    coreApi: coreApi.replace(noError, helperError),
    publicTypes: [{ ...base.publicTypes[0], methods: [{ ...base.publicTypes[0].methods[0], errorContract: helperError }] }],
  };
  assert.doesNotThrow(() => validateReferenceDocumentation(helperBase));
  const helperDrift = { ...helperBase, coreApi: helperBase.coreApi.replace(helperError, noError) };
  assert.throws(() => validateReferenceDocumentation(helperDrift), /Error／Safe Code(?: Method mapping)? .*drifted/);
  const unrelatedStaticCall = { ...base, coreApi: coreApi.replace(noError, 'safe AuthenticationResult::anonymous()') };
  assert.throws(() => validateReferenceDocumentation(unrelatedStaticCall), /Error／Safe Code(?: Method mapping)? .*drifted/);
  const duplicateCatalog = {
    ...base,
    coreApi: coreApi.replace(
      '#### Namespace `BlackOps`',
      '| Namespace／Type | Kind | Purpose | Typical Use |\n| --- | --- | --- | --- |\n| `BlackOps\\Example` | class | duplicate | `Example::run()` |\n\n#### Namespace `BlackOps`',
    ),
  };
  assert.throws(() => validateReferenceDocumentation(duplicateCatalog), /duplicate legacy per-type namespace catalog/);

  const twoMethodType = 'BlackOps\\TwoMethod';
  const twoMethodCoreApi = [
    '### Source-derived lookup fields',
    'Signature Parameter Return Default Error Typical Use Source-derived exact signature index',
    '### Source-derived exact signature index',
    '#### Namespace `BlackOps`',
    '| Type | Signature | Parameter | Return | Default | Error / Safe Code (source-observed) | Typical Use | Enum backing / cases | Public constants (name / type / value) |',
    '| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
    `| \`${twoMethodType}\` | TwoMethod::first(string $value): string<br>TwoMethod::second(int $count): int | first: string $value<br>second: int $count | first: string<br>second: int | なし（Defaultなし） | first: throws FirstError<br>second: throws SecondError | ` + '`TwoMethod::first()`、`TwoMethod::second()`を呼ぶ' + ' | not an enum (source-derived) | none (source-derived) |',
  ].join('\n');
  const twoMethodBase = {
    ...base,
    coreApi: twoMethodCoreApi,
    publicTypes: [{ name: twoMethodType, kind: 'class', methods: [
      { name: 'first', parameters: 'string $value', returnType: 'string', errorContract: 'throws FirstError' },
      { name: 'second', parameters: 'int $count', returnType: 'int', errorContract: 'throws SecondError' },
    ] }],
  };
  assert.doesNotThrow(() => validateReferenceDocumentation(twoMethodBase));
  const swappedReturns = { ...twoMethodBase, coreApi: twoMethodCoreApi.replace('first: string<br>second: int', 'first: int<br>second: string') };
  assert.throws(() => validateReferenceDocumentation(swappedReturns), /Return Method mapping drifted/);
  const swappedErrors = { ...twoMethodBase, coreApi: twoMethodCoreApi.replace('first: throws FirstError<br>second: throws SecondError', 'first: throws SecondError<br>second: throws FirstError') };
  assert.throws(() => validateReferenceDocumentation(swappedErrors), /Error／Safe Code Method mapping drifted/);
  const missingReturn = { ...twoMethodBase, coreApi: twoMethodCoreApi.replace('first: string<br>second: int', 'first: string') };
  assert.throws(() => validateReferenceDocumentation(missingReturn), /Return Method mapping count drifted/);
  const duplicateError = { ...twoMethodBase, coreApi: twoMethodCoreApi.replace('first: throws FirstError<br>second: throws SecondError', 'first: throws FirstError<br>first: throws SecondError') };
  assert.throws(() => validateReferenceDocumentation(duplicateError), /Error／Safe Code Method mapping duplicates/);
});
