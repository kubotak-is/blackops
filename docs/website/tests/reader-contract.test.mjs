import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { cp, mkdtemp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createRequire } from 'node:module';
import path from 'node:path';
import test from 'node:test';
import { pathToFileURL } from 'node:url';
import { promisify } from 'node:util';
import { contentMap } from '../content-map.mjs';
import {
  assertArtifactReaderText,
  assertArtifactReaderFile,
  assertNoInternalEvidenceVoice,
  assertNoCurrentMainOnly,
  assertNoProtectedDecode,
  assertNoUnsafeLgtmDiagnostics,
  normalizeArtifactVisibleText,
  validateArtifactReaderContract,
  validateLlmRouteInventory,
  validateArtifactPageRouteInventory,
  validateReferenceDocumentation,
  validateReaderContract,
  validateSearchRouteInventory,
} from '../scripts/reader-contract.mjs';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const execFileAsync = promisify(execFile);
const blumeRequire = createRequire(import.meta.resolve('blume/package.json'));
const { codeToHtml } = await import(pathToFileURL(blumeRequire.resolve('shiki')).href);
const { createSatteriMarkdownProcessor } = await import(pathToFileURL(blumeRequire.resolve('@astrojs/markdown-satteri')).href);
const satteriMarkdownProcessor = await createSatteriMarkdownProcessor({ syntaxHighlight: false });

test('canonical Content Map is the one 40-page reader inventory', () => {
  const result = validateReaderContract(contentMap);
  assert.deepEqual(result.counts, { tutorial: 3, 'how-to': 18, concept: 10, reference: 8, troubleshooting: 1 });
  assert.equal(result.pages.length, 40);
  assert.equal(new Set(result.pages.map(({ outcome }) => outcome)).size, 40);
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

test('full artifact validation applies the HTML reader routing to generated pages', async () => {
  const temporary = await mkdtemp(path.join(repositoryRoot, 'docs/website/.reader-contract-html-'));
  const artifactDirectory = path.join(temporary, 'dist');
  try {
    await cp(path.join(repositoryRoot, 'docs/website/dist'), artifactDirectory, { recursive: true });
    const htmlPath = path.join(artifactDirectory, ...contentMap['installation.md'].slug.split('/'), 'index.html');
    const original = await readFile(htmlPath, 'utf8');
    const injectBeforeBodyClose = (suffix) => {
      const bodyClose = original.lastIndexOf('</body>');
      return bodyClose < 0
        ? `${original}${suffix}`
        : `${original.slice(0, bodyClose)}${suffix}${original.slice(bodyClose)}`;
    };
    await assert.doesNotReject(() => validateArtifactReaderContract({ contentMap, artifactDirectory }));

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
      ['template-missing-close-inert', '<template><script type=application/ld+json>{"description":"Run `export -p`."}</script>', false],
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
