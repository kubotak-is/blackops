import { readFileSync } from 'node:fs';
import { createHash, createHmac } from 'node:crypto';

const fixtureBytes = readFileSync(new URL('./framework-audit-event-catalog-v1.json', import.meta.url));
const fixture = JSON.parse(fixtureBytes);
const events = [];
const event = (type, phase, resourceType, outcomeStatus, outcomeCode, policyDecision, policyReasonCode, delivery, version = 1) => ({
  type, version, evidenceVersion: version, category: type.startsWith('security') ? 'security' : 'access',
  actionTypes: [type], semanticTuples: [{ actionType: type, phase, resourceType, outcomeStatus, outcomeCode, policyDecision, policyReasonCode }],
  evidenceFields: [], maxEvidenceBytes: 13, delivery,
});
events.push(event('security.authentication.succeeded', 'succeeded', 'authentication', 'succeeded', 'authenticated', 'allow', 'authenticated', 'durable'));
events.push(event('security.authentication.invalid', 'denied', 'authentication', 'denied', 'authentication_invalid', 'deny', 'authentication_invalid', 'durable'));
for (const [name, phase, status, code, decision] of [['allow','succeeded','succeeded','authorization_allowed','allow'],['deny','denied','denied','authorization_denied','deny']]) {
  events.push(event(`security.authorization.${name}`, phase, 'operation', status, code, decision, code, 'durable'));
  events.push(event(`security.authorization.${name}`, phase, 'operation', status, code, decision, code, 'required', 2));
}
for (const purpose of ['status', 'journal', 'outcome', 'diagnostics']) {
  events.push(event(`access.operation_${purpose}.requested`, 'requested', 'operation', 'unknown', 'read_requested', 'not_applicable', 'read_requested', 'required'));
  events.push(event(`access.operation_${purpose}.unavailable`, 'unknown', 'operation', 'unknown', 'resource_unavailable', 'not_applicable', 'resource_unavailable', 'required'));
  events.push(event(`access.operation_${purpose}.accessed`, 'succeeded', 'operation', 'succeeded', 'read_accessed', 'allow', 'authorization_allowed', 'required'));
}
for (const purpose of ['query', 'export', 'sign', 'verify']) {
  events.push(event(`audit.access.${purpose}.requested`, 'requested', 'audit_trail', 'unknown', 'audit_access_requested', 'not_applicable', 'audit_access_requested', 'durable'));
  events.push(event(`audit.access.${purpose}.unavailable`, 'unknown', 'audit_trail', 'unknown', 'resource_unavailable', 'not_applicable', 'resource_unavailable', 'durable'));
  events.push(event(`audit.access.${purpose}.accessed`, 'succeeded', 'audit_trail', 'succeeded', 'audit_accessed', 'allow', 'authorization_allowed', 'required'));
}
const utf16Units = value => Array.from({ length: value.length }, (_, index) => value.charCodeAt(index));
const utf16Compare = (a, b) => {
  const left = utf16Units(a);
  const right = utf16Units(b);
  const length = Math.min(left.length, right.length);
  for (let index = 0; index < length; index += 1) {
    if (left[index] !== right[index]) return left[index] - right[index];
  }
  return left.length - right.length;
};
events.sort((a, b) => utf16Compare(a.type, b.type) || a.version - b.version || a.evidenceVersion - b.evidenceVersion);
const catalogId = 'blackops.framework.security_access';
const body = { schemaVersion: 1, kind: 'audit_event_catalog', catalogId, catalogVersion: 1, owner: 'framework', events };
const jcs = value => Array.isArray(value) ? `[${value.map(jcs).join(',')}]` : value && typeof value === 'object' ? `{${Object.keys(value).sort(utf16Compare).map(key => `${JSON.stringify(key)}:${jcs(value[key])}`).join(',')}}` : JSON.stringify(value);
const unsigned = jcs(body);
const lp = value => { const bytes = Buffer.from(value); const length = Buffer.alloc(4); length.writeUInt32BE(bytes.length); return Buffer.concat([length, bytes]); };
const stringEncoding = value => Buffer.from(`s:${Buffer.byteLength(value)}:${value}`);
const bytesEncoding = value => Buffer.from(`b:${Buffer.byteLength(value)}:${value}`);
const preimage = Buffer.concat([stringEncoding('blackops.audit.catalog.v1'), bytesEncoding(unsigned)]);
const hash = createHash('sha256').update(preimage).digest('hex');
const complete = jcs({ ...body, catalogHash: hash });
if (events.length !== 30 || unsigned.length !== 13878 || preimage.length !== 13916 || complete.length !== 13959 || hash !== 'cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83' || fixtureBytes.toString() !== complete + '\n') process.exit(1);
const requestBasis = { purpose: 'status', actor: null, execution: { type: 'cli', id: 'exec-1' }, tenant: null, requestedAt: '2026-08-20T03:20:00.123456Z', nonce: 'nonce-1' };
const requestJson = jcs(requestBasis);
const requestPreimage = Buffer.concat([lp('blackops.audit.read.request-ref.v1'), lp(requestJson)]);
const requestRef = `oid:v1:${createHash('sha256').update(requestPreimage).digest('hex')}`;
if (requestJson.length !== 150 || requestPreimage.length !== 192 || requestRef !== 'oid:v1:bfad862f2771114911e44b417ca9205e98e23e6716ffc47e18c2182d35bdf0cf') process.exit(1);
const unicodeBasis = { purpose: 'diagnostics', actor: { type: 'actor\u2028', id: 'tenant\u2029' }, execution: { type: 'execution\u2029', id: 'exec\u2028' }, tenant: { type: 'actor\u2028', id: 'tenant\u2029' }, requestedAt: '2026-08-20T03:20:00.123456Z', nonce: 'nonce\u2028\u2029' };
const unicodeJson = jcs(unicodeBasis);
const unicodePreimage = Buffer.concat([lp('blackops.audit.read.request-ref.v1'), lp(unicodeJson)]);
const unicodeRef = `oid:v1:${createHash('sha256').update(unicodePreimage).digest('hex')}`;
if (!unicodeJson.includes('\u2028') || !unicodeJson.includes('\u2029') || unicodeJson.includes('\\u2028') || unicodeJson.includes('\\u2029') || unicodeRef !== 'oid:v1:d5dad52db850d4b331f2ff0d06c79cb29fcc4e1fbd7e8c6bb53b1c34b3b32a92') process.exit(1);
const key = Buffer.from([...Array(32).keys()]);
const tenantContext = jcs({ type: ' Type/V1 ', id: 'ID:AbC/01' });
const evidenceContext = jcs({ catalogHash: 'cfbcb3335b27ac4506046216d7ff43a9802586b1e60fbb9cfc47dc12230b1d83', eventType: 'security.authentication.succeeded', eventVersion: 1, evidenceVersion: 1, fieldName: 'result_code', value: 'ok' });
const expectedTenant = 'oid:v1:6f8ddcb879201ece4e44eab6583ba21d43348e66db3177ecb3fe23c342437b9b';
const expectedEvidence = 'oid:v1:126af91d210237e27ea45de3c9f79a4046d51cf9316fd99205e379868ce2bd2a';
const hmacSha256 = (purpose, context, secret = key) => `oid:v1:${createHmac('sha256', secret).update(Buffer.concat([lp('blackops.audit.opaque-id.v1'), lp(purpose), lp(context)])).digest('hex')}`;
if (hmacSha256('tenant', tenantContext) !== expectedTenant || hmacSha256('evidence', evidenceContext) !== expectedEvidence) process.exit(1);
if (hmacSha256('tenant', jcs({ type: 'type/v1', id: 'ID:AbC/01' })) === expectedTenant) process.exit(1);
if (hmacSha256('tenant', tenantContext, Buffer.alloc(32, 0xff)) === expectedTenant) process.exit(1);
for (const substitution of [
  { eventType: 'security.authentication.invalid' },
  { eventVersion: 2 },
  { evidenceVersion: 2 },
  { fieldName: 'other_field' },
]) {
  if (hmacSha256('evidence', jcs({ ...JSON.parse(evidenceContext), ...substitution })) === expectedEvidence) process.exit(1);
}
if (jcs({ slash: '/', value: '\"\\' }) !== '{"slash":"/","value":"\\\"\\\\"}') process.exit(1);
if (jcs(JSON.parse('{"value":"\\/"}')) === '{"value":"\\/"}') process.exit(1);
if (jcs(JSON.parse('{"value":"\\u2028"}')) === '{"value":"\\u2028"}') process.exit(1);
if (hmacSha256('resource', tenantContext) === expectedTenant) process.exit(1);
if (createHash('sha256').update(Buffer.concat([lp('blackops.audit.opaque-id.v1'), lp('tenant'), lp(tenantContext)])).digest('hex') === expectedTenant.slice(8)) process.exit(1);
console.log('framework audit catalog golden: PASS');
