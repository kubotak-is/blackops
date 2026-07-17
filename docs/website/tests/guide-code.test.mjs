import assert from 'node:assert/strict';
import { readdir, readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { repositoryRoot } from '../scripts/website-paths.mjs';

const guideRoot = path.join(repositoryRoot, 'docs/guide');
const guide = (name) => readFile(path.join(guideRoot, name), 'utf8');

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

  assert.match(tutorial, /^# チュートリアル: Operationを作る$/m);
  assert.ok(tutorial.indexOf(command) < tutorial.indexOf('```php'));
  for (const file of ['CreateInvoice.php', 'CreateInvoiceValue.php', 'CreateInvoiceOutcome.php']) {
    assert.match(tutorial, new RegExp(`Created: app/Feature/Billing/CreateInvoice/${file.replace('.', '\\.')}`));
  }
  assert.equal(phpBlocks.length, 4);
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
  assert.match(phpBlocks[3], /OutcomeReader/);
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
    'mvp-sample.md: curl -i http://127.0.0.1:8080/welcome',
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
      assert.match(source, /ApplicationWorkerComposer.*Worker Event.*JSONL Observer/s);
      assert.match(source, /FROM blackops\.journal/);
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

test('guide presents the Stable 1.1 release surface and experimental policy consistently', async () => {
  const installation = await guide('installation.md');
  const quickstart = await guide('mvp-sample.md');
  const tutorial = await guide('first-operation.md');
  const generators = await guide('project-generators.md');
  const status = await guide('mvp-status.md');

  assert.match(installation, /composer create-project blackops\/skeleton my-app 1\.1\.0/);
  assert.match(installation, /Websiteは`main` Document Channel/);
  assert.doesNotMatch(installation, /現行手順と同じRelease Surface/);
  assert.match(quickstart, /blackops\/skeleton my-app 1\.1\.0/);
  assert.doesNotMatch(quickstart, /dev-main/);
  assert.match(quickstart, /StableにはGlobal Middleware、Authentication、`#\[Authorize\]`がない/);
  assert.match(quickstart, /"symlink":false,"versions":\{"blackops\/framework":"1\.1\.0"\}/);
  assert.match(quickstart, /Local Path Repository/);
  assert.match(tutorial, /Experimental Stable `1\.1\.0`/);
  assert.match(generators, /Experimental Stable `1\.1\.0`/);
  assert.match(status, /7 Value Validation Attribute／422 Lifecycle \| Available \| Available/);
  assert.match(status, /FrankenPHP Worker Mode \| Default Runtime \| Default Runtime/);
  assert.match(status, /Named DBAL Connection／Default Connection DI \| Not available \| Available/);
  assert.match(status, /`#\[Transactional\]` Operation／Service \| Not available \| Available/);
  assert.match(status, /Backward Compatibility/);
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
