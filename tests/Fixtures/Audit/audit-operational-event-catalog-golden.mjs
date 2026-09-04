import { readFileSync } from 'node:fs';
import { createHash } from 'node:crypto';

const utf16Units = value => Array.from({ length: value.length }, (_, index) => value.charCodeAt(index));
const utf16Compare = (left, right) => {
  const leftUnits = utf16Units(left);
  const rightUnits = utf16Units(right);
  const length = Math.min(leftUnits.length, rightUnits.length);
  for (let index = 0; index < length; index += 1) {
    if (leftUnits[index] !== rightUnits[index]) return leftUnits[index] - rightUnits[index];
  }
  return leftUnits.length - rightUnits.length;
};
const jcs = value => {
  if (Array.isArray(value)) return `[${value.map(jcs).join(',')}]`;
  if (value !== null && typeof value === 'object') {
    return `{${Object.keys(value).sort(utf16Compare).map(key => `${JSON.stringify(key)}:${jcs(value[key])}`).join(',')}}`;
  }
  return JSON.stringify(value);
};
const event = (type, phase, resourceType, outcomeStatus, outcomeCode, policyDecision, policyReasonCode) => ({
  type,
  version: 1,
  evidenceVersion: 1,
  category: type.split('.')[0],
  actionTypes: [type],
  semanticTuples: [{ actionType: type, phase, resourceType, outcomeStatus, outcomeCode, policyDecision, policyReasonCode }],
  evidenceFields: [],
  maxEvidenceBytes: 13,
  delivery: 'durable',
});
const expectedEvents = [
  event('outbox.delivery.dead_letter_retry_requested', 'requested', 'outbox_record', 'unknown', 'outbox_retry_requested', 'not_applicable', 'outbox_retry_requested'),
  event('outbox.delivery.dead_lettered', 'failed', 'outbox_record', 'failed', 'outbox_dead_lettered', 'not_applicable', 'outbox_dead_lettered'),
  event('outbox.delivery.retry_scheduled', 'committed', 'outbox_record', 'unknown', 'outbox_retry_scheduled', 'not_applicable', 'outbox_retry_scheduled'),
  event('replay.observer.failed', 'failed', 'lifecycle_journal', 'failed', 'replay_failed', 'not_applicable', 'replay_failed'),
  event('replay.observer.progressed', 'committed', 'lifecycle_journal', 'unknown', 'replay_progressed', 'not_applicable', 'replay_progressed'),
  event('replay.observer.started', 'started', 'lifecycle_journal', 'unknown', 'replay_started', 'not_applicable', 'replay_started'),
  event('replay.observer.succeeded', 'succeeded', 'lifecycle_journal', 'succeeded', 'replay_succeeded', 'not_applicable', 'replay_succeeded'),
  event('retention.hold.placed', 'committed', 'operation', 'succeeded', 'retention_hold_placed', 'not_applicable', 'retention_hold_placed'),
  event('retention.hold.released', 'committed', 'operation', 'succeeded', 'retention_hold_released', 'not_applicable', 'retention_hold_released'),
  event('retention.purge.committed', 'committed', 'operation_data', 'succeeded', 'retention_purged', 'not_applicable', 'retention_purged'),
  event('rotation.storage.failed', 'failed', 'protected_storage', 'failed', 'rotation_failed', 'not_applicable', 'rotation_failed'),
  event('rotation.storage.progressed', 'committed', 'protected_storage', 'unknown', 'rotation_progressed', 'not_applicable', 'rotation_progressed'),
  event('rotation.storage.started', 'started', 'protected_storage', 'unknown', 'rotation_started', 'not_applicable', 'rotation_started'),
  event('rotation.storage.succeeded', 'succeeded', 'protected_storage', 'succeeded', 'rotation_succeeded', 'not_applicable', 'rotation_succeeded'),
];

const catalogBody = events => ({
  schemaVersion: 1,
  kind: 'audit_event_catalog',
  catalogId: 'blackops.framework.operations',
  catalogVersion: 1,
  owner: 'framework',
  events,
});
const hashForUnsigned = unsigned => createHash('sha256').update(
  `s:${Buffer.byteLength('blackops.audit.catalog.v1')}:blackops.audit.catalog.v1b:${Buffer.byteLength(unsigned)}:${unsigned}`,
).digest('hex');
const buildArtifacts = events => {
  const body = catalogBody(events);
  const unsigned = jcs(body);
  const catalogHash = hashForUnsigned(unsigned);
  const complete = jcs({ ...body, catalogHash });
  return {
    body,
    unsigned,
    preimage: `s:${Buffer.byteLength('blackops.audit.catalog.v1')}:blackops.audit.catalog.v1b:${Buffer.byteLength(unsigned)}:${unsigned}`,
    catalog: { ...body, catalogHash },
    complete,
    raw: `${complete}\n`,
    catalogHash,
  };
};
const expected = buildArtifacts(expectedEvents);

if (process.argv.includes('--emit-fixture')) {
  process.stdout.write(expected.raw);
  process.exit(0);
}

const fixtureBytes = readFileSync(new URL('./framework-operational-audit-event-catalog-v1.json', import.meta.url));
if (
  expectedEvents.length !== 14
  || expected.unsigned.length !== 6448
  || expected.preimage.length !== 6485
  || expected.complete.length !== 6529
  || Buffer.byteLength(expected.raw) !== 6530
  || expected.catalogHash !== '9b04a5af68feb9091903282a5d99309bcd7a52cd0cbe2eb9e2901bfacbea083b'
  || fixtureBytes.toString() !== expected.raw
) process.exit(1);
if (!expected.raw.endsWith('\n') || expected.raw.endsWith('\n\n') || expected.raw.includes('\r') || fixtureBytes[0] === 0xef) process.exit(1);

const clone = value => JSON.parse(JSON.stringify(value));
const rehash = value => {
  const unsigned = clone(value);
  delete unsigned.catalogHash;
  value.catalogHash = hashForUnsigned(jcs(unsigned));
  return value;
};
const rejectCatalogMutation = (name, mutate, shouldRehash = true) => {
  const candidate = clone(expected.catalog);
  mutate(candidate);
  if (shouldRehash) rehash(candidate);
  if (jcs(candidate) === expected.complete) process.exit(1);
  if (shouldRehash && hashForUnsigned(jcs((() => { const unsigned = clone(candidate); delete unsigned.catalogHash; return unsigned; })())) === expected.catalogHash) process.exit(1);
  void name;
};
const rejectRawMutation = (name, mutate) => {
  const candidate = mutate(expected.raw);
  if (candidate === expected.raw) process.exit(1);
  void name;
};

for (const [name, mutate] of [
  ['schema version', catalog => { catalog.schemaVersion = 2; }],
  ['kind', catalog => { catalog.kind = 'other'; }],
  ['catalog id', catalog => { catalog.catalogId = 'blackops.framework.other'; }],
  ['catalog version', catalog => { catalog.catalogVersion = 2; }],
  ['owner', catalog => { catalog.owner = 'application'; }],
  ['event type', catalog => { catalog.events[0].type = 'outbox.delivery.changed'; }],
  ['event version', catalog => { catalog.events[0].version = 2; }],
  ['evidence version', catalog => { catalog.events[0].evidenceVersion = 2; }],
  ['category', catalog => { catalog.events[0].category = 'retention'; }],
  ['action type', catalog => { catalog.events[0].actionTypes[0] = 'outbox.delivery.changed'; }],
  ['tuple action type', catalog => { catalog.events[0].semanticTuples[0].actionType = 'outbox.delivery.changed'; }],
  ['tuple phase', catalog => { catalog.events[0].semanticTuples[0].phase = 'failed'; }],
  ['tuple resource', catalog => { catalog.events[0].semanticTuples[0].resourceType = 'operation'; }],
  ['tuple status', catalog => { catalog.events[0].semanticTuples[0].outcomeStatus = 'failed'; }],
  ['tuple outcome', catalog => { catalog.events[0].semanticTuples[0].outcomeCode = 'changed'; }],
  ['tuple policy', catalog => { catalog.events[0].semanticTuples[0].policyDecision = 'allow'; }],
  ['tuple reason', catalog => { catalog.events[0].semanticTuples[0].policyReasonCode = 'changed'; }],
  ['evidence fields', catalog => { catalog.events[0].evidenceFields = [{ name: 'value' }]; }],
  ['evidence size', catalog => { catalog.events[0].maxEvidenceBytes = 14; }],
  ['delivery', catalog => { catalog.events[0].delivery = 'required'; }],
]) rejectCatalogMutation(name, mutate);

rejectCatalogMutation('sort order', catalog => {
  [catalog.events[0], catalog.events[1]] = [catalog.events[1], catalog.events[0]];
});
rejectCatalogMutation('duplicate event', catalog => {
  catalog.events.splice(1, 0, clone(catalog.events[0]));
});
rejectCatalogMutation('omitted event', catalog => {
  catalog.events.splice(0, 1);
});
rejectCatalogMutation('added event', catalog => {
  catalog.events.push(clone(catalog.events[0]));
});
rejectCatalogMutation('catalog hash', catalog => {
  catalog.catalogHash = `${catalog.catalogHash.slice(0, -1)}0`;
}, false);

for (const invalid of [
  {},
  { version: 2 },
  { version: 1, extra: true },
]) {
  if (jcs(invalid) === '{"version":1}') process.exit(1);
}

for (const [name, mutate] of [
  ['missing LF', raw => raw.slice(0, -1)],
  ['double LF', raw => `${raw}\n`],
  ['CRLF', raw => raw.replace(/\n$/, '\r\n')],
  ['BOM', raw => `\ufeff${raw}`],
  ['trailing space', raw => `${raw.slice(0, -1)} \n`],
  ['leading whitespace', raw => ` ${raw}`],
  ['noncanonical bytes', raw => JSON.stringify(JSON.parse(raw))],
  ['duplicate member', raw => raw.replace('"kind":"audit_event_catalog"', '"kind":"audit_event_catalog","kind":"audit_event_catalog"')],
  ['omitted member', raw => raw.replace(',"kind":"audit_event_catalog"', '')],
  ['length truncation', raw => raw.slice(0, 6480)],
  ['length extension', raw => `${raw}x`],
]) rejectRawMutation(name, mutate);

const decimalPreimage = expected.preimage;
const u32be = value => {
  const bytes = Buffer.from(value);
  const length = Buffer.alloc(4);
  length.writeUInt32BE(bytes.length);
  return Buffer.concat([length, bytes]);
};
const lpHash = createHash('sha256').update(Buffer.concat([
  u32be('blackops.audit.catalog.v1'),
  u32be(expected.unsigned),
])).digest('hex');
if (lpHash === expected.catalogHash || decimalPreimage.length !== 6485) process.exit(1);
if (decimalPreimage.startsWith('\u0000') || decimalPreimage.includes('\u0000')) process.exit(1);

console.log('framework operational audit catalog golden: PASS');
