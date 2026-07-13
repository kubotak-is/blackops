import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import path from 'node:path';
import test from 'node:test';
import { repositoryRoot } from '../scripts/website-paths.mjs';

test('first operation code blocks match the quickstart public application source', async () => {
  const guide = await readFile(path.join(repositoryRoot, 'docs/guide/first-operation.md'), 'utf8');
  const blocks = [...guide.matchAll(/```php\n([\s\S]*?)\n```/g)].map((match) => match[1].trim());
  const sources = await Promise.all(
    ['ShowWelcome.php', 'WelcomeValue.php', 'WelcomeShown.php'].map((file) =>
      readFile(path.join(repositoryRoot, 'examples/quickstart/app/Feature/Welcome/ShowWelcome', file), 'utf8'),
    ),
  );

  assert.equal(blocks.length, sources.length);
  assert.deepEqual(blocks, sources.map((source) => source.trim()));
});

test('guide keeps stable install and unreleased generator channels distinct', async () => {
  const installation = await readFile(path.join(repositoryRoot, 'docs/guide/installation.md'), 'utf8');
  const generators = await readFile(path.join(repositoryRoot, 'docs/guide/project-generators.md'), 'utf8');
  const status = await readFile(path.join(repositoryRoot, 'docs/guide/mvp-status.md'), 'utf8');

  assert.match(installation, /composer create-project blackops\/skeleton my-app 1\.0\.0/);
  assert.match(generators, /Latest Stable `1\.0\.0`には`make:operation`／`make:migration`がまだ含まれない/);
  assert.match(status, /`make:operation`／`make:migration` \| Not included \| Implemented; unreleased/);
});
