import { createHmac, createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

const fixturePath = new URL('./observed-audit-record-v1.jsonl', import.meta.url);
const row = JSON.parse(readFileSync(fixturePath, 'utf8').trim());
const logical = row.logical;
const key = Buffer.from(Array.from({ length: 32 }, (_, index) => index));

const utf16Compare = (left, right) => left < right ? -1 : left > right ? 1 : 0;
const jcs = (value) => {
  if (Array.isArray(value)) return `[${value.map(jcs).join(',')}]`;
  if (value !== null && typeof value === 'object') {
    return `{${Object.keys(value).sort(utf16Compare).map((name) => `${JSON.stringify(name)}:${jcs(value[name])}`).join(',')}}`;
  }
  if (typeof value === 'number' && (!Number.isSafeInteger(value))) throw new Error('unsafe number');
  return JSON.stringify(value);
};
const lp = (value) => {
  const bytes = Buffer.from(value, 'utf8');
  const length = Buffer.alloc(4);
  length.writeUInt32BE(bytes.length);
  return Buffer.concat([length, bytes]);
};
const derive = (purpose, context) => {
  const preimage = Buffer.concat([
    lp('blackops.audit.opaque-id.v1'),
    lp(purpose),
    lp(context),
  ]);
  return `oid:v1:${createHmac('sha256', key).update(preimage).digest('hex')}`;
};
const scalar = (purpose, value) => derive(purpose, value);
const reference = (purpose, value, typed = false) => value === null ? null : derive(purpose, typed ? jcs(value) : typeof value === 'string' ? value : value.id);

const projection = {
  schemaVersion: 1,
  kind: 'audit',
  projection: 'observed_v1',
  recordRef: scalar('record', logical.recordId),
  event: logical.event,
  action: {
    ref: scalar('action', logical.action.id),
    type: logical.action.type,
    phase: logical.action.phase,
  },
  actor: {
    principalRef: reference('principal', logical.actor.principal),
    executionRef: reference('execution', logical.actor.execution),
  },
  resource: {
    type: logical.resource.type,
    ref: scalar('resource', logical.resource.id),
  },
  outcome: logical.outcome,
  policy: {
    decision: logical.policy.decision,
    reasonCode: logical.policy.reasonCode,
    auditDelivery: logical.policy.auditDelivery,
  },
  tenantRef: reference('tenant', logical.tenant, true),
  time: logical.time,
  source: {
    owner: logical.source.owner,
    runtime: logical.source.runtime,
  },
  correlation: {
    operationRef: reference('operation', logical.correlation.operationId),
    correlationRef: reference('correlation', logical.correlation.correlationId),
    causationRef: reference('causation', logical.correlation.causationId),
    attempt: logical.correlation.attempt,
    traceRef: reference('trace', logical.correlation.traceId),
    spanRef: reference('span', logical.correlation.spanId),
    sampled: logical.correlation.sampled,
  },
  evidence: {
    version: logical.evidence.version,
    result_code: logical.evidence.result_code,
    opaque_ref: logical.evidence.opaque_ref,
  },
};
const expected = `${jcs(projection)}\n`;

if (process.argv.includes('--print')) {
  process.stdout.write(expected);
} else {
  if (row.expected !== expected) throw new Error('observed projection golden mismatch');
  if (!expected.endsWith('\n') || expected.slice(0, -1).includes('\n') || expected.includes('\r')) {
    throw new Error('projection is not exactly one LF terminated');
  }
  const omittedEvidenceFields = ['missing_canary', 'null_canary', 'integer_canary', 'boolean_canary'];
  for (const field of omittedEvidenceFields) {
    if (Object.hasOwn(projection.evidence, field)) throw new Error('omitted evidence field projected: ' + field);
    if (expected.includes('"' + field + '"')) throw new Error('omitted evidence field leaked: ' + field);
  }
  for (const canary of [
    logical.actor.principal.type,
    logical.actor.execution.type,
    logical.tenant.type,
    logical.correlation.traceId,
    logical.correlation.spanId,
    String(logical.evidence.integer_canary),
  ]) {
    if (expected.includes(canary)) throw new Error('projection canary leaked: ' + canary);
  }
  if (Buffer.byteLength(expected) > 65536) throw new Error('projection exceeds byte cap');
  process.stdout.write('observed projection golden: PASS\n');
}
