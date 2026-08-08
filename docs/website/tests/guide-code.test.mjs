import assert from 'node:assert/strict';
import { execFile as execFileCallback } from 'node:child_process';
import { mkdtemp, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import { promisify } from 'node:util';
import { tmpdir } from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const guideRoot = path.join(repositoryRoot, 'docs/guide');
const guide = (name) => readFile(path.join(guideRoot, name), 'utf8');
const execFile = promisify(execFileCallback);

test('upgrade guide installs the exact Skeleton 1.1 project-root entrypoint', async () => {
  const [upgrade, entrypoint] = await Promise.all([
    readFile(path.join(repositoryRoot, 'UPGRADE.md'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/blackops'), 'utf8'),
  ]);
  const replacement = upgrade.match(/install -m 0755 \/dev\/stdin blackops <<'PHP'\n([\s\S]*?)\nPHP/);

  assert.ok(replacement, 'UPGRADE.md must contain the complete executable entrypoint replacement');
  assert.equal(`${replacement[1]}\n`, entrypoint);
  assert.doesNotMatch(upgrade, /^mv bin\/blackops blackops$/m);
  assert.match(upgrade, /php blackops list/);
  assert.match(upgrade, /rm bin\/blackops/);
});

test('tutorial starts from the current generator and contains complete edited source', async () => {
  const tutorial = await guide('first-operation.md');
  const command = 'php blackops make:operation Billing/CreateInvoice --type=billing.invoice.create';
  const phpBlocks = [...tutorial.matchAll(/```php\n([\s\S]*?)\n```/g)].map((match) => match[1]);

  assert.match(tutorial, /^# First Operation$/m);
  assert.ok(tutorial.indexOf(command) < tutorial.indexOf('```php'));
  for (const file of ['CreateInvoice.php', 'CreateInvoiceValue.php', 'CreateInvoiceOutcome.php']) {
    assert.match(tutorial, new RegExp(`Created: app/Feature/Billing/CreateInvoice/${file.replace('.', '\\.')}`));
  }
  assert.equal(phpBlocks.length, 3);
  for (const [className, source] of [
    ['CreateInvoiceValue', phpBlocks[0]],
    ['CreateInvoiceOutcome', phpBlocks[1]],
    ['CreateInvoice', phpBlocks[2]],
  ]) {
    assert.match(source, /^<\?php\n\ndeclare\(strict_types=1\);/);
    assert.match(source, /namespace App\\Feature\\Billing\\CreateInvoice;/);
    assert.match(source, new RegExp(`class ${className}\\b`));
  }
  assert.match(phpBlocks[0], /Validation\\Attribute\\NotBlank/);
  assert.match(phpBlocks[0], /SensitiveMode::Mask/);
  assert.match(phpBlocks[2], /handle\(CreateInvoiceValue \$value, ExecutionContext \$context\): CreateInvoiceOutcome/);
  assert.match(tutorial, /OperationOutcomeQuery/);
  assert.match(tutorial, /Build artifacts written\./);
});

test('public guide commands use the project-root entrypoint deterministically', async () => {
  const files = (await readdir(guideRoot)).filter((file) => file.endsWith('.md')).sort();
  const sources = await Promise.all(files.map(async (file) => [file, await guide(file)]));

  for (const [file, source] of sources) {
    assert.doesNotMatch(source, /php bin\/blackops/, file);
  }
  const commands = sources.flatMap(([, source]) => [...source.matchAll(/(?:^|\s)php (blackops)\b/g)]);
  assert.ok(commands.length >= 20);
  assert.ok(commands.every((match) => match[1] === 'blackops'));
});

test('Welcome requests authenticate unless they intentionally demonstrate anonymous access', async () => {
  const files = (await readdir(guideRoot)).filter((file) => file.endsWith('.md')).sort();
  const anonymousExamples = [];

  for (const file of files) {
    const source = await guide(file);
    for (const match of source.matchAll(/^curl .*\/welcome$/gm)) {
      if (!/-H ['"]X-Sample-Token:/.test(match[0])) {
        anonymousExamples.push(`${file}: ${match[0]}`);
        continue;
      }
      assert.match(match[0], /-H ['"]X-Sample-Token:/, `${file}: ${match[0]}`);
    }
  }

  assert.deepEqual(anonymousExamples, [
    'installation.md: curl -i http://127.0.0.1:8080/welcome',
    'mvp-sample.md: curl -i http://127.0.0.1:8080/welcome',
    'runtime-bootstrap.md: curl http://127.0.0.1:8080/welcome',
    'troubleshooting.md: curl -i http://127.0.0.1:8080/welcome',
  ]);
});

test('guide JSON and JSONL examples stay parseable and free of raw tutorial secrets', async () => {
  for (const file of ['mvp-sample.md', 'first-operation.md', 'validation.md']) {
    const source = await guide(file);
    const jsonBlocks = [...source.matchAll(/```json\n([\s\S]*?)\n```/g)].map((match) => match[1]);
    const jsonlBlocks = [...source.matchAll(/```jsonl\n([\s\S]*?)\n```/g)].map((match) => match[1]);

    assert.ok(jsonBlocks.length > 0, file);
    for (const block of jsonBlocks) JSON.parse(block);
    for (const block of jsonlBlocks) {
      for (const line of block.split('\n')) JSON.parse(line);
    }
    if (file === 'first-operation.md') {
      assert.equal(jsonlBlocks.length, 1);
      assert.match(jsonlBlocks[0], /\[masked\]/);
      assert.doesNotMatch(jsonlBlocks[0], /local-example/);
      const records = jsonlBlocks[0].split('\n').map((line) => JSON.parse(line));
      assert.deepEqual(records.map(({ event }) => event), ['operation.received', 'operation.rejected']);
      for (const record of records) {
        assert.equal(record.schemaVersion, 1);
        assert.equal(record.kind, 'journal');
        assert.match(record.occurredAt, /^\d{4}-\d{2}-\d{2}T/);
        assert.equal(record.operation.schemaVersion, 1);
        assert.equal(record.operation.strategy, 'deferred');
        assert.ok('correlationId' in record.operation);
        assert.ok('causationId' in record.operation);
        assert.ok('attempt' in record);
      }
      assert.ok(records.every(({ attempt }) => attempt === null));
      assert.equal(records[0].data.value.billingReference, '[masked]');
      assert.deepEqual(records[1].data, {
        reason: {
          category: 'validation',
          code: 'validation.failed',
          violations: [{ field: 'email', rule: 'email', code: 'validation.email' }],
        },
      });
      assert.match(source, /HTTP ProcessのObserved Projection/);
      assert.match(source, /Public Status Resource/);
      assert.doesNotMatch(source, /FROM blackops\.journal/);
    }
    if (file === 'validation.md') {
      assert.equal(jsonlBlocks.length, 1);
      const records = jsonlBlocks[0].split('\n').map((line) => JSON.parse(line));
      assert.deepEqual(records.map(({ event, sequence }) => [event, sequence]), [
        ['operation.received', 1],
        ['operation.rejected', 2],
      ]);
      assert.ok(records.every(({ attempt }) => attempt === null));
      assert.deepEqual(records[1].data.reason.violations, [
        { field: 'email', rule: 'email', code: 'validation.email' },
        { field: 'quantity', rule: 'range', code: 'validation.range' },
      ]);
    }
  }
});

test('Journal JSONL example matches the observed encoder envelope and lifecycle set', async () => {
  const source = await guide('journal.md');
  const block = source.match(/```jsonl\n([\s\S]*?)\n```/)?.[1] ?? '';
  assert.ok(block);
  const records = block.split('\n').map((line) => JSON.parse(line));
  const topLevel = ['schemaVersion', 'kind', 'recordId', 'event', 'occurredAt', 'sequence', 'operation', 'attempt', 'data'];
  const operation = ['id', 'type', 'schemaVersion', 'strategy', 'correlationId', 'causationId', 'actors'];
  const actor = ['origin', 'authorization', 'execution'];
  const attempt = ['id', 'number', 'startedAt'];
  const events = [
    'operation.received', 'operation.accepted', 'attempt.started', 'attempt.succeeded',
    'attempt.failed', 'attempt.retry_scheduled', 'operation.completed', 'operation.rejected',
    'operation.failed', 'operation.dead_lettered',
  ];

  for (const event of events) assert.match(source, new RegExp('`' + event.replace('.', '\\.') + '`'));

  for (const record of records) {
    assert.deepEqual(Object.keys(record), topLevel);
    assert.equal(record.kind, 'journal');
    assert.match(record.occurredAt, /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/);
    assert.deepEqual(Object.keys(record.operation), operation);
    assert.deepEqual(Object.keys(record.operation.actors), actor);
    for (const actorRef of Object.values(record.operation.actors)) {
      if (actorRef !== null) assert.equal(actorRef.id, '[masked]');
    }
    if (record.attempt !== null) assert.deepEqual(Object.keys(record.attempt), attempt);
  }
  assert.deepEqual(records.map(({ event, sequence }) => [event, sequence]), [
    ['operation.received', 1],
    ['operation.accepted', 2],
    ['attempt.started', 3],
  ]);
  assert.equal(new Set(records.map(({ recordId }) => recordId)).size, 3);
  assert.ok(records.every(({ recordId, operation }) => recordId !== operation.id));
  assert.ok(records.every(({ operation }) => operation.correlationId === operation.id && operation.causationId === null));
  assert.ok(records.every(({ operation }) => operation.actors === null || ['origin', 'authorization', 'execution'].every((key) => key in operation.actors)));
  assert.ok(records.every(({ event }) => events.includes(event)));
  assert.notDeepEqual(records[0].data, records[1].data, 'data is event-specific');
  assert.deepEqual(records[1].data, {}, 'operation.accepted uses EmptyJournalData');
  assert.deepEqual(records[2].data, {}, 'attempt.started uses EmptyJournalData');
  assert.match(source, /Sensitive Filter/);
  assert.match(source, /data` はEvent固有/);
  assert.match(source, /DeferredではTyped OutcomeをOutcome Storeへ保存/);
  assert.match(source, /InlineではHTTP Responseだけへ返し、Outcome Store Rowを作成しません/);
  assert.match(source, /ActorContextがない場合は全体が`null`/);
});

test('Journal parameter contract uses five complete implementation-aligned tables', async () => {
  const source = await guide('journal.md');
  const sections = [
    ['### Top-level Record', '### `operation`', 9],
    ['### `operation`', '### `operation.actors`／Actor', 11],
    ['### `operation.actors`／Actor', '### `attempt`', 6],
    ['### `attempt`', '### Event固有`data`', 4],
    ['### Event固有`data`', '## JSONLの設定', 27],
  ];

  for (const [heading, nextHeading, expectedRows] of sections) {
    const section = source.slice(source.indexOf(heading), source.indexOf(nextHeading));
    assert.ok(section.startsWith(heading), heading);
    assert.match(section, heading === '### Event固有`data`'
      ? /\| Event \| Parameter \| Type \| 説明 \|/
      : /\| Parameter \| Type \| 説明 \|/);
    assert.equal(section.split('\n').filter((line) => line.startsWith('| `')).length, expectedRows, heading);
  }

  for (const event of [
    'operation.received', 'operation.accepted', 'attempt.started', 'attempt.succeeded',
    'attempt.failed', 'attempt.retry_scheduled', 'operation.completed', 'operation.rejected',
    'operation.failed', 'operation.dead_lettered',
  ]) assert.ok(source.includes('`' + event + '`'), event);
  assert.match(source, /\| `operation\.accepted` \| `data` \|[\s\S]*EmptyJournalData/);
  assert.match(source, /\| `operation\.received`（通常） \| `data\.value` \|/);
  assert.match(source, /\| `operation\.received`（Ephemeral） \| `data` \|[\s\S]*EmptyJournalData/);
  assert.match(source, /\| `attempt\.started` \| `data` \|[\s\S]*EmptyJournalData/);
  assert.match(source, /\| `attempt\.succeeded` \| `data` \|[\s\S]*EmptyJournalData/);
  assert.match(source, /Ephemeral OutcomeではJournalRecordFactoryがEmptyJournalDataを使うため`\{\}`/);
  assert.match(source, /\| `operation\.rejected` \| `data\.reason\.violations` \| `array<object>`/);
  assert.match(source, /RootではOperation IDと同じUUID値。子Operationは親のCorrelationを引き継ぐ/);
  assert.match(source, /子Operationを発生させた親Operation IDと同じUUID値。Rootでは`null`/);
  for (const parameter of ['data.reason.violations[].field', 'data.reason.violations[].rule', 'data.reason.violations[].code']) {
    assert.ok(source.includes(parameter), parameter);
  }
  assert.match(source, /Exception Message。SecretをMessageへ含めない/);
});

test('guide presents the Stable 1.1 release surface and experimental policy consistently', async () => {
  const installation = await guide('installation.md');
  const quickstart = await guide('mvp-sample.md');
  const tutorial = await guide('first-operation.md');
  const generators = await guide('project-generators.md');
  const status = await guide('mvp-status.md');

  assert.match(installation, /composer create-project blackops\/skeleton my-app 1\.1\.0/);
  assert.match(installation, /WebsiteはRepository `main`のドキュメント/);
  assert.doesNotMatch(installation, /現行手順と同じRelease Surface/);
  assert.match(quickstart, /blackops\/skeleton my-app 1\.1\.0/);
  assert.doesNotMatch(quickstart, /dev-main/);
  assert.match(quickstart, /StableにはGlobal Middleware、Authentication、`#\[Authorize\]`がない/);
  assert.match(quickstart, /"symlink":false,"versions":\{"blackops\/framework":"1\.1\.0"\}/);
  assert.match(quickstart, /Local Path Repository/);
  assert.match(tutorial, /Experimental Stable `1\.1\.0`/);
  assert.match(generators, /Experimental Stable `1\.1\.0`/);
  assert.match(status, /7 Value Validation Attribute／422 Lifecycle \| 利用可 \| 利用可/);
  assert.match(status, /FrankenPHP Worker Mode \| 既定Runtime \| 既定Runtime/);
  assert.match(status, /Named DBAL Connection／Default Connection DI \| 未提供 \| 利用可/);
  assert.match(status, /`#\[Transactional\]` Operation／Service \| 未提供 \| 利用可/);
  assert.match(status, /Backward Compatibility/);
});

test('stable installation is an executable anonymous Docker lane', async () => {
  const installation = await guide('installation.md');
  const stable = installation.slice(installation.indexOf('## Stable 1.1.0を作成する'), installation.indexOf('## Composer Scriptを使わない場合'));

  for (const command of ['docker compose build app http', 'docker compose up -d postgres', 'database:migrate', 'build:compile', 'docker compose up -d http', 'curl -i http://127.0.0.1:8080/welcome', 'docker compose down']) {
    assert.match(stable, new RegExp(command.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const forbidden of ['database:seed', 'make:auth', 'frontend:generate', 'pnpm']) {
    assert.doesNotMatch(stable, new RegExp(forbidden));
  }
  assert.match(installation, /composer create-project --no-scripts blackops\/skeleton my-app 1\.1\.0[\s\S]*php bin\/setup/);
});

test('main onboarding names the executable client, auth contract, and runtime links', async () => {
  const [quickstart, auth, runtime, outbox, consoleGuide, execution] = await Promise.all([
    guide('mvp-sample.md'),
    guide('authentication.md'),
    guide('runtime-bootstrap.md'),
    guide('outbox.md'),
    guide('console-command.md'),
    guide('execution.md'),
  ]);

  assert.match(quickstart, /try-client\.ts/);
  assert.match(quickstart, /pnpm exec tsc[\s\S]*tests\/Frontend\/try-client\.ts/);
  assert.match(quickstart, /--ignoreConfig --ignoreDeprecations 6\.0/);
  assert.match(quickstart, /try-client failed/);
  assert.match(quickstart, /global `fetch`/);
  assert.match(quickstart, /terminal\.data\.outcome\.reportName/);
  assert.match(quickstart, /operation-id-from-accepted-response/);
  assert.doesNotMatch(quickstart, /OPERATION_ID='019[a-f0-9-]+'/);
  assert.match(auth, /Repository `main` Preview/);
  for (const expected of ['auth.email_unavailable', 'auth.invalid_credentials', 'wrong horse battery staple', 'validation.length', 'binding.required', '200、43文字', '409、code', '401、code', '422', 'AuthenticationMiddleware::class']) {
    assert.match(auth, new RegExp(expected.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  assert.match(runtime, /docker compose --profile worker up -d worker/);
  assert.match(runtime, /docker compose --profile maintenance up -d scheduler/);
  assert.match(outbox, /Operations::dispatch\(\)/);
  assert.match(outbox, /outbox:relay:run --until-empty/);
  assert.match(consoleGuide, /project-cli\.md#operation-command/);
  const outboxExample = execution.match(/## Transactional Outboxへの登録[\s\S]*?```php\n([\s\S]*?)\n```/)?.[1] ?? '';
  assert.match(outboxExample, /readonly class PlaceOrder implements Operation/);
  assert.doesNotMatch(outboxExample, /final readonly class PlaceOrder/);
});

test('task-oriented operation guides expose source-backed process boundaries', async () => {
  const [testing, deployment, consoleGuide, outbox, cli] = await Promise.all([
    guide('testing.md'),
    guide('deployment.md'),
    guide('console-command.md'),
    guide('outbox.md'),
    guide('project-cli.md'),
  ]);

  for (const layer of ['Operation', 'HTTP', 'Deferred', 'Frontend', 'Full-stack Browser']) {
    assert.match(testing, new RegExp(`\\| ${layer} \\|`));
  }
  for (const failure of ['malformed JSON', 'Binding／Value Validation', 'Authentication', 'Worker retry／dead letter', 'poll_timeout']) {
    assert.match(testing, new RegExp(failure.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const process of ['HTTP Worker', 'Deferred Worker', 'Outbox Relay', 'Maintenance Scheduler']) {
    assert.match(deployment, new RegExp(`\\| ${process} \\|`));
  }
  for (const command of ['php blackops database:status', 'php blackops database:migrate --dry-run', 'php blackops build:compile', 'php blackops worker:run', 'php blackops outbox:relay:run']) {
    assert.match(deployment, new RegExp(command.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const contract of ['#[ConsoleCommand]', 'php blackops help report:export', 'status":"completed', 'Exit `2`']) {
    assert.match(consoleGuide, new RegExp(contract.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const step of ['Operations::dispatch()', '同じTransactionでCommit', 'outbox:relay:run --until-empty', 'worker:run', 'dead-letter retry scheduled']) {
    assert.match(outbox, new RegExp(step.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const command of ['operation:list', 'database:migrate [--dry-run]', 'worker:run', 'outbox:relay:run', 'journal:observer:replay']) {
    assert.match(cli, new RegExp(command.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  for (const option of ['--observer=application-jsonl', '--checkpoint=journal-replay-20260701', '--actor=operator', '--reason="restore projection"', '--transport-payload-days=7', '--policy-ref=production-retention-v1']) {
    assert.match(cli, new RegExp(option.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  }
  assert.ok(cli.includes('`ApplicationConfigurationSnapshot`＋`ApplicationOperationDiscovery`（Source）＋Metadata Compiler'));
  assert.ok(cli.includes('outbox:dead-letter:retry <record-id> --actor=<actor> --reason=<reason>'));
  assert.doesNotMatch(cli, /outbox:dead-letter:retry <record-id> --actor --reason/);
  assert.match(cli, /Stable `1\.1\.0`[\s\S]*Repository `main`のExperimental Surface/);
  assert.match(testing, /tests\/Consumer\/community-board-product-journey\.sh/);
  assert.match(testing, /community-board-digest\.sh.*Outbox Relayの検証記録には使いません/);
  for (const path of ['app\/Feature\/Comment\/AddComment\/AddComment.php', 'app\/Domain\/Board\/BoardRepository.php', 'app\/Feature\/Notification\/NotifyPostOwner\/NotifyPostOwner.php']) {
    assert.match(outbox, new RegExp(path));
  }
  assert.match(outbox, /Dispatch Receipt/);
});

test('task-oriented PHP examples remain syntactically parseable', async () => {
  const [consoleGuide, outbox] = await Promise.all([guide('console-command.md'), guide('outbox.md')]);
  const blocks = [
    ...consoleGuide.matchAll(/```php\n([\s\S]*?)\n```/g),
    ...outbox.matchAll(/```php\n([\s\S]*?)\n```/g),
  ].map(([, source]) => `<?php\n${source}\n`);
  const temporary = await mkdtemp(path.join(tmpdir(), 'blackops-guide-php-'));
  try {
    for (const [index, source] of blocks.entries()) {
      const file = path.join(temporary, `example-${index}.php`);
      await writeFile(file, source);
      try {
        await execFile('php', ['-l', file]);
      } catch (error) {
        if (error?.code !== 'ENOENT') throw error;
        assert.doesNotMatch(source, /\\\\/);
        assert.match(source, /(?:class|interface|trait)\s+\w+/);
      }
    }
  } finally {
    await rm(temporary, { recursive: true, force: true });
  }
});

test('Docker-only quickstart keeps the local viewer inside its reachable network boundary', async () => {
  const quickstart = await guide('mvp-sample.md');

  assert.match(quickstart, /Docker-only Quickstartでは、Host BrowserからLocal Viewerを利用できません/);
  assert.match(quickstart, /`POSTGRES_HOST=postgres`はCompose Network内だけで解決/);
  assert.match(quickstart, /Human／JSONを利用してください/);
  assert.match(quickstart, /ViewerとHTTP Clientを同じnamed CLI Container、同じLocal Network Namespace/);
  assert.match(quickstart, /Application／PHP CLI／PostgreSQL／Browserが同じLocal Network Namespace/);
  assert.match(quickstart, /Native Runtime/);
  assert.match(quickstart, /Non-loopback Bindへ緩めて回避してはいけません/);
  assert.doesNotMatch(quickstart, /0\.0\.0\.0/);
});

test('quickstart frontend journey matches the installed application-owned source', async () => {
  const [quickstart, projectCli, configuration, directory, status, packageSource, applicationSource] = await Promise.all([
    guide('mvp-sample.md'),
    guide('project-cli.md'),
    guide('configuration.md'),
    guide('directory-structure.md'),
    guide('mvp-status.md'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/package.json'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/resources/js/application/operations.ts'), 'utf8'),
  ]);
  const frontendPackage = JSON.parse(packageSource);

  assert.equal(frontendPackage.private, true);
  assert.equal(frontendPackage.devDependencies.typescript, '6.0.3');
  assert.match(frontendPackage.scripts.test, /typecheck/);
  assert.match(quickstart, /build:compile[\s\S]*frontend:generate[\s\S]*frontend:check[\s\S]*pnpm test/);
  for (const operation of ['ShowWelcome', 'GenerateReport', 'CreateOrder', 'TriggerFailure']) {
    assert.match(quickstart, new RegExp(`${operation}\\.fetch`));
    assert.match(applicationSource, new RegExp(`export \\{ ${operation} \\}`));
  }
  assert.match(quickstart, /ShowWelcome\.url\(\)/);
  assert.match(quickstart, /GenerateReport\.toRequest/);
  assert.match(quickstart, /"kind":"completed"/);
  assert.match(quickstart, /"kind":"accepted"/);
  assert.match(quickstart, /"kind":"validation"/);
  assert.match(quickstart, /"kind":"internal"/);
  assert.match(quickstart, /"kind":"transport"/);
  assert.match(projectCli, /Fresh 0、Missing／Drift 1、Invalid 2/);
  assert.match(configuration, /resources\/js\/blackops/);
  assert.match(directory, /resources\/js\/application/);
  assert.match(status, /Frontend Contract Manifest／Operation Object生成 \| 未提供 \| 利用可（試験的）/);
  assert.match(status, /Deferred Status Query／`GET \/operations\/\{operationId\}` \| 未提供 \| 利用可（試験的）/);
  assert.match(status, /Generated `\.status\(\)`／finite `\.wait\(\)` \| 未提供 \| 利用可（試験的）/);
  assert.match(quickstart, /GenerateReport\.status/);
  assert.match(quickstart, /GenerateReport\.wait/);
  assert.match(quickstart, /poll_timeout/);
});

test('quickstart order journey matches the installed transactional source', async () => {
  const [quickstart, provider, operation, command, repository, afterCommit, migration] = await Promise.all([
    guide('mvp-sample.md'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/app/ApplicationServiceProvider.php'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/app/Feature/Order/CreateOrder/CreateOrder.php'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/app/Feature/Order/CreateOrder/CreateOrderCommand.php'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/app/Feature/Order/DoctrineOrderRepository.php'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/app/Feature/Order/RecordOrderCommit.php'), 'utf8'),
    readFile(path.join(repositoryRoot, 'examples/quickstart/migrations/Version20260718000000.php'), 'utf8'),
  ]);

  assert.match(operation, /#\[Route\(method: 'POST', path: '\/orders'\)\]/);
  assert.match(operation, /#\[Transactional\][\s\S]*handle\(CreateOrderValue \$value\): OrderCreated/);
  assert.match(command, /#\[Transactional\][\s\S]*function execute\(string \$reference\): void/);
  assert.match(repository, /private Connection \$connection/);
  assert.match(repository, /VALUES \(:reference\)/);
  assert.match(afterCommit, /#\[AfterCommit\][\s\S]*function record\(string \$reference\): void/);
  assert.match(provider, /autowire\(OrderRepository::class, DoctrineOrderRepository::class\)/);
  assert.match(provider, /autowire\(CreateOrderCommand::class\)/);
  assert.match(provider, /autowire\(RecordOrderCommit::class\)/);
  assert.match(migration, /CREATE TABLE public\.quickstart_orders/);
  assert.match(migration, /CREATE TABLE public\.quickstart_order_commits/);
  assert.match(quickstart, /"reference":"order-001","status":"created"/);
  assert.match(quickstart, /Nested Required/);
  assert.match(quickstart, /Transactional Outbox/);
});
