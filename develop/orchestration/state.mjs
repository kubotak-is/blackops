#!/usr/bin/env node

import { createHash, randomUUID } from 'node:crypto';
import { closeSync, fsyncSync, linkSync, lstatSync, mkdirSync, openSync, readFileSync, readdirSync, realpathSync, renameSync, statSync, unlinkSync, writeSync } from 'node:fs';
import { dirname, extname, isAbsolute, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { TextDecoder, parseArgs } from 'node:util';

export const MAX_LINES = 200;
export const MAX_BYTES = 24 * 1024;
export const HISTORY_START = '<!-- state-tool:history:start -->';
export const HISTORY_END = '<!-- state-tool:history:end -->';
const ARCHIVE_FILE = /^(\d{8}T\d{6}Z)-([a-f0-9]{64})\.md$/u;
const MONTH_DIRECTORY = /^\d{4}-\d{2}$/u;
const REQUIRED_FIELDS = ['Updated At', 'Status', 'Current Task', 'Current Report'];
const REQUIRED_SECTIONS = ['Current Boundary', 'Evidence', 'Remaining Work', 'Next Action'];
const decoder = new TextDecoder('utf-8', { fatal: true });

const repositoryRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

export class StateToolError extends Error {
  constructor(message, code = 'STATE_INVALID') {
    super(message);
    this.name = 'StateToolError';
    this.code = code;
  }
}

export function statePaths(root = repositoryRoot) {
  const rootPath = resolve(root);
  return {
    root: rootPath,
    state: join(rootPath, 'develop', 'STATE.md'),
    archive: join(rootPath, 'develop', 'orchestration', 'state-archive'),
  };
}

export function sha256(value) {
  return createHash('sha256').update(value).digest('hex');
}

function error(message, code = 'STATE_INVALID') {
  throw new StateToolError(message, code);
}

function textOf(bytes, label) {
  try {
    const text = typeof bytes === 'string' ? bytes : decoder.decode(bytes);
    if (text.includes('\0') || /\r(?!\n)/u.test(text)) error(`${label} contains invalid control characters.`);
    return text;
  } catch (cause) {
    if (cause instanceof StateToolError) throw cause;
    error(`${label} is not valid UTF-8.`);
  }
}

function linesOf(text) {
  const lines = text.split(/\r?\n/u);
  if (lines.at(-1) === '') lines.pop();
  return lines;
}

function lineCount(text) {
  return text === '' ? 0 : linesOf(text).length;
}

function footerOf(text) {
  const start = text.indexOf(HISTORY_START);
  const end = text.indexOf(HISTORY_END, start + HISTORY_START.length);
  const hasFooter = start !== -1 || end !== -1;
  if (!hasFooter) return { base: text, reference: null, footer: null };
  if (start < 0 || end < 0 || text.indexOf(HISTORY_START, start + 1) !== -1)
    error('STATE history footer is duplicated or incomplete.');
  if ((start > 0 && text[start - 1] !== '\n') || text.indexOf(HISTORY_END, end + 1) !== -1)
    error('STATE history footer is malformed.');
  const footerEnd = end + HISTORY_END.length;
  if (text.slice(footerEnd).trim() !== '') error('STATE history footer must be final.');
  const footer = text.slice(start, footerEnd);
  const match = footer.match(/^(?:<!-- state-tool:history:start -->)\r?\nPrevious STATE archive: \[snapshot\]\(([^)]+)\)\r?\n(?:<!-- state-tool:history:end -->)$/u);
  if (!match) error('STATE history footer is malformed.');
  return { base: text.slice(0, start), reference: match[1], footer };
}

function comparisonBase(bytes) {
  try {
    return footerOf(textOf(bytes, 'STATE')).base;
  } catch (cause) {
    if (cause instanceof StateToolError) return null;
    throw cause;
  }
}

function checkMeasure(text, includeFooter = true) {
  const bytes = Buffer.byteLength(text, 'utf8');
  const lines = lineCount(text);
  if (lines > MAX_LINES) error(`STATE exceeds the ${MAX_LINES}-line limit${includeFooter ? ' including its history footer' : ''}.`);
  if (bytes > MAX_BYTES) error(`STATE exceeds the ${MAX_BYTES}-byte limit${includeFooter ? ' including its history footer' : ''}.`);
  return { lines, bytes };
}

function field(lines, name) {
  const values = lines.flatMap((line) => {
    const match = line.match(new RegExp(`^${name}:\\s*(.*)$`, 'u'));
    return match ? [match[1].trim()] : [];
  });
  if (values.length === 0) error(`STATE is missing required field: ${name}.`);
  if (values.length > 1) error(`STATE contains duplicate required field: ${name}.`);
  if (values[0] === '') error(`STATE required field is empty: ${name}.`);
  return values[0];
}

function linkTarget(value, name, paths) {
  const match = value.match(/^\[[^\]]+\]\(([^)]+)\)$/u);
  if (!match) error(`${name} must be one Markdown link.`);
  const target = match[1].trim().split('#', 1)[0];
  if (!target || target.startsWith('/') || target.startsWith('\\') ||
      /^[A-Za-z][A-Za-z\d+.-]*:/u.test(target) || extname(target).toLowerCase() !== '.md') {
    error(`${name} must link to a relative repository Markdown file.`);
  }
  const targetPath = resolve(dirname(paths.state), target);
  const rootPath = resolve(paths.root);
  const lexical = relative(rootPath, targetPath);
  if (!lexical || lexical === '..' || lexical.startsWith(`..${sep}`) || isAbsolute(lexical))
    error(`${name} must point inside the repository.`);
  try {
    const rootReal = realpathSync(rootPath);
    const targetReal = realpathSync(targetPath);
    const actual = relative(rootReal, targetReal);
    if (!actual || actual === '..' || actual.startsWith(`..${sep}`) || isAbsolute(actual))
      error(`${name} must point inside the repository.`);
    if (!statSync(targetReal).isFile()) error(`${name} must point to an existing file.`);
  } catch (cause) {
    if (cause instanceof StateToolError) throw cause;
    error(`${name} must point to an existing repository file.`);
  }
  return targetPath;
}

function validateBase(text, paths) {
  if (text.trim() === '') error('STATE input must not be empty.');
  const lines = linesOf(text);
  const values = Object.fromEntries(REQUIRED_FIELDS.map((name) => [name, field(lines, name)]));
  const updatedAt = values['Updated At'];
  const [year, month, day] = updatedAt.slice(0, 10).split('-').map(Number);
  const calendarDate = new Date(Date.UTC(year, month - 1, day));
  if (
    !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/u.test(updatedAt) ||
    Number.isNaN(Date.parse(updatedAt)) ||
    calendarDate.getUTCFullYear() !== year ||
    calendarDate.getUTCMonth() !== month - 1 ||
    calendarDate.getUTCDate() !== day
  )
    error('Updated At must be an ISO 8601 timestamp with seconds and a UTC offset.');
  linkTarget(values['Current Task'], 'Current Task', paths);
  linkTarget(values['Current Report'], 'Current Report', paths);
  for (const section of REQUIRED_SECTIONS) {
    const heading = `## ${section}`;
    const indexes = lines.flatMap((line, index) => (line.trimEnd() === heading ? [index] : []));
    if (indexes.length === 0) error(`STATE is missing required section: ${heading}.`);
    if (indexes.length > 1) error(`STATE contains duplicate required section: ${heading}.`);
    const next = lines.findIndex((line, index) => index > indexes[0] && /^##\s/u.test(line));
    const body = lines.slice(indexes[0] + 1, next < 0 ? lines.length : next).join('\n').trim();
    if (!body) error(`STATE required section is empty: ${heading}.`);
  }
  checkMeasure(text, false);
}

function verifyFooter(parsed, paths) {
  if (!parsed.reference) return null;
  const archivePath = resolve(dirname(paths.state), parsed.reference);
  const outside = relative(resolve(paths.archive), archivePath);
  if (!outside || outside === '..' || outside.startsWith(`..${sep}`) || isAbsolute(outside))
    error('STATE history link must point inside the archive directory.');
  const name = archivePath.split(sep).at(-1);
  const match = name?.match(ARCHIVE_FILE);
  if (!match) error('STATE history link has an invalid archive filename.');
  let bytes;
  try {
    if (!lstatSync(archivePath).isFile()) error('STATE history archive is not a regular file.');
    bytes = readFileSync(archivePath);
  } catch (cause) {
    if (cause instanceof StateToolError) throw cause;
    error('STATE history link points to a missing archive.');
  }
  if (sha256(bytes) !== match[2]) error('STATE history archive hash does not match its filename.');
  return { archivePath, hash: match[2] };
}

export function validateState(value, paths = statePaths()) {
  const text = textOf(value, 'STATE');
  const parsed = footerOf(text);
  validateBase(parsed.base, paths);
  return { ...checkMeasure(text), base: parsed.base, footer: parsed.footer, reference: parsed.reference };
}

function archiveEntries(directory) {
  if (!exists(directory)) return [];
  return readdirSync(directory, { withFileTypes: true })
    .filter((month) => month.isDirectory() && MONTH_DIRECTORY.test(month.name))
    .flatMap((month) => readdirSync(join(directory, month.name), { withFileTypes: true })
      .filter((entry) => entry.isFile() && ARCHIVE_FILE.test(entry.name))
      .map((entry) => join(month.name, entry.name)))
    .sort();
}

function exists(path) {
  try {
    statSync(path);
    return true;
  } catch {
    return false;
  }
}

function existingArchive(paths, hash, bytes) {
  for (const entry of archiveEntries(paths.archive)) {
    if (!entry.endsWith(`-${hash}.md`)) continue;
    const path = join(paths.archive, entry);
    if (readFileSync(path).equals(bytes)) return path;
    error(`Archive ${entry} already uses this SHA with different bytes.`);
  }
  return null;
}

function archiveTimestamp(value) {
  const date = value instanceof Date ? value : new Date(value ?? Date.now());
  if (Number.isNaN(date.getTime())) error('Archive timestamp is invalid.');
  const pad = (part) => String(part).padStart(2, '0');
  return `${date.getUTCFullYear()}${pad(date.getUTCMonth() + 1)}${pad(date.getUTCDate())}T${pad(
    date.getUTCHours(),
  )}${pad(date.getUTCMinutes())}${pad(date.getSeconds())}Z`;
}

function writeAll(fd, bytes) {
  for (let offset = 0; offset < bytes.length; ) offset += writeSync(fd, bytes, offset);
}

function exclusiveFile(path, bytes) {
  const fd = openSync(path, 'wx', 0o644);
  try {
    writeAll(fd, bytes);
    fsyncSync(fd);
  } finally {
    closeSync(fd);
  }
}

function createArchive(path, bytes) {
  mkdirSync(dirname(path), { recursive: true });
  const temporary = join(dirname(path), `.archive-${process.pid}-${randomUUID()}.tmp`);
  try {
    exclusiveFile(temporary, bytes);
    try {
      linkSync(temporary, path);
    } catch (cause) {
      if (cause.code !== 'EEXIST') throw cause;
      if (!lstatSync(path).isFile()) error('Archive path already exists and is not a regular file.');
      if (!readFileSync(path).equals(bytes)) error('Archive path already exists with different bytes.');
    }
  } finally {
    if (exists(temporary)) unlinkSync(temporary);
  }
}

function ensureArchive(path, bytes) {
  if (exists(path)) {
    if (!lstatSync(path).isFile() || !readFileSync(path).equals(bytes))
      error('Archive path already exists with different or invalid bytes.');
    return;
  }
  createArchive(path, bytes);
}

function replaceState(path, bytes) {
  const temporary = join(dirname(path), `.STATE-${process.pid}-${randomUUID()}.tmp`);
  try {
    exclusiveFile(temporary, bytes);
    renameSync(temporary, path);
  } finally {
    if (exists(temporary)) unlinkSync(temporary);
  }
}

function withLock(path, callback) {
  const lockPath = `${path}.lock`;
  let acquired = false;
  let fd;
  try {
    fd = openSync(lockPath, 'wx', 0o600);
    acquired = true;
    writeAll(fd, Buffer.from(`${process.pid}\n`));
    fsyncSync(fd);
    return callback();
  } catch (cause) {
    if (cause.code === 'EEXIST') error('Another STATE update is already in progress.', 'STATE_LOCKED');
    throw cause;
  } finally {
    if (fd !== undefined) closeSync(fd);
    if (acquired && exists(lockPath)) unlinkSync(lockPath);
  }
}

function read(path, label) {
  try {
    return readFileSync(path);
  } catch (cause) {
    error(`Unable to read ${label}: ${cause.message}`, label === 'STATE' ? 'STATE_READ_FAILED' : 'INPUT_READ_FAILED');
  }
}

function plannedArchive(paths, current, now) {
  const hash = sha256(current);
  const existing = existingArchive(paths, hash, current);
  if (existing) return existing;
  const stamp = archiveTimestamp(now);
  return join(paths.archive, `${stamp.slice(0, 4)}-${stamp.slice(4, 6)}`, `${stamp}-${hash}.md`);
}

function outputFor(base, paths, archive) {
  const reference = relative(dirname(paths.state), archive).split(sep).join('/');
  const ending = base.includes('\r\n') ? '\r\n' : '\n';
  const separator = base.endsWith(ending) ? '' : ending;
  return `${base}${separator}${HISTORY_START}${ending}Previous STATE archive: [snapshot](${reference})${ending}${HISTORY_END}${ending}`;
}

export function checkState({ root = repositoryRoot } = {}) {
  const paths = statePaths(root);
  const bytes = read(paths.state, 'STATE');
  const text = textOf(bytes, 'STATE');
  const parsed = footerOf(text);
  const result = validateState(text, paths);
  return { ...result, sha256: sha256(bytes), history: verifyFooter(parsed, paths), statePath: paths.state };
}

export function listHistory({ root = repositoryRoot } = {}) {
  const paths = statePaths(root);
  return archiveEntries(paths.archive).map((entry) => ({
    relativePath: entry.split(sep).join('/'),
    archivePath: join(paths.archive, entry),
  }));
}

export function writeState({ root = repositoryRoot, inputPath, expectSha, dryRun = false, now } = {}) {
  if (!inputPath) error('STATE write requires an input path.', 'USAGE');
  if (!/^[a-f\d]{64}$/iu.test(expectSha ?? '')) error('STATE write requires a 64-character SHA256.', 'USAGE');
  const paths = statePaths(root);
  const candidate = footerOf(textOf(read(resolve(inputPath), 'STATE input'), 'STATE input'));
  validateBase(candidate.base, paths);
  const expected = expectSha.toLowerCase();
  const initial = read(paths.state, 'STATE');
  if (sha256(initial) !== expected) error('Expected STATE SHA256 does not match the current STATE.', 'STATE_STALE');
  const plan = (current) => {
    if (comparisonBase(current) === candidate.base) return { changed: false, archive: null, bytes: current };
    const archive = plannedArchive(paths, current, now);
    const output = Buffer.from(outputFor(candidate.base, paths, archive), 'utf8');
    validateState(output, paths);
    return { changed: true, archive, bytes: output };
  };
  if (dryRun) {
    const result = plan(initial);
    return { ...result, dryRun: true, sha256: sha256(result.bytes), lines: lineCount(result.bytes.toString('utf8')), statePath: paths.state };
  }
  return withLock(paths.state, () => {
    const current = read(paths.state, 'STATE');
    if (sha256(current) !== expected) error('Expected STATE SHA256 does not match the current STATE.', 'STATE_STALE');
    const result = plan(current);
    if (result.changed) {
      ensureArchive(result.archive, current);
      replaceState(paths.state, result.bytes);
    }
    return { ...result, dryRun: false, sha256: sha256(result.bytes), lines: lineCount(result.bytes.toString('utf8')), statePath: paths.state };
  });
}

function main() {
  const { values } = parseArgs({
    options: {
      check: { type: 'boolean' },
      history: { type: 'boolean' },
      write: { type: 'string' },
      'expect-sha': { type: 'string' },
      'dry-run': { type: 'boolean' },
    },
    allowPositionals: false,
    strict: true,
  });
  const actions = [values.check, values.history, values.write !== undefined].filter(Boolean).length;
  if (actions !== 1) error('Choose exactly one of --check, --history, or --write.', 'USAGE');
  if (values.check || values.history) {
    if (values['expect-sha'] || values['dry-run']) error('Write options require --write.', 'USAGE');
    if (values.check) {
      const result = checkState();
      console.log(`STATE OK\nSHA256: ${result.sha256}\nLines: ${result.lines}\nBytes: ${result.bytes}`);
    } else {
      for (const entry of listHistory()) console.log(entry.relativePath);
    }
    return;
  }
  if (!values['expect-sha']) error('--write requires --expect-sha.', 'USAGE');
  print(writeState({ inputPath: values.write, expectSha: values['expect-sha'], dryRun: values['dry-run'] === true }));
}

function print(result) {
  const state = result.dryRun ? 'DRY RUN' : result.changed ? 'STATE UPDATED' : 'STATE UNCHANGED';
  console.log(`${state}\nSHA256: ${result.sha256}\nLines: ${lineCount(result.bytes.toString('utf8'))}\nBytes: ${result.bytes.length}`);
  if (result.archive) console.log(`Archive: ${result.archive}`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    main();
  } catch (cause) {
    console.error(`state.mjs: ${cause instanceof Error ? cause.message : String(cause)}`);
    process.exitCode = 1;
  }
}
