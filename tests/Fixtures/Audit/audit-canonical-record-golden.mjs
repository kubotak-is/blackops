import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

const fixture = JSON.parse(readFileSync(new URL('./audit-canonical-record-v1.json', import.meta.url), 'utf8'));
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
  if (value !== null && typeof value === 'object') {
    return `{${Object.keys(value).sort(utf16Compare).map((key) => `${JSON.stringify(key)}:${jcs(value[key])}`).join(',')}}`;
  }
  if (typeof value === 'number' && !Number.isSafeInteger(value)) throw new Error('unsafe number');
  return JSON.stringify(value);
};
const bytes = (value) => Buffer.from(value, 'utf8');
const stringEncoding = (value) => bytes(`s:${Buffer.byteLength(value, 'utf8')}:${value}`);
const bytesEncoding = (value) => {
  const raw = Buffer.isBuffer(value) ? value : bytes(value);
  return Buffer.concat([bytes(`b:${raw.length}:`), raw]);
};
const integerEncoding = (value) => bytes(`i:${value}`);
const lp = (value) => {
  const raw = Buffer.isBuffer(value) ? value : bytes(value);
  const length = Buffer.alloc(4);
  length.writeUInt32BE(raw.length);
  return Buffer.concat([length, raw]);
};
const digest = (value) => createHash('sha256').update(value).digest('hex');
const hashContent = (logical) => digest(Buffer.concat([
  stringEncoding('blackops.audit.content.v1'),
  bytesEncoding(jcs(logical)),
]));
const partition = (tenant) => tenant === null
  ? 'system:global'
  : `tenant:${digest(Buffer.concat([
      stringEncoding('blackops.audit.partition.tenant.v1'),
      bytesEncoding(jcs(tenant)),
    ]))}`;
const hashRecord = (chain, contentHash) => digest(Buffer.concat([
  stringEncoding('blackops.audit.record.v1'),
  stringEncoding(chain.partitionId),
  stringEncoding(chain.segmentId),
  integerEncoding(chain.sequence),
  bytesEncoding(chain.previousRecordHash === null ? Buffer.alloc(32) : Buffer.from(chain.previousRecordHash, 'hex')),
  bytesEncoding(Buffer.from(contentHash, 'hex')),
  stringEncoding('audit-sha256-jcs-v1'),
]));
const tenantUnion = (tenant) => tenant === null
  ? Buffer.from([0])
  : Buffer.concat([Buffer.from([1]), lp(tenant.type), lp(tenant.id)]);
const previousUnion = (previous) => previous === null
  ? Buffer.from([0])
  : Buffer.concat([Buffer.from([1]), Buffer.from(previous, 'hex')]);
const recordAad = (keyId, logical, chain, contentHash, recordHash) => Buffer.concat([
  lp('blackops.audit.record.aad.v1'), lp('AuditStoredRecord'), lp('1'), lp('1'), lp('1'),
  lp(keyId), lp(logical.recordId), lp(chain.partitionId), lp(tenantUnion(logical.tenant)),
  lp(chain.segmentId), lp(String(chain.sequence)), lp(previousUnion(chain.previousRecordHash)),
  lp(Buffer.from(contentHash, 'hex')), lp(Buffer.from(recordHash, 'hex')),
  lp(logical.time.recordedAt), lp(logical.event.category),
]);
const idempotencyHash = (partitionId, key) => digest(Buffer.concat([
  stringEncoding('blackops.audit.outbox.idempotency.v1'),
  stringEncoding(partitionId),
  stringEncoding(key),
]));
const outboxAad = (keyId, logical, ordinal, key, contentHash) => {
  const partitionId = partition(logical.tenant);
  return Buffer.concat([
    lp('blackops.audit.outbox.aad.v1'), lp('AuditOutboxRecord'), lp('1'), lp('1'), lp('1'),
    lp(keyId), lp(logical.recordId), lp(partitionId), lp(tenantUnion(logical.tenant)),
    lp(String(ordinal)), lp(Buffer.from(idempotencyHash(partitionId, key), 'hex')),
    lp(Buffer.from(contentHash, 'hex')),
  ]);
};

if (fixture.schemaVersion !== 1 || fixture.kind !== 'audit_canonical_record_fixture') throw new Error('fixture header mismatch');
const records = Object.entries(fixture.records);
if (records.length !== 3) throw new Error('canonical fixture count mismatch');
for (const [name, entry] of records) {
  const logicalBytes = jcs(entry.logical);
  if (logicalBytes !== entry.logicalBytes) throw new Error(`${name} logical bytes mismatch`);
  const contentHash = hashContent(entry.logical);
  if (contentHash !== entry.contentHash) throw new Error(`${name} content hash mismatch`);
  const recordHash = hashRecord(entry.chain, contentHash);
  if (recordHash !== entry.recordHash) throw new Error(`${name} record hash mismatch`);
  if (partition(entry.logical.tenant) !== entry.chain.partitionId) throw new Error(`${name} partition mismatch`);
  const complete = {
    ...entry.logical,
    integrity: {
      hashSuite: 'audit-sha256-jcs-v1',
      partitionId: entry.chain.partitionId,
      segmentId: entry.chain.segmentId,
      sequence: entry.chain.sequence,
      previousRecordHash: entry.chain.previousRecordHash,
      contentHash,
      recordHash,
    },
  };
  if (jcs(complete) + '\n' !== entry.completeBytes) throw new Error(`${name} complete bytes mismatch`);
}

const scalarPartitions = Object.entries(fixture.scalarPartitions ?? {});
if (scalarPartitions.length !== 4) throw new Error('scalar partition fixture count mismatch');
for (const [name, entry] of scalarPartitions) {
  if (jcs(entry.tenant) !== entry.tenantJcs) throw new Error(`${name} tenant JCS mismatch`);
  if (partition(entry.tenant) !== entry.partitionId) throw new Error(`${name} partition mismatch`);
}
if (!fixture.scalarPartitions.lineSeparator.tenant.id.includes('\u2028')) throw new Error('line separator literal missing');
if (!fixture.scalarPartitions.paragraphSeparator.tenant.id.includes('\u2029')) throw new Error('paragraph separator literal missing');

const expectedStored = {
  systemGenesis: {
    keyId: 'audit-key-1',
    length: 308,
    sha256: 'c917e26a1a4d124db7701ceb76fc141581a54ce248e8df3783fc5e8500a4cade',
    hex: '0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d310000002430313934316632392d376330302d373030302d383030302d3030303030303030303030310000000d73797374656d3a676c6f62616c00000001000000002430313934316632392d376330302d373030302d383030302d303030303030303030313031000000013100000001000000002041b8f7df71f09eb50e78a6f4259b5ee0147efb192ae40c9fe13f1a4526b3244100000020a4f7fd2ac8a282deb00bd0221edd2f7c14aa559de97db4b83e5a9609227459b00000001b323032362d30382d31395430303a30303a30302e3030303030305a00000006616363657373',
  },
  tenantNonGenesis: {
    keyId: 'audit-key-2',
    length: 421,
    sha256: '0ab1465ae6e1e908228cafd36cf13fa55a805d4189bbd096d8df885a47452c48',
    hex: '0000001c626c61636b6f70732e61756469742e7265636f72642e6161642e763100000011417564697453746f7265645265636f72640000000131000000013100000001310000000b61756469742d6b65792d320000002430313934316632392d376330302d373030302d383030302d3030303030303030303030330000004774656e616e743a353966353862666362343736666363393866653565393563366266383161356638313235613732316632376535336564373062613733343561653634656165310000001801000000076163636f756e740000000874656e616e742d610000002430313934316632392d376330302d373030302d383030302d3030303030303030303130320000000132000000210153f065f0fcd332fb2dc80e6bb2c23ed9d1ee127531550bb4e0a459d09544935e00000020034a0ef2dc757517a1045660072835fbacfbfc9d77781be9e1db3b168ecf41860000002090b6a61cdd0bc6eae188fe7f79f21656cea5a97e49d8b5e6f2656a7a1c99a5cd0000001b323032362d30382d31395430303a30303a30312e3030303030305a00000006616363657373',
  },
};
for (const [name, expected] of Object.entries(expectedStored)) {
  const entry = fixture.records[name];
  const value = recordAad(expected.keyId, entry.logical, entry.chain, entry.contentHash, entry.recordHash);
  if (value.length !== expected.length || digest(value) !== expected.sha256 || value.toString('hex') !== expected.hex) throw new Error(`${name} stored AAD mismatch`);
}

const expectedOutbox = {
  durableSystemIngress: { keyId: 'audit-key-1', ordinal: 1, length: 222, sha256: '1444d7e8430fdb22bed2b29cb10988684aa8b75c061d943ef43cf1d5ec976f7d' },
  durableTenantIngress: { keyId: 'audit-key-2', ordinal: 2, length: 303, sha256: '6ad3000f8b1b7fa7d76a266c813f9bf83be8c30340c751e761abc396e9acd9e6' },
};
for (const [name, expected] of Object.entries(expectedOutbox)) {
  const entry = fixture.ingress[name];
  const partitionId = partition(entry.logical.tenant);
  if (jcs(entry.logical) !== entry.logicalBytes) throw new Error(`${name} logical bytes mismatch`);
  const idempotency = idempotencyHash(partitionId, entry.idempotencyKey);
  if (idempotency !== entry.idempotencyKeyHash) throw new Error(`${name} idempotency hash mismatch`);
  if (hashContent(entry.logical) !== entry.contentHash) throw new Error(`${name} ingress content hash mismatch`);
  const value = outboxAad(expected.keyId, entry.logical, expected.ordinal, entry.idempotencyKey, entry.contentHash);
  if (value.length !== expected.length || digest(value) !== expected.sha256) throw new Error(`${name} outbox AAD mismatch`);
}
if (fixture.records.systemGenesis.logical.policy.auditDelivery !== 'required') throw new Error('required fixture changed');
if (fixture.ingress.durableSystemIngress.logical.policy.auditDelivery !== 'durable') throw new Error('durable fixture changed');
console.log('audit canonical record golden: PASS');
