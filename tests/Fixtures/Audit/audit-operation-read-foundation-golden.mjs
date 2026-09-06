import { createHash, createHmac } from 'node:crypto';
import { readFileSync } from 'node:fs';

const fixture = JSON.parse(readFileSync(new URL('./audit-operation-read-foundation-v1.json', import.meta.url), 'utf8'));
const fail = (message) => { throw new Error(message); };
const expectedPurposes = ['status', 'journal', 'outcome', 'diagnostics'];
const exactKeys = (value, expected, label) => {
  if (value === null || typeof value !== 'object' || Array.isArray(value) || Object.keys(value).join('\u0000') !== expected.join('\u0000')) {
    fail(label + ' keys mismatch');
  }
};
exactKeys(fixture, [
  'schemaVersion',
  'kind',
  'operationId',
  'key',
  'basis',
  'requestRefs',
  'opaqueResource',
  'catalog',
  'source',
  'requestRefVariants',
  'draftMatrix',
], 'fixture');
exactKeys(fixture.basis, ['actor', 'execution', 'tenant', 'requestedAt', 'nonce'], 'basis');
exactKeys(fixture.basis.actor, ['type', 'id'], 'basis.actor');
exactKeys(fixture.basis.execution, ['type', 'id'], 'basis.execution');
exactKeys(fixture.basis.tenant, ['type', 'id'], 'basis.tenant');
exactKeys(fixture.catalog, ['hash', 'version', 'category', 'delivery', 'evidenceVersion'], 'catalog');
exactKeys(fixture.source, ['owner', 'runtime', 'componentPrefix', 'buildId'], 'source');
exactKeys(fixture.opaqueResource, ['purpose', 'context', 'preimageBytes', 'value'], 'opaqueResource');
exactKeys(fixture.requestRefs, expectedPurposes, 'requestRefs');
for (const purpose of expectedPurposes) exactKeys(fixture.requestRefs[purpose], ['jcsBytes', 'preimageBytes', 'value'], 'requestRefs.' + purpose);
exactKeys(fixture.requestRefVariants, ['actorNull', 'tenantNull', 'bothNull'], 'requestRefVariants');
for (const variant of ['actorNull', 'tenantNull', 'bothNull']) {
  exactKeys(fixture.requestRefVariants[variant], expectedPurposes, 'requestRefVariants.' + variant);
}
if (fixture.schemaVersion !== 1 || fixture.kind !== 'audit_operation_read_foundation_fixture') fail('fixture header mismatch');
if (fixture.key.length !== 32 || fixture.operationId.length !== 36) fail('fixture identity mismatch');
if (fixture.key !== '0123456789abcdef0123456789abcdef') fail('fixture key mismatch');
if (fixture.opaqueResource.purpose !== 'resource') fail('opaque purpose mismatch');
if (fixture.opaqueResource.context !== fixture.operationId) fail('opaque context mismatch');

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
  if (typeof value === 'number' && !Number.isSafeInteger(value)) fail('unsafe number');
  return JSON.stringify(value);
};
const canonicalCatalog = {
  hash: 'cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83',
  version: 1,
  category: 'access',
  delivery: 'required',
  evidenceVersion: 1,
};
const canonicalSource = {
  owner: 'framework',
  runtime: 'cli',
  componentPrefix: 'operation-',
  buildId: 'build-1',
};
const canonicalOperationId = '0194a000-0000-7000-8000-0000000000d2';
const canonicalBasis = {
  actor: { type: 'user', id: 'actor-1' },
  execution: { type: 'process', id: 'blackops-cli' },
  tenant: { type: 'account', id: 'tenant-1' },
  requestedAt: '2026-09-05T02:00:00.123456Z',
  nonce: '00112233445566778899aabbccddeeff',
};
if (jcs(fixture.catalog) !== jcs(canonicalCatalog)) fail('catalog constants mismatch');
if (jcs(fixture.source) !== jcs(canonicalSource)) fail('source constants mismatch');
if (fixture.operationId !== canonicalOperationId) fail('operation id constants mismatch');
if (jcs(fixture.basis) !== jcs(canonicalBasis)) fail('basis constants mismatch');
const bytes = (value) => Buffer.from(value, 'utf8');
const lp = (value) => {
  const raw = Buffer.isBuffer(value) ? value : bytes(value);
  const length = Buffer.alloc(4);
  length.writeUInt32BE(raw.length);
  return Buffer.concat([length, raw]);
};
const digest = (value) => createHash('sha256').update(value).digest('hex');
const requestRef = (purpose, basis) => {
  const json = jcs({ purpose, ...basis });
  const preimage = Buffer.concat([lp('blackops.audit.read.request-ref.v1'), lp(json)]);
  return { json, preimage, value: `oid:v1:${digest(preimage)}` };
};
const opaqueResource = (
  operationId,
  key = fixture.key,
  role = 'resource',
  domain = 'blackops.audit.opaque-id.v1',
) => {
  const preimage = Buffer.concat([lp(domain), lp(role), lp(operationId)]);
  return { preimage, value: `oid:v1:${createHmac('sha256', key).update(preimage).digest('hex')}` };
};

const basis = fixture.basis;
const references = {};
for (const purpose of expectedPurposes) {
  const calculated = requestRef(purpose, basis);
  const expected = fixture.requestRefs[purpose];
  if (Buffer.byteLength(calculated.json) !== expected.jcsBytes) fail(`${purpose} JCS length mismatch`);
  if (calculated.preimage.length !== expected.preimageBytes) fail(`${purpose} request preimage length mismatch`);
  if (calculated.value !== expected.value) fail(`${purpose} request reference mismatch`);
  references[purpose] = calculated.value;
}
const opaque = opaqueResource(fixture.operationId);
if (opaque.preimage.length !== fixture.opaqueResource.preimageBytes) fail('opaque preimage length mismatch');
if (opaque.value !== fixture.opaqueResource.value) fail('opaque resource mismatch');

const variantBasis = (actor, tenant) => ({ ...basis, actor, tenant });
for (const [variant, actor, tenant] of [
  ['actorNull', null, basis.tenant],
  ['tenantNull', basis.actor, null],
  ['bothNull', null, null],
]) {
  for (const purpose of expectedPurposes) {
    const calculated = requestRef(purpose, variantBasis(actor, tenant));
    const expected = fixture.requestRefVariants[variant][purpose];
    if (calculated.value !== expected) fail(`${variant} ${purpose} request reference mismatch`);
    if (calculated.value === references[purpose]) fail(`${variant} ${purpose} null axis was ignored`);
  }
}

const expectedTuple = {
  requested: ['requested', 'unknown', 'read_requested', 'not_applicable', 'read_requested'],
  accessed: ['succeeded', 'succeeded', 'read_accessed', 'allow', 'authorization_allowed'],
  unavailable: ['unknown', 'unknown', 'resource_unavailable', 'not_applicable', 'resource_unavailable'],
};
if (fixture.draftMatrix.length !== expectedPurposes.length * 3) fail('draft matrix count mismatch');
const seen = new Set();
const draftKeys = [
  'actionId',
  'actionType',
  'actionPhase',
  'eventType',
  'eventVersion',
  'eventCategory',
  'catalogHash',
  'resourceType',
  'resourceId',
  'outcomeStatus',
  'outcomeCode',
  'policyId',
  'policyVersion',
  'policyDecision',
  'policyReasonCode',
  'policyDelivery',
  'actor',
  'execution',
  'tenant',
  'occurredAt',
  'sourceOwner',
  'sourceComponent',
  'sourceRuntime',
  'sourceBuildId',
  'correlation',
  'evidence',
].sort(utf16Compare);
const expectedDraft = (entry, entryBasis = basis) => {
  const [actionPhase, outcomeStatus, outcomeCode, policyDecision, policyReasonCode] = expectedTuple[entry.phase];
  const eventType = `access.operation_${entry.purpose}.${entry.phase}`;
  return {
    actionId: references[entry.purpose],
    actionType: eventType,
    actionPhase,
    eventType,
    eventVersion: 1,
    eventCategory: canonicalCatalog.category,
    catalogHash: canonicalCatalog.hash,
    resourceType: 'operation',
    resourceId: opaque.value,
    outcomeStatus,
    outcomeCode,
    policyId: 'operation-read',
    policyVersion: 1,
    policyDecision,
    policyReasonCode,
    policyDelivery: canonicalCatalog.delivery,
    actor: entryBasis.actor,
    execution: entryBasis.execution,
    tenant: entryBasis.tenant,
    occurredAt: entryBasis.requestedAt,
    sourceOwner: canonicalSource.owner,
    sourceComponent: `${canonicalSource.componentPrefix}${entry.purpose}`,
    sourceRuntime: canonicalSource.runtime,
    sourceBuildId: canonicalSource.buildId,
    correlation: {},
    evidence: { version: canonicalCatalog.evidenceVersion },
  };
};
const exactDraft = (entry, entryBasis = basis) => {
  if (jcs(Object.keys(entry.draft ?? {}).sort(utf16Compare)) !== jcs(draftKeys)) return false;
  return jcs(entry.draft) === jcs(expectedDraft(entry, entryBasis));
};
for (const entry of fixture.draftMatrix) {
  const identity = `${entry.purpose}:${entry.phase}`;
  if (
    seen.has(identity)
    || !expectedPurposes.includes(entry.purpose)
    || !Object.hasOwn(expectedTuple, entry.phase)
    || jcs(Object.keys(entry).sort(utf16Compare)) !== jcs(['draft', 'phase', 'purpose'])
  ) fail('draft matrix identity mismatch');
  seen.add(identity);
  if (!exactDraft(entry)) fail(`${identity} complete draft mismatch`);
}
if (seen.size !== 12) fail('draft matrix uniqueness mismatch');
const mutation = (label, mutate) => {
  const entry = structuredClone(fixture.draftMatrix[0]);
  mutate(entry.draft);
  if (exactDraft(entry)) fail(`${label} mutation was accepted`);
};
for (const field of [
  'actionId',
  'actionType',
  'actionPhase',
  'eventType',
  'eventVersion',
  'eventCategory',
  'catalogHash',
  'resourceType',
  'resourceId',
  'outcomeStatus',
  'outcomeCode',
  'policyId',
  'policyVersion',
  'policyDecision',
  'policyReasonCode',
  'policyDelivery',
  'occurredAt',
  'sourceOwner',
  'sourceComponent',
  'sourceRuntime',
  'sourceBuildId',
]) {
  mutation(field, (draft) => {
    draft[field] = typeof draft[field] === 'number' ? draft[field] + 1 : `${draft[field]}-mutated`;
  });
}
for (const [field, mutate] of [
  ['actor', (draft) => { draft.actor.id = 'actor-mutated'; }],
  ['execution', (draft) => { draft.execution.type = 'thread'; }],
  ['tenant', (draft) => { draft.tenant = null; }],
  ['correlation', (draft) => { draft.correlation.extra = 'unexpected'; }],
  ['evidence', (draft) => { draft.evidence.version = 2; }],
  ['actor-null', (draft) => { draft.actor = null; }],
]) mutation(field, mutate);
mutation('missing-field', (draft) => { delete draft.catalogHash; });
mutation('extra-field', (draft) => { draft.extra = true; });
if (opaqueResource(fixture.operationId, 'fedcba9876543210fedcba9876543210').value === fixture.opaqueResource.value) fail('opaque key substitution was accepted');
if (opaqueResource(fixture.operationId, fixture.key, 'tenant').value === fixture.opaqueResource.value) fail('opaque HMAC role substitution was accepted');
if (opaqueResource(fixture.operationId, fixture.key, 'resource', 'blackops.audit.other-domain.v1').value === fixture.opaqueResource.value) fail('opaque HMAC domain substitution was accepted');
if (opaqueResource('0194a000-0000-7000-8000-0000000000d3').value === fixture.opaqueResource.value) fail('opaque HMAC context substitution was accepted');
if (requestRef('status', { ...basis, nonce: `${basis.nonce}0` }).value === references.status) fail('nonce substitution was accepted');
if (requestRef('status', { ...basis, actor: null }).value === references.status) fail('actor substitution was accepted');
if (requestRef('status', { ...basis, tenant: null }).value === references.status) fail('tenant substitution was accepted');

console.log('operation read foundation golden: PASS (4 request refs, 12 drafts, HMAC role)');
