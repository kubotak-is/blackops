import assert from 'node:assert/strict';
import { mkdtemp, mkdir, readFile, rm, unlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

import {
  MAX_BYTES,
  MAX_LINES,
  checkState,
  listHistory,
  sha256,
  writeState,
} from './state.mjs';

const testDirectory = dirname(fileURLToPath(import.meta.url));

function validState(revision = 'one') {
  return `# Orchestration State

Updated At: 2026-09-08T01:04:00+09:00
Status: In Progress (${revision})
Current Task: [Task](orchestration/tasks/current.md)
Current Report: [Report](orchestration/reports/current.md)

## Current Boundary

The current bounded work is recorded here.

## Evidence

The focused evidence is available in the task report.

## Remaining Work

The orchestrator still has review work to complete.

## Next Action

Review this worker result.
`;
}

async function fixture({ oldState = 'legacy STATE bytes\n', candidate = validState() } = {}) {
  const root = await mkdtemp(join(tmpdir(), 'blackops-state-tool-'));
  const statePath = join(root, 'develop', 'STATE.md');
  const archiveDirectory = join(root, 'develop', 'orchestration', 'state-archive');
  const inputPath = join(root, 'candidate.md');

  await mkdir(join(root, 'develop', 'orchestration', 'tasks'), { recursive: true });
  await mkdir(join(root, 'develop', 'orchestration', 'reports'), { recursive: true });
  await writeFile(join(root, 'develop', 'orchestration', 'tasks', 'current.md'), '# Task\n');
  await writeFile(join(root, 'develop', 'orchestration', 'reports', 'current.md'), '# Report\n');
  await writeFile(statePath, oldState);
  await writeFile(inputPath, candidate);

  return { root, statePath, archiveDirectory, inputPath, oldState, candidate };
}

async function withFixture(options, callback) {
  const paths = await fixture(options);
  try {
    return await callback(paths);
  } finally {
    await rm(paths.root, { recursive: true, force: true });
  }
}

function pathOptions(paths) {
  return { root: paths.root };
}

test('migrates legacy STATE bytes into one bounded state and verifies the archive link', async () => {
  await withFixture({ oldState: 'legacy bytes\nwith old history\n' }, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const result = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: sha256(oldBytes),
      now: new Date('2026-09-08T01:05:06Z'),
    });

    assert.equal(result.changed, true);
    assert.match(result.archive, /2026-09\/\d{8}T\d{6}Z-[a-f\d]{64}\.md$/u);
    assert.deepEqual(await readFile(result.archive), oldBytes);

    const currentBytes = await readFile(paths.statePath);
    assert.notDeepEqual(currentBytes, oldBytes);
    assert.ok(currentBytes.length <= MAX_BYTES);
    assert.ok(currentBytes.toString('utf8').split('\n').length <= MAX_LINES + 1);

    const checked = await checkState(pathOptions(paths));
    assert.equal(checked.sha256, sha256(currentBytes));
    assert.equal(checked.history.hash, sha256(oldBytes));
    assert.equal((await listHistory(pathOptions(paths))).length, 1);
  });
});

test('does not add a second archive when the same input is retried', async () => {
  await withFixture({}, async (paths) => {
    const oldSha = sha256(await readFile(paths.statePath));
    const first = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: oldSha,
      now: new Date('2026-09-08T01:05:06Z'),
    });
    const firstBytes = await readFile(paths.statePath);
    const second = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: sha256(firstBytes),
      now: new Date('2026-09-08T01:05:07Z'),
    });

    assert.equal(first.changed, true);
    assert.equal(second.changed, false);
    assert.equal(second.archive, null);
    assert.deepEqual(await readFile(paths.statePath), firstBytes);
    assert.equal((await listHistory(pathOptions(paths))).length, 1);
  });
});

test('rejects stale input before changing STATE or creating an archive', async () => {
  await withFixture({}, async (paths) => {
    const before = await readFile(paths.statePath);

    assert.throws(
      () => writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: '0'.repeat(64),
      }),
      (error) => error.code === 'STATE_STALE',
    );

    assert.deepEqual(await readFile(paths.statePath), before);
    assert.equal((await listHistory(pathOptions(paths))).length, 0);
  });
});

test('rejects empty, duplicate, invalid-link, and oversized candidates without loss', async () => {
  await withFixture({}, async (paths) => {
    const before = await readFile(paths.statePath);
    const expectedSha = sha256(before);

    const candidates = [
      ['', /must not be empty/u],
      [validState().replace('Status: In Progress (one)\n', ''), /missing required field: Status/u],
      [validState().replace('Status: In Progress (one)', 'Status:   '), /required field is empty: Status/u],
      [validState().replace('Status: In Progress (one)\n', 'Status: first\nStatus: second\n'), /duplicate required field/u],
      [validState().replace('2026-09-08', '2026-02-30'), /ISO 8601 timestamp/u],
      [validState().replace('Current Task: [Task](orchestration/tasks/current.md)', 'Current Task: [Task](https://example.test/task.md)'), /relative repository/u],
      [`${validState()}${'too many lines\n'.repeat(210)}`, /200-line limit/u],
    ];

    for (const [candidate, message] of candidates) {
      await writeFile(paths.inputPath, candidate);
      assert.throws(
        () => writeState({
          ...pathOptions(paths),
          inputPath: paths.inputPath,
          expectSha: expectedSha,
        }),
        message,
      );
      assert.deepEqual(await readFile(paths.statePath), before);
      assert.equal((await listHistory(pathOptions(paths))).length, 0);
    }

    await writeFile(paths.inputPath, validState().replace('2026-09-08', '2024-02-29'));
    assert.doesNotThrow(() =>
      writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: expectedSha,
        dryRun: true,
      }),
    );
  });
});

test('counts the generated history footer in the line and byte boundaries', async () => {
  const timestamp = new Date('2026-09-08T01:05:06Z');
  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const oldHash = sha256(oldBytes);
    const baseLines = validState().trimEnd().split('\n');
    const atLineLimit = [
      ...baseLines,
      ...Array(MAX_LINES - baseLines.length - 3).fill('bounded detail'),
    ].join('\n') + '\n';
    await writeFile(paths.inputPath, atLineLimit);

    const result = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: oldHash,
      now: timestamp,
    });
    assert.equal(result.lines, MAX_LINES);
    assert.equal((await checkState(pathOptions(paths))).lines, MAX_LINES);
  });

  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const oldHash = sha256(oldBytes);
    const baseLines = validState().trimEnd().split('\n');
    const overLineLimit = [
      ...baseLines,
      ...Array(MAX_LINES - baseLines.length - 2).fill('bounded detail'),
    ].join('\n') + '\n';
    await writeFile(paths.inputPath, overLineLimit);
    const before = await readFile(paths.statePath);

    assert.throws(
      () => writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: oldHash,
        now: timestamp,
      }),
      /200-line limit/u,
    );
    assert.deepEqual(await readFile(paths.statePath), before);
    assert.equal((await listHistory(pathOptions(paths))).length, 0);
  });

  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const oldHash = sha256(oldBytes);
    const baseline = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: oldHash,
      dryRun: true,
      now: timestamp,
    });
    const bytePadding = MAX_BYTES - baseline.bytes.length - 1;
    const atByteLimit = `${validState()}${'x'.repeat(bytePadding)}`;
    await writeFile(paths.inputPath, atByteLimit);

    const result = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: oldHash,
      now: timestamp,
    });
    assert.equal(result.bytes.length, MAX_BYTES);
    assert.equal((await checkState(pathOptions(paths))).bytes, MAX_BYTES);
  });

  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const oldHash = sha256(oldBytes);
    const baseline = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: oldHash,
      dryRun: true,
      now: timestamp,
    });
    const bytePadding = MAX_BYTES - baseline.bytes.length;
    await writeFile(paths.inputPath, `${validState()}${'x'.repeat(bytePadding)}`);
    const before = await readFile(paths.statePath);

    assert.throws(
      () => writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: oldHash,
        now: timestamp,
      }),
      /24576-byte limit/u,
    );
    assert.deepEqual(await readFile(paths.statePath), before);
    assert.equal((await listHistory(pathOptions(paths))).length, 0);
  });
});

test('dry-run validates and previews without writing, while a lock rejects concurrent mutation', async () => {
  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const oldHash = sha256(oldBytes);
    const preview = await writeState({
      ...pathOptions(paths),
      inputPath: paths.inputPath,
      expectSha: oldHash,
      dryRun: true,
      now: new Date('2026-09-08T01:05:06Z'),
    });

    assert.equal(preview.changed, true);
    assert.equal(preview.dryRun, true);
    assert.deepEqual(await readFile(paths.statePath), oldBytes);
    assert.equal((await listHistory(pathOptions(paths))).length, 0);

    const lockPath = `${paths.statePath}.lock`;
    await writeFile(lockPath, 'test lock\n');
    assert.throws(
      () => writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: oldHash,
      }),
      (error) => error.code === 'STATE_LOCKED',
    );
    await unlink(lockPath);
    assert.deepEqual(await readFile(paths.statePath), oldBytes);
  });
});

test('preserves STATE when archive preparation fails', async () => {
  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    await writeFile(paths.archiveDirectory, 'cannot be an archive directory\n');

    assert.throws(
      () => writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: sha256(oldBytes),
      }),
    );
    assert.deepEqual(await readFile(paths.statePath), oldBytes);
  });
});

test('does not replace STATE when the predicted archive path is occupied by a directory', async () => {
  await withFixture({}, async (paths) => {
    const oldBytes = await readFile(paths.statePath);
    const oldHash = sha256(oldBytes);
    const archivePath = join(
      paths.archiveDirectory,
      '2026-09',
      `20260908T010506Z-${oldHash}.md`,
    );
    await mkdir(dirname(archivePath), { recursive: true });
    await mkdir(archivePath);

    assert.throws(
      () => writeState({
        ...pathOptions(paths),
        inputPath: paths.inputPath,
        expectSha: oldHash,
        now: new Date('2026-09-08T01:05:06Z'),
      }),
    );
    assert.deepEqual(await readFile(paths.statePath), oldBytes);
  });
});
