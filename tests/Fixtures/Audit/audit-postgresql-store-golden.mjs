import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

const fixture = JSON.parse(readFileSync(new URL('./audit-postgresql-store-v1.json', import.meta.url), 'utf8'));
const MAX_SAFE_INTEGER = 9007199254740991;
const UUID_V7 = /^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/u;
const HASH = /^[0-9a-f]{64}$/u;
const PARTITION = /^(?:system:global|tenant:[0-9a-f]{64})$/u;
const TIMESTAMP = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})\.(\d{6})Z$/u;
const CATEGORIES = new Set([
  'business',
  'security',
  'access',
  'configuration',
  'control',
  'retention',
  'replay',
  'rotation',
  'outbox',
]);

const fail = (message) => {
  throw new Error(message);
};
const assert = (condition, message) => {
  if (!condition) fail(message);
};
const isObject = (value) => value !== null && typeof value === 'object' && !Array.isArray(value);
const keys = (value, expected, label) => {
  assert(isObject(value), `${label} is not an object`);
  assert(JSON.stringify(Object.keys(value)) === JSON.stringify(expected), `${label} shape/order mismatch`);
};
const utf16Compare = (left, right) => {
  const a = Array.from({ length: left.length }, (_, index) => left.charCodeAt(index));
  const b = Array.from({ length: right.length }, (_, index) => right.charCodeAt(index));
  for (let index = 0; index < Math.min(a.length, b.length); index += 1) {
    if (a[index] !== b[index]) return a[index] - b[index];
  }
  return a.length - b.length;
};
const jcs = (value) => {
  if (Array.isArray(value)) return `[${value.map(jcs).join(',')}]`;
  if (isObject(value)) {
    return `{${Object.keys(value).sort(utf16Compare).map((name) => `${JSON.stringify(name)}:${jcs(value[name])}`).join(',')}}`;
  }
  if (typeof value === 'number') assert(Number.isSafeInteger(value), 'JCS contains an unsafe number');
  return JSON.stringify(value);
};
const utf8 = (value) => Buffer.from(value, 'utf8');
const hexBytes = (value, label) => {
  assert(typeof value === 'string' && value.length % 2 === 0 && /^[0-9a-f]*$/u.test(value), `${label} is not lowercase hex`);
  return Buffer.from(value, 'hex');
};
const encString = (value) => {
  const raw = utf8(value);
  return Buffer.concat([utf8(`s:${raw.length}:`), raw]);
};
const encBytes = (value) => {
  const raw = Buffer.isBuffer(value) ? value : utf8(value);
  return Buffer.concat([utf8(`b:${raw.length}:`), raw]);
};
const encInteger = (value) => utf8(`i:${canonicalInteger(value, 'integer')}`);
const sha256 = (value) => createHash('sha256').update(value).digest('hex');
const canonicalInteger = (value, label, minimum = 0) => {
  let decimal;
  if (typeof value === 'number') {
    assert(Number.isSafeInteger(value), `${label} is unsafe`);
    decimal = String(value);
  } else {
    assert(typeof value === 'string' && /^(?:0|[1-9][0-9]*)$/u.test(value), `${label} is not canonical decimal`);
    decimal = value;
  }
  const numeric = BigInt(decimal);
  assert(numeric >= BigInt(minimum) && numeric <= BigInt(MAX_SAFE_INTEGER), `${label} is outside safe bounds`);
  return decimal;
};
const uuid = (value, label) => assert(typeof value === 'string' && UUID_V7.test(value), `${label} is not UUIDv7`);
const hash = (value, label) => assert(typeof value === 'string' && HASH.test(value), `${label} is not lowercase SHA-256`);
const timestamp = (value, label) => {
  assert(typeof value === 'string', `${label} is not a timestamp string`);
  const match = TIMESTAMP.exec(value);
  assert(match !== null, `${label} is not canonical UTC six-fraction time`);
  const [, year, month, day, hour, minute, second] = match;
  assert(Number(month) >= 1 && Number(month) <= 12, `${label} has an invalid month`);
  assert(Number(day) >= 1 && Number(day) <= 31, `${label} has an invalid day`);
  assert(Number(hour) <= 23 && Number(minute) <= 59 && Number(second) <= 59, `${label} has an invalid clock`);
  const date = new Date(`${year}-${month}-${day}T${hour}:${minute}:${second}.000Z`);
  assert(Number.isFinite(date.getTime()), `${label} is not a date`);
  assert(
    date.getUTCFullYear() === Number(year)
      && date.getUTCMonth() + 1 === Number(month)
      && date.getUTCDate() === Number(day),
    `${label} has an invalid calendar date`,
  );
};
const stringMember = (value, label, maximum = 256) => {
  assert(typeof value === 'string' && value.length > 0, `${label} is not a non-empty string`);
  assert(Buffer.byteLength(value, 'utf8') <= maximum && !/[\u0000-\u001f\u007f]/u.test(value), `${label} is out of bounds`);
};
const tenantShape = (value, label) => {
  if (value === null) return;
  assert(isObject(value) && JSON.stringify(Object.keys(value).sort()) === JSON.stringify(['id', 'type']), `${label} shape mismatch`);
  stringMember(value.type, `${label}.type`);
  stringMember(value.id, `${label}.id`);
};
const tenantJcs = (tenant) => tenant === null ? null : jcs({ id: tenant.id, type: tenant.type });
const partitionFor = (tenant) => tenant === null
  ? 'system:global'
  : `tenant:${sha256(Buffer.concat([encString('blackops.audit.partition.tenant.v1'), encBytes(tenantJcs(tenant))]))}`;
const sameTenant = (left, right) => left === null || right === null
  ? left === right
  : left.type === right.type && left.id === right.id;
const physicalTenant = (row, label) => {
  assert((row.tenant_type === null) === (row.tenant_id === null), `${label} has a partial tenant pair`);
  return row.tenant_type === null ? null : { type: row.tenant_type, id: row.tenant_id };
};
const recordHash = (row) => sha256(Buffer.concat([
  encString('blackops.audit.record.v1'),
  encString(row.partition_id),
  encString(row.segment_id),
  encInteger(row.sequence),
  encBytes(row.previous_record_hash === null ? Buffer.alloc(32) : hexBytes(row.previous_record_hash, 'previous_record_hash')),
  encBytes(hexBytes(row.content_hash, 'content_hash')),
  encString('audit-sha256-jcs-v1'),
]));
const closePreimage = (close) => Buffer.concat([
  encString('blackops.audit.segment.close.v1'),
  encString(close.partitionId),
  encString(close.closedSegmentId),
  encInteger(close.finalSequence),
  encBytes(hexBytes(close.headHash, 'close headHash')),
  encString(close.successorSegmentId),
  encString(close.closedAt),
]);
const refreshCloseDigest = (close) => {
  const preimage = closePreimage(close);
  close.preimageHex = preimage.toString('hex');
  close.token = `aft:v1:${sha256(preimage)}`;
  return close.token;
};
const assertPhysicalBody = (value, label) => {
  const body = hexBytes(value, label);
  assert(body.length >= 53 && body.length <= 1048756, `${label} is outside the body bounds`);
  assert(body.subarray(0, 4).toString('hex') === '424f5044', `${label} has the wrong BOPD magic`);
};

const EXPECTED_MUTATIONS = [
  {
    name: 'system close head hash substitution',
    kind: 'field',
    path: ['closeCases', 0, 'headHash'],
    replacement: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
  },
  {
    name: 'tenant close final sequence substitution',
    kind: 'field',
    path: ['closeCases', 1, 'finalSequence'],
    replacement: 1,
  },
  {
    name: 'closed row moved to successor segment',
    kind: 'coherent',
    path: ['partitions', 0, 'records', 1],
    operation: 'move_record_to_successor_rehash',
    replacement: 'successor_segment_id',
  },
  {
    name: 'tenant row sequence cap plus one',
    kind: 'field',
    path: ['partitions', 1, 'records', 1, 'sequence'],
    replacement: '9007199254740992',
  },
  {
    name: 'closed/successor segment identity collision',
    kind: 'coherent',
    path: ['partitions', 1, 'segments', 1],
    operation: 'collapse_successor_segment_identity',
    replacement: 'closed_segment_id',
  },
  {
    name: 'protected body wrong magic',
    kind: 'field',
    path: ['partitions', 0, 'records', 0, 'protected_body'],
    replacement: '5a4f504400000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',
  },
  {
    name: 'noncanonical timestamp',
    kind: 'field',
    path: ['partitions', 1, 'records', 1, 'recorded_at'],
    replacement: '2026-08-29T00:00:01.100002+00:00',
  },
  {
    name: 'record ordering reversal',
    kind: 'ordering',
    path: ['partitions', 0, 'records'],
    operation: 'reverse',
  },
  {
    name: 'segment ordering reversal',
    kind: 'ordering',
    path: ['partitions', 1, 'segments'],
    operation: 'reverse',
  },
  {
    name: 'physical column ordering substitution',
    kind: 'ordering',
    path: ['partitions', 0, 'records', 0],
    operation: 'swap_first_two_keys',
  },
  {
    name: 'unexpected state member',
    kind: 'shape',
    path: ['partitions', 0, 'state'],
    operation: 'add_extra_key',
  },
  {
    name: 'system close partition identifier substitution',
    kind: 'field',
    path: ['closeCases', 0, 'partitionId'],
    replacement: 'tenant:3fb6e98600ca752a93966ff30149565d1b468b11aaa6ea8cfc35c1962785ad88',
  },
  {
    name: 'tenant close closed segment identifier substitution',
    kind: 'field',
    path: ['closeCases', 1, 'closedSegmentId'],
    replacement: '0194a000-0000-7000-8000-000000000202',
  },
  {
    name: 'system close successor identifier substitution',
    kind: 'field',
    path: ['closeCases', 0, 'successorSegmentId'],
    replacement: '0194a000-0000-7000-8000-000000000101',
  },
  {
    name: 'tenant close timestamp substitution',
    kind: 'field',
    path: ['closeCases', 1, 'closedAt'],
    replacement: '2026-08-29T00:00:02.100004Z',
  },
];

const validateProjections = (value) => {
  const recordProjection = [
    'schema_version', 'kind', 'record_id', 'partition_id', 'tenant_type', 'tenant_id', 'segment_id', 'sequence',
    'previous_record_hash', 'content_hash', 'record_hash', 'recorded_at', 'event_category', 'protected_body',
    'action_type', 'resource_type', 'resource_id',
  ];
  const hydratorProjection = recordProjection.slice(0, 14);
  assert(JSON.stringify(value.recordProjection) === JSON.stringify(recordProjection), 'record projection mismatch');
  assert(JSON.stringify(value.hydratorProjection) === JSON.stringify(hydratorProjection), 'hydrator projection mismatch');
};

const validateCanonical = (entry, partition, index) => {
  keys(entry, ['recordId', 'bytes', 'contentHash'], `${partition.name}.canonical[${index}]`);
  uuid(entry.recordId, `${partition.name}.canonical[${index}].recordId`);
  assert(typeof entry.bytes === 'string' && !/[\r\n]/u.test(entry.bytes), `${partition.name}.canonical[${index}].bytes is not one-line JSON`);
  let body;
  try {
    body = JSON.parse(entry.bytes);
  } catch {
    fail(`${partition.name}.canonical[${index}].bytes is not JSON`);
  }
  keys(body, ['action', 'recordId', 'resource', 'tenant'], `${partition.name}.canonical[${index}].body`);
  keys(body.action, ['type'], `${partition.name}.canonical[${index}].body.action`);
  keys(body.resource, ['id', 'type'], `${partition.name}.canonical[${index}].body.resource`);
  stringMember(body.action.type, `${partition.name}.canonical[${index}].body.action.type`);
  stringMember(body.resource.id, `${partition.name}.canonical[${index}].body.resource.id`);
  stringMember(body.resource.type, `${partition.name}.canonical[${index}].body.resource.type`);
  uuid(body.recordId, `${partition.name}.canonical[${index}].body.recordId`);
  tenantShape(body.tenant, `${partition.name}.canonical[${index}].body.tenant`);
  assert(body.recordId === entry.recordId, `${partition.name}.canonical[${index}] record identity mismatch`);
  assert(sameTenant(body.tenant, partition.tenant), `${partition.name}.canonical[${index}] tenant mismatch`);
  assert(jcs(body) === entry.bytes, `${partition.name}.canonical[${index}] is not canonical JCS`);
  hash(entry.contentHash, `${partition.name}.canonical[${index}].contentHash`);
  assert(
    sha256(Buffer.concat([encString('blackops.audit.content.v1'), encBytes(entry.bytes)])) === entry.contentHash,
    `${partition.name}.canonical[${index}] content hash mismatch`,
  );
};

const validateRecord = (row, partition, canonical, index, previous) => {
  const label = `${partition.name}.records[${index}]`;
  keys(row, [
    'schema_version', 'kind', 'record_id', 'partition_id', 'tenant_type', 'tenant_id', 'segment_id', 'sequence',
    'previous_record_hash', 'content_hash', 'record_hash', 'recorded_at', 'event_category', 'protected_body',
    'action_type', 'resource_type', 'resource_id',
  ], label);
  assert(row.schema_version === 1 && row.kind === 'audit_stored_record', `${label} header mismatch`);
  uuid(row.record_id, `${label}.record_id`);
  assert(row.partition_id === partition.partitionId && PARTITION.test(row.partition_id), `${label}.partition_id mismatch`);
  const tenant = physicalTenant(row, label);
  assert(sameTenant(tenant, partition.tenant), `${label} tenant mismatch`);
  uuid(row.segment_id, `${label}.segment_id`);
  const sequence = Number(canonicalInteger(row.sequence, `${label}.sequence`, 1));
  assert(sequence === index + 1, `${label} sequence/order mismatch`);
  if (index === 0) {
    assert(row.previous_record_hash === null, `${label} genesis predecessor is not null`);
  } else {
    hash(row.previous_record_hash, `${label}.previous_record_hash`);
    assert(row.previous_record_hash === previous.record_hash, `${label} predecessor mismatch`);
  }
  hash(row.content_hash, `${label}.content_hash`);
  hash(row.record_hash, `${label}.record_hash`);
  assert(row.record_id === canonical.recordId && row.content_hash === canonical.contentHash, `${label} canonical link mismatch`);
  assert(recordHash(row) === row.record_hash, `${label} record hash mismatch`);
  timestamp(row.recorded_at, `${label}.recorded_at`);
  assert(CATEGORIES.has(row.event_category), `${label}.event_category is unknown`);
  assertPhysicalBody(row.protected_body, `${label}.protected_body`);
  const body = JSON.parse(canonical.bytes);
  assert(row.action_type === body.action.type, `${label}.action_type index mismatch`);
  assert(row.resource_type === body.resource.type, `${label}.resource_type index mismatch`);
  assert(row.resource_id === body.resource.id, `${label}.resource_id index mismatch`);
  stringMember(row.action_type, `${label}.action_type`);
  stringMember(row.resource_type, `${label}.resource_type`);
  stringMember(row.resource_id, `${label}.resource_id`);
};

const validatePartition = (partition, index, options = {}) => {
  const label = `partitions[${index}]`;
  keys(partition, ['name', 'partitionId', 'tenant', 'canonical', 'records', 'state', 'segments'], label);
  assert(partition.name === (index === 0 ? 'system' : 'tenant'), `${label}.name/order mismatch`);
  assert(typeof partition.partitionId === 'string' && PARTITION.test(partition.partitionId), `${label}.partitionId invalid`);
  tenantShape(partition.tenant, `${label}.tenant`);
  assert(partition.partitionId === partitionFor(partition.tenant), `${label}.partitionId derivation mismatch`);
  assert(Array.isArray(partition.canonical) && partition.canonical.length === 2, `${label}.canonical count mismatch`);
  partition.canonical.forEach((entry, canonicalIndex) => validateCanonical(entry, partition, canonicalIndex));
  assert(Array.isArray(partition.records) && partition.records.length === 2, `${label}.records count mismatch`);
  let previous = null;
  partition.records.forEach((row, recordIndex) => {
    validateRecord(row, partition, partition.canonical[recordIndex], recordIndex, previous);
    previous = row;
  });
  keys(partition.state, [
    'partition_id', 'tenant_type', 'tenant_id', 'active_segment_id', 'last_sequence', 'head_record_hash',
    'last_assigned_recorded_at', 'state_version',
  ], `${label}.state`);
  assert(partition.state.partition_id === partition.partitionId, `${label}.state partition mismatch`);
  const stateTenant = physicalTenant(partition.state, `${label}.state`);
  assert(sameTenant(stateTenant, partition.tenant), `${label}.state tenant mismatch`);
  uuid(partition.state.active_segment_id, `${label}.state.active_segment_id`);
  assert(Number(canonicalInteger(partition.state.last_sequence, `${label}.state.last_sequence`)) === 2, `${label}.state last sequence mismatch`);
  hash(partition.state.head_record_hash, `${label}.state.head_record_hash`);
  assert(partition.state.head_record_hash === partition.records[1].record_hash, `${label}.state head mismatch`);
  timestamp(partition.state.last_assigned_recorded_at, `${label}.state.last_assigned_recorded_at`);
  assert(partition.state.last_assigned_recorded_at === partition.records[1].recorded_at, `${label}.state clock high-water mismatch`);
  assert(Number(canonicalInteger(partition.state.state_version, `${label}.state.state_version`, 1)) === 2, `${label}.state version mismatch`);
  assert(Array.isArray(partition.segments) && partition.segments.length === 2, `${label}.segments count mismatch`);
  const segmentKeys = [
    'partition_id', 'segment_id', 'state', 'first_sequence', 'final_sequence', 'head_record_hash',
    'predecessor_record_hash', 'opened_at', 'closed_at', 'successor_segment_id', 'close_fencing_token', 'state_version',
  ];
  partition.segments.forEach((segment, segmentIndex) => {
    keys(segment, segmentKeys, `${label}.segments[${segmentIndex}]`);
    assert(segment.partition_id === partition.partitionId, `${label}.segments[${segmentIndex}] partition mismatch`);
    uuid(segment.segment_id, `${label}.segments[${segmentIndex}].segment_id`);
    timestamp(segment.opened_at, `${label}.segments[${segmentIndex}].opened_at`);
    const first = Number(canonicalInteger(segment.first_sequence, `${label}.segments[${segmentIndex}].first_sequence`, 1));
    assert(first === 1 + segmentIndex * 2, `${label}.segments[${segmentIndex}] first sequence/order mismatch`);
    if (segmentIndex === 0) {
      assert(segment.state === 'closed', `${label}.segments[0] is not closed`);
      assert(Number(canonicalInteger(segment.final_sequence, `${label}.segments[0].final_sequence`, 1)) === 2, `${label}.segments[0] final mismatch`);
      hash(segment.head_record_hash, `${label}.segments[0].head_record_hash`);
      assert(segment.head_record_hash === partition.records[1].record_hash, `${label}.segments[0] head mismatch`);
      assert(segment.predecessor_record_hash === null, `${label}.segments[0] predecessor mismatch`);
      assert(segment.opened_at === partition.records[0].recorded_at, `${label}.segments[0] opened boundary mismatch`);
      timestamp(segment.closed_at, `${label}.segments[0].closed_at`);
      assert(segment.closed_at >= segment.opened_at, `${label}.segments[0] closes before opening`);
      uuid(segment.successor_segment_id, `${label}.segments[0].successor_segment_id`);
      assert(segment.successor_segment_id === partition.segments[1].segment_id, `${label}.segments[0] successor mismatch`);
      assert(typeof segment.close_fencing_token === 'string' && /^aft:v1:[0-9a-f]{64}$/u.test(segment.close_fencing_token), `${label}.segments[0] token invalid`);
      assert(Number(canonicalInteger(segment.state_version, `${label}.segments[0].state_version`, 1)) === 2, `${label}.segments[0] version mismatch`);
    } else {
      assert(segment.state === 'open', `${label}.segments[1] is not open`);
      assert(segment.final_sequence === null && segment.head_record_hash === null, `${label}.segments[1] is not empty`);
      hash(segment.predecessor_record_hash, `${label}.segments[1].predecessor_record_hash`);
      assert(segment.predecessor_record_hash === partition.records[1].record_hash, `${label}.segments[1] predecessor mismatch`);
      assert(segment.opened_at === partition.segments[0].closed_at, `${label}.segments[1] opened boundary mismatch`);
      assert(segment.closed_at === null && segment.successor_segment_id === null && segment.close_fencing_token === null, `${label}.segments[1] close fields are not null`);
      assert(Number(canonicalInteger(segment.state_version, `${label}.segments[1].state_version`, 1)) === 1, `${label}.segments[1] version mismatch`);
      assert(partition.state.active_segment_id === segment.segment_id, `${label}.state active segment mismatch`);
    }
  });
  const closedSegment = partition.segments[0];
  const successorSegment = partition.segments[1];
  if (!options.skipClosedSuccessorDistinctness) {
    assert(closedSegment.segment_id !== successorSegment.segment_id, `${label} closed/successor IDs must differ`);
  }
  assert(partition.state.active_segment_id === successorSegment.segment_id, `${label} active endpoint is not the successor`);
  if (closedSegment.segment_id !== successorSegment.segment_id) {
    assert(partition.state.active_segment_id !== closedSegment.segment_id, `${label} active endpoint is the closed segment`);
  }
  if (!options.skipClosedMembership) {
    partition.records.forEach((row, recordIndex) => {
      assert(row.segment_id === closedSegment.segment_id, `${label}.records[${recordIndex}] is not in the closed segment`);
    });
  }
};

const validateClose = (close, partition, index) => {
  const label = `closeCases[${index}]`;
  keys(close, ['partitionId', 'closedSegmentId', 'finalSequence', 'headHash', 'successorSegmentId', 'closedAt', 'preimageHex', 'token'], label);
  const segment = partition.segments[0];
  assert(close.partitionId === partition.partitionId, `${label}.partitionId mismatch`);
  assert(close.closedSegmentId === segment.segment_id, `${label}.closedSegmentId mismatch`);
  assert(Number(canonicalInteger(close.finalSequence, `${label}.finalSequence`, 1)) === 2, `${label}.finalSequence mismatch`);
  assert(close.headHash === segment.head_record_hash, `${label}.headHash mismatch`);
  hash(close.headHash, `${label}.headHash`);
  assert(close.successorSegmentId === segment.successor_segment_id, `${label}.successorSegmentId mismatch`);
  uuid(close.successorSegmentId, `${label}.successorSegmentId`);
  assert(close.closedAt === segment.closed_at, `${label}.closedAt mismatch`);
  timestamp(close.closedAt, `${label}.closedAt`);
  const preimage = closePreimage(close);
  assert(close.preimageHex === preimage.toString('hex'), `${label}.preimage mismatch`);
  assert(close.token === `aft:v1:${sha256(preimage)}`, `${label}.token mismatch`);
  assert(close.token === segment.close_fencing_token, `${label}.token persistence mismatch`);
};

const validateFixture = (value, options = {}) => {
  keys(value, [
    'schemaVersion', 'kind', 'recordProjection', 'hydratorProjection', 'encodingVectors',
    'bounds', 'partitions', 'closeCases', 'mutations',
  ], 'fixture');
  assert(value.schemaVersion === 1 && value.kind === 'audit_postgresql_store_fixture', 'fixture header mismatch');
  validateProjections(value);
  keys(value.encodingVectors, ['string', 'integer', 'bytes'], 'encodingVectors');
  keys(value.encodingVectors.string, ['value', 'encodedHex'], 'encodingVectors.string');
  assert(value.encodingVectors.string.value === 'μ-close', 'ENC_STRING vector value mismatch');
  assert(value.encodingVectors.string.encodedHex === encString(value.encodingVectors.string.value).toString('hex'), 'ENC_STRING vector mismatch');
  keys(value.encodingVectors.integer, ['value', 'encodedHex'], 'encodingVectors.integer');
  assert(value.encodingVectors.integer.value === MAX_SAFE_INTEGER, 'ENC_INTEGER vector value mismatch');
  assert(value.encodingVectors.integer.encodedHex === encInteger(value.encodingVectors.integer.value).toString('hex'), 'ENC_INTEGER vector mismatch');
  keys(value.encodingVectors.bytes, ['hex', 'encodedHex'], 'encodingVectors.bytes');
  const vectorBytes = hexBytes(value.encodingVectors.bytes.hex, 'encodingVectors.bytes.hex');
  assert(value.encodingVectors.bytes.encodedHex === encBytes(vectorBytes).toString('hex'), 'ENC_BYTES vector mismatch');
  keys(value.bounds, ['maxSafeInteger', 'minSequence', 'minProtectedBodyBytes', 'maxProtectedBodyBytes', 'canonicalTimestampPattern'], 'bounds');
  assert(value.bounds.maxSafeInteger === MAX_SAFE_INTEGER && value.bounds.minSequence === 1, 'integer bounds mismatch');
  assert(value.bounds.minProtectedBodyBytes === 53 && value.bounds.maxProtectedBodyBytes === 1048756, 'body bounds mismatch');
  assert(value.bounds.canonicalTimestampPattern === 'YYYY-MM-DDTHH:mm:ss.ffffffZ', 'timestamp bound mismatch');
  assert(Array.isArray(value.partitions) && value.partitions.length === 2, 'partition count mismatch');
  value.partitions.forEach((partition, index) => validatePartition(partition, index, options));
  assert(Array.isArray(value.closeCases) && value.closeCases.length === 2, 'close case count mismatch');
  value.closeCases.forEach((close, index) => validateClose(close, value.partitions[index], index));
  assert(JSON.stringify(value.mutations) === JSON.stringify(EXPECTED_MUTATIONS), 'mutation inventory mismatch');
};

validateFixture(fixture);
const original = JSON.stringify(fixture);
let mutationKillChecks = 0;
for (const [index, mutation] of fixture.mutations.entries()) {
  const expectedKeys = mutation.kind === 'field'
    ? ['name', 'kind', 'path', 'replacement']
    : mutation.kind === 'coherent'
      ? ['name', 'kind', 'path', 'operation', 'replacement']
    : ['name', 'kind', 'path', 'operation'];
  keys(mutation, expectedKeys, `mutations[${index}]`);
  assert(typeof mutation.name === 'string' && mutation.name.length > 0, `mutations[${index}] name is invalid`);
  assert(Array.isArray(mutation.path) && mutation.path.length > 0, `mutations[${index}] path is invalid`);
  const candidate = structuredClone(fixture);
  let target = candidate;
  for (let pathIndex = 0; pathIndex < mutation.path.length - 1; pathIndex += 1) {
    const part = mutation.path[pathIndex];
    assert((typeof part === 'string' && Object.hasOwn(target, part)) || (Number.isInteger(part) && Array.isArray(target) && part >= 0 && part < target.length), `mutations[${index}] path is unresolved`);
    target = target[part];
  }
  const leaf = mutation.path.at(-1);
  assert((typeof leaf === 'string' && Object.hasOwn(target, leaf)) || (Number.isInteger(leaf) && Array.isArray(target) && leaf >= 0 && leaf < target.length), `mutations[${index}] leaf is unresolved`);
  if (mutation.kind === 'field') {
    assert(mutation.replacement === null || ['string', 'number', 'boolean'].includes(typeof mutation.replacement), `mutations[${index}] is not a scalar substitution`);
    assert(!Object.is(target[leaf], mutation.replacement), `mutations[${index}] does not substitute a field`);
    target[leaf] = mutation.replacement;
  } else if (mutation.operation === 'move_record_to_successor_rehash') {
    assert(mutation.kind === 'coherent' && mutation.replacement === 'successor_segment_id', `mutations[${index}] coherent descriptor mismatch`);
    assert(mutation.path.length === 4 && mutation.path[0] === 'partitions' && mutation.path[2] === 'records', `mutations[${index}] coherent path mismatch`);
    const partition = candidate.partitions[mutation.path[1]];
    assert(isObject(target[leaf]) && partition !== undefined && target === partition.records, `mutations[${index}] coherent target mismatch`);
    const row = target[leaf];
    row.segment_id = partition.segments[1].segment_id;
    row.record_hash = recordHash(row);
    partition.state.head_record_hash = row.record_hash;
    partition.segments[0].head_record_hash = row.record_hash;
    partition.segments[1].predecessor_record_hash = row.record_hash;
    const close = candidate.closeCases[mutation.path[1]];
    assert(close !== undefined, `mutations[${index}] close case is unresolved`);
    close.headHash = row.record_hash;
    partition.segments[0].close_fencing_token = refreshCloseDigest(close);
  } else if (mutation.operation === 'collapse_successor_segment_identity') {
    assert(mutation.kind === 'coherent' && mutation.replacement === 'closed_segment_id', `mutations[${index}] coherent descriptor mismatch`);
    assert(mutation.path.length === 4 && mutation.path[0] === 'partitions' && mutation.path[2] === 'segments', `mutations[${index}] coherent path mismatch`);
    const partition = candidate.partitions[mutation.path[1]];
    const closed = partition?.segments[0];
    const successor = target[leaf];
    assert(isObject(successor) && partition !== undefined && target === partition.segments && successor === partition.segments[1], `mutations[${index}] coherent target mismatch`);
    successor.segment_id = closed.segment_id;
    partition.state.active_segment_id = successor.segment_id;
    closed.successor_segment_id = successor.segment_id;
    const close = candidate.closeCases[mutation.path[1]];
    assert(close !== undefined, `mutations[${index}] close case is unresolved`);
    close.successorSegmentId = successor.segment_id;
    closed.close_fencing_token = refreshCloseDigest(close);
  } else if (mutation.operation === 'reverse') {
    assert(Array.isArray(target[leaf]) && target[leaf].length > 1, `mutations[${index}] reverse target is not an ordered list`);
    target[leaf].reverse();
  } else if (mutation.operation === 'swap_first_two_keys') {
    assert(isObject(target[leaf]), `mutations[${index}] key-order target is not an object`);
    const names = Object.keys(target[leaf]);
    assert(names.length > 1, `mutations[${index}] key-order target is too small`);
    const reordered = {};
    [names[1], names[0], ...names.slice(2)].forEach((name) => { reordered[name] = target[leaf][name]; });
    target[leaf] = reordered;
  } else if (mutation.operation === 'add_extra_key') {
    assert(isObject(target[leaf]), `mutations[${index}] extra-key target is not an object`);
    target[leaf]._unexpected = true;
  } else {
    fail(`mutations[${index}] has an unknown operation`);
  }
  let rejection = null;
  try {
    validateFixture(candidate);
  } catch (error) {
    rejection = error instanceof Error ? error.message : String(error);
  }
  assert(rejection !== null, `mutation was accepted: ${mutation.name}`);
  if (mutation.operation === 'move_record_to_successor_rehash') {
    assert(rejection === 'partitions[0].records[1] is not in the closed segment', `membership mutation rejected for the wrong reason: ${rejection}`);
    try {
      validateFixture(candidate, { skipClosedMembership: true });
    } catch (error) {
      fail(`membership mutation kill check failed: ${error instanceof Error ? error.message : String(error)}`);
    }
    mutationKillChecks += 1;
  }
  if (mutation.operation === 'collapse_successor_segment_identity') {
    assert(rejection === 'partitions[1] closed/successor IDs must differ', `distinctness mutation rejected for the wrong reason: ${rejection}`);
    try {
      validateFixture(candidate, { skipClosedSuccessorDistinctness: true });
    } catch (error) {
      fail(`distinctness mutation kill check failed: ${error instanceof Error ? error.message : String(error)}`);
    }
    mutationKillChecks += 1;
  }
  assert(JSON.stringify(fixture) === original, `mutation changed the source fixture: ${mutation.name}`);
}

console.log(`audit PostgreSQL store golden: PASS (mutations=${fixture.mutations.length}, mutationKills=${mutationKillChecks}, inventorySha=${sha256(utf8(JSON.stringify(fixture.mutations)))})`);
