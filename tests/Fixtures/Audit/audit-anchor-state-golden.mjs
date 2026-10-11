import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import test from 'node:test';

const directory = dirname(fileURLToPath(import.meta.url));
const fixturePath = join(directory, 'audit-anchor-state-v1.json');
const maxSafeInteger = 9_007_199_254_740_991n;
const microsecondsPerSecond = 1_000_000n;
const microsecondsPerMinute = 60n * microsecondsPerSecond;
const microsecondsPerHour = 60n * microsecondsPerMinute;
const microsecondsPerDay = 24n * microsecondsPerHour;
const retryDelays = [1, 300, 86_400];

// Independently reviewed vectors keep the fixture builder from becoming its
// own oracle when a formula or transition field changes.
const expectedReceiptHashes = {
  preimage: 'b87756b44006ef717689c27d581998be012c0b3cf7bc24e3adacc1f3d143b770',
  bytes: '91bde0353d97c297073679b6882d34c5e566bb6f362269fb8e6591ec4ad1ce4d',
  public: '1cb627144a82d9cae6f10b990dcb8f4ba48f201a3b914711a0ca1860a3cc9ca7',
};

const expectedFoundReceiptHashes = {
  preimage: 'ae31081fcb9f016affcdfd636c3a0fa169751bdbfd70cbd32c8d67adf26a5053',
  bytes: '5c4549668dd96f907599a5a63279a3a998a34cf265468aff44b653233e41297d',
  public: 'dee74d940d56da363df4b64b0706a903991fbc66374acafed054e8b795892416',
};

const expectedGenesisStateHash = '327d1fb766f24f54cbedd1a816db182fda1c7a89057cf593623e082633683488';

const expectedBranchSchedules = {
  pre_call_lookup_unavailable: {
    '1': '2030-01-02T03:04:06.123456Z',
    '300': '2030-01-02T03:09:05.123456Z',
    '86400': '2030-01-03T03:04:05.123456Z',
  },
  pre_call_pending_reclaim: {
    '1': '2030-01-02T03:09:06.123456Z',
    '300': '2030-01-02T03:14:05.123456Z',
    '86400': '2030-01-03T03:09:05.123456Z',
  },
  pre_call_absent_reclaim: {
    '1': '2030-01-02T03:09:06.123456Z',
    '300': '2030-01-02T03:14:05.123456Z',
    '86400': '2030-01-03T03:09:05.123456Z',
  },
  post_call_result_unavailable: {
    '1': '2030-01-02T03:04:06.123456Z',
    '300': '2030-01-02T03:09:05.123456Z',
    '86400': '2030-01-03T03:04:05.123456Z',
  },
  post_call_reclaim: {
    '1': '2030-01-02T03:09:06.123456Z',
    '300': '2030-01-02T03:14:05.123456Z',
    '86400': '2030-01-03T03:09:05.123456Z',
  },
  post_call_reconciliation_unavailable: {
    '1': '2030-01-02T03:09:06.123456Z',
    '300': '2030-01-02T03:14:05.123456Z',
    '86400': '2030-01-03T03:09:05.123456Z',
  },
  post_call_absent_below32: {
    '1': '2030-01-02T03:14:06.123456Z',
    '300': '2030-01-02T03:19:05.123456Z',
    '86400': '2030-01-03T03:14:05.123456Z',
  },
};

const expectedBranchStateHashes = {
  pre_call_lookup_unavailable: [
    'c4bd2a430d3e9bca72fdc5f2059e5b5fd02753cd7cb8e930bfb51b8ff1a9bb98',
    'f35dc6e2720a95dc05abf907b9828e7175aaa9bcdf38f50862575034dddafc89',
  ],
  pre_call_pending_reclaim: [
    '414df7f5cc04ee208b3e0508f6b68f9c3e77f55685dd5fdc5f0d4f93fb4e36a0',
    '66272ddbfa3d3cb1b96a637792d4c891fdd8e201c3aca2aeb3749db112be8fc8',
  ],
  pre_call_absent_reclaim: [
    '76fcc00ea249a92031574b6a05713b64386002a229f07f7c213984fab9495357',
    '8bf5e6fce963ef59691c8068c75ce1361e889eda6c343466d0dda3e59e81bc43',
    'b4732422f8a969bb951376643b4a5b2eeb8615005f65ad70d6e93de6b3129c10',
  ],
  post_call_result_unavailable: [
    'a5b8519d729adf43b6ec318369edf83775a8288b8827e4586de17c8257b33ffe',
    'abfb499e7076a1d773c7e5d4ed407ee0c8cc9e221ab665eb4f6f56deed097eef',
    '7d6a6410339fcda691a8e2f3d0a7bda3e4025681f384990aed1cfdba083f1bf6',
    '63c0eaa72d9769c0cdaa4aaf21c01bf21fb9cb1279b360f0a42f9fee5479ea68',
  ],
  post_call_reconciliation_unavailable: [
    'b0c72e45e84c04e935ca439e6bcdd7f050060a76d570d9d33ece3eb93418892d',
    'ccd5273239f653f9680cf6196a518a5b4bf07796a8cf28792607c1143a4f5125',
    'd33a82d5fc8a3642844d62524c3023422c897ccda3e3a678fdcaaeff4beb7562',
    '749a34e03dfe1ea3970120f677d4ebcd8f9b8089b6c590933bfc04db07ccbd4b',
    '1554749ae54112b8575bfb078e14d19956d9577882694db3481328dcaca20e86',
    '9e28d769c42eae668641219e9daea25093a5fdd7c8758d8dbbe409738f84b1fa',
  ],
  post_call_absent_below32: [
    '582d277b9180f046e15eb9128986386e5aa618987c0633d04fb5ce19bfdf5a38',
    'c50f0d613da0af9276fef8bfe1a791fc08662ac6ab9b06caf770a06e53488a72',
    '97767bf92aa1a999ba3427483188e1f4b27ac57534c38ce5c7110533c1e20d1c',
    '0cf0e0cef5cd754a882381e9b845648dce92e37e457eb084ada1520273aa6f45',
    '3aa31b438ecc63e24ebaf27676b89f03a2cbbb6cf3e835f441a96a02415ff56a',
    '2ac5efd99cd3b49807e0e69221a3ac912b52cdb320ec4b9db4615e8c0db86d41',
  ],
  post_call_reclaim: [
    'a5b8519d729adf43b6ec318369edf83775a8288b8827e4586de17c8257b33ffe',
    'abfb499e7076a1d773c7e5d4ed407ee0c8cc9e221ab665eb4f6f56deed097eef',
    '7d6a6410339fcda691a8e2f3d0a7bda3e4025681f384990aed1cfdba083f1bf6',
    'e80cc7db7909606232b70096e7a844ebd01be5c35c197bc3531e8bc8a43df2d9',
  ],
};

const expectedCompleteStateHashes = [
  '0e7afa6a6ffa850ebfd7e4b2202fc6aa28d8ecc6fb424874969064c17a928a86',
  'a02a627f7af18d89c93acf8075bd5a355ec652adeab9c776a7bbaf020bd659f6',
  '0df11f0fba544d3510b12ccef347881e02deaebfe2684df1a53a9c1b72e0023d',
  'be8f9d6357e34041a89385b269514b8394d937a164d804cc3a3c39684bef4ab9',
  'cb32ecb9dd52e7d78619ad21b9e0a294842360e8a8671d789a7a2c1034f7efb6',
  'ceb93b2783583276b1a9ff9ac8ae21b0ed2e2ba81a710f385c33b6efe9790c5b',
  '19bb7cf289bc0a5623121b037b21201fb7db7154b4d514861ebb42eeb0ace90a',
  'b52f3bb801917f31ea7ad4183d520410d7c1df71b1623c65618b15b76d349df9',
  '70904e93da688c86264fd23f08cdd61cfe60f869ce42468205241a9c1f1ba14d',
  '8ce8b6a82e16c14d7f5b3769073ec45603b56152152ac73b3ba857f2fb048603',
];

const expectedFoundStateHashes = [
  'b2e66cec79fee06fc03035e729991090925149ca157b243aaa54dd0681ab189e',
  '626d40300ee56498b41f0d9b40a3070882dfa07e1727c2c237b20659a52df167',
  'f3c447713fa0bd4b4d92f003b0d425db4ba4ae1037c7908258af7f1929f7464f',
  '844683bbaed3c30213e294785a6c4535ee0d433a6c6d4f22a0b40b7d5d0db5fa',
  '453d5f002a18ed30214ff7a1eed729d6622c1bdb9298af3b6986fc46e0b82f2e',
  'f471c23e9d776f0a2e2fba5f7bd5ab4ead58e31809eef3807d01600e9db27ce2',
  '5e66c5da65a651e47ca72722296af530c59ea36e21d063ade8e13f9153279ef6',
  '87c02b9544d2332cdf86907805e8c64409e67f11c13a254c8f1fc39dc0934201',
];

function floorDiv(value, divisor) {
  const quotient = value / divisor;
  return value % divisor < 0n ? quotient - 1n : quotient;
}

function daysFromCivil(year, month, day) {
  let adjustedYear = BigInt(year);
  const monthValue = BigInt(month);
  const dayValue = BigInt(day);
  adjustedYear -= monthValue <= 2n ? 1n : 0n;
  const era = floorDiv(adjustedYear, 400n);
  const yearOfEra = adjustedYear - era * 400n;
  const monthPrime = monthValue + (monthValue > 2n ? -3n : 9n);
  const dayOfYear = (153n * monthPrime + 2n) / 5n + dayValue - 1n;
  const dayOfEra = yearOfEra * 365n + yearOfEra / 4n - yearOfEra / 100n + dayOfYear;
  return era * 146097n + dayOfEra - 719468n;
}

function civilFromDays(days) {
  let z = days + 719468n;
  const era = floorDiv(z, 146097n);
  const dayOfEra = z - era * 146097n;
  const yearOfEra = (dayOfEra - dayOfEra / 1460n + dayOfEra / 36524n - dayOfEra / 146096n) / 365n;
  let year = yearOfEra + era * 400n;
  const dayOfYear = dayOfEra - (365n * yearOfEra + yearOfEra / 4n - yearOfEra / 100n);
  const monthPrime = (5n * dayOfYear + 2n) / 153n;
  const day = dayOfYear - (153n * monthPrime + 2n) / 5n + 1n;
  const month = monthPrime + (monthPrime < 10n ? 3n : -9n);
  year += month <= 2n ? 1n : 0n;
  return { year: Number(year), month: Number(month), day: Number(day) };
}

function parseTimestamp(value) {
  assert.equal(typeof value, 'string');
  const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})\.(\d{6})Z$/.exec(value);
  assert.ok(match, `timestamp grammar: ${value}`);
  const [, yearText, monthText, dayText, hourText, minuteText, secondText, fractionText] = match;
  const year = Number(yearText);
  const month = Number(monthText);
  const day = Number(dayText);
  const hour = Number(hourText);
  const minute = Number(minuteText);
  const second = Number(secondText);
  assert.ok(month >= 1 && month <= 12 && hour < 24 && minute < 60 && second < 60);
  const dateMicros = daysFromCivil(year, month, day) * microsecondsPerDay;
  const valueMicros = dateMicros
    + BigInt(hour) * microsecondsPerHour
    + BigInt(minute) * microsecondsPerMinute
    + BigInt(second) * microsecondsPerSecond
    + BigInt(fractionText);
  assert.equal(formatTimestamp(valueMicros), value, `calendar date: ${value}`);
  return valueMicros;
}

function formatTimestamp(value) {
  const day = floorDiv(value, microsecondsPerDay);
  const remainder = value - day * microsecondsPerDay;
  const date = civilFromDays(day);
  assert.ok(date.year >= 0 && date.year <= 9999, 'four digit year boundary');
  const hour = remainder / microsecondsPerHour;
  const minute = remainder % microsecondsPerHour / microsecondsPerMinute;
  const second = remainder % microsecondsPerMinute / microsecondsPerSecond;
  const fraction = remainder % microsecondsPerSecond;
  return `${String(date.year).padStart(4, '0')}-${String(date.month).padStart(2, '0')}-${String(date.day).padStart(2, '0')}T${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:${String(second).padStart(2, '0')}.${String(fraction).padStart(6, '0')}Z`;
}

function addSecondsMicros(value, seconds) {
  assert.ok(Number.isInteger(seconds) && seconds >= 1 && seconds <= 86_400);
  const next = parseTimestamp(value) + BigInt(seconds) * microsecondsPerSecond;
  return formatTimestamp(next);
}

function lp(value) {
  const bytes = Buffer.from(value, 'utf8');
  const length = Buffer.alloc(4);
  length.writeUInt32BE(bytes.length);
  return Buffer.concat([length, bytes]);
}

function typed(domain, bytes) {
  const domainBytes = Buffer.from(domain, 'utf8');
  const bodyBytes = Buffer.isBuffer(bytes) ? bytes : Buffer.from(bytes, 'utf8');
  return Buffer.concat([
    Buffer.from(`s:${domainBytes.byteLength}:`, 'utf8'),
    domainBytes,
    Buffer.from(`b:${bodyBytes.byteLength}:`, 'utf8'),
    bodyBytes,
  ]);
}

function canonical(value) {
  if (Array.isArray(value)) return `[${value.map(canonical).join(',')}]`;
  if (value !== null && typeof value === 'object') {
    return `{${Object.keys(value).sort().map((key) => `${JSON.stringify(key)}:${canonical(value[key])}`).join(',')}}`;
  }
  return JSON.stringify(value);
}

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

function digest(domain, ...parts) {
  return sha256(Buffer.concat([lp(domain), ...parts.map(lp)]));
}

function typedDigest(domain, bytes) {
  return sha256(typed(domain, bytes));
}

function receiptDigest(domain, body) {
  const bytes = Buffer.from(canonical(body), 'utf8');
  const preimage = typed(domain, bytes);
  return { bytes, preimage, hash: sha256(preimage) };
}

function publicReceiptHash(body) {
  return receiptDigest('blackops.audit.anchor.hash.v1', body).hash;
}

function stateHash(intent) {
  const copy = { ...intent };
  delete copy.stateHash;
  return digest('blackops.anchor.state.v1', canonical(copy));
}

function idempotencyHash(intentId, rawKey) {
  return digest('blackops.anchor.idempotency.v1', intentId, rawKey);
}

function receiptBody(overrides = {}) {
  return {
    schemaVersion: 1,
    kind: 'audit_anchor_receipt',
    anchorReceiptId: 'receipt-001',
    partitionId: 'system:global',
    segmentId: '01941f29-7c00-7000-8000-000000000102',
    checkpointId: 'checkpoint-001',
    checkpointHash: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    checkpointBytesHash: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    anchorStoreRef: 'anchor-store',
    anchorObjectRef: 'object-001',
    anchorObjectVersion: 1,
    previousAnchorReceiptHash: null,
    anchoredAt: '2030-01-02T03:05:05.123456Z',
    signedAt: '2030-01-02T03:10:05.123456Z',
    signatureAlgorithm: 'ed25519-v1',
    signingKeyRef: 'signing-key',
    ...overrides,
  };
}

function intentFixture() {
  const intent = {
    schemaVersion: 1,
    kind: 'audit_anchor_intent',
    intentId: 'intent-001',
    state: 'prepared',
    partitionId: 'system:global',
    checkpointId: 'checkpoint-001',
    checkpointHash: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    checkpointBytesHash: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    anchorStoreRef: 'anchor-store',
    anchorObjectRef: 'object-001',
    expectedPredecessorVersion: null,
    expectedPredecessorHash: null,
    conditionalCreateToken: 'conditional-token-001',
    idempotencyKeyHash: idempotencyHash('intent-001', 'raw-idempotency-key'),
    acceptedObjectVersion: null,
    acceptedObjectHash: null,
    acceptedAnchoredAt: null,
    anchorReceiptId: null,
    segmentId: null,
    previousAnchorReceiptHash: null,
    receiptSigningKeyRef: null,
    receiptSignedAt: null,
    receiptPreimageHash: null,
    receiptBytesHash: null,
    anchorReceiptHash: null,
    externalCallPhase: 'pre_call',
    preCallClaimMode: 'new_attempt',
    preCallLookupStatus: null,
    leaseFencingToken: null,
    leaseExpiresAt: null,
    attempt: 0,
    nextAvailableAt: null,
    terminalReasonCode: null,
    version: 1,
    previousStateHash: null,
    stateHash: null,
    updatedAt: '2030-01-02T03:04:05.123456Z',
    acknowledgedAt: null,
  };
  intent.stateHash = stateHash(intent);
  return intent;
}

function revise(previous, operation, now, changes) {
  const next = { ...previous, ...changes };
  next.version = previous.version + 1;
  next.previousStateHash = previous.stateHash;
  next.updatedAt = now;
  next.stateHash = stateHash(next);
  return {
    operation,
    fromVersion: previous.version,
    toVersion: next.version,
    previousStateHash: next.previousStateHash,
    updatedAt: now,
    scheduledAt: next.nextAvailableAt,
    successorStateHash: next.stateHash,
    successorBytes: canonical(next),
    intent: next,
  };
}

function branchTrace(name, transitions, scheduleAnchor) {
  const finalTransition = transitions[transitions.length - 1];
  return {
    scheduleAnchor,
    scheduledAt: finalTransition.scheduledAt,
    transitions: transitions.map(({ intent, ...transition }) => transition),
  };
}

function buildTrace() {
  const genesis = intentFixture();
  const now = genesis.updatedAt;
  const leaseExpiry = addSecondsMicros(now, 300);
  const retryAt = addSecondsMicros(now, 300);
  const laterRetryAt = addSecondsMicros(retryAt, 300);
  const postCallRetryAt = addSecondsMicros(leaseExpiry, 300);
  const branches = {};

  let submission = revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: 'node-submission-token',
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  branches.pre_call_lookup_unavailable = branchTrace(
    'pre_call_lookup_unavailable',
    [submission, revise(submission.intent, 'lookup_unavailable', now, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      preCallLookupStatus: null,
      preCallClaimMode: 'retry_same_attempt',
      nextAvailableAt: retryAt,
    })],
    now,
  );

  submission = revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: 'node-pending-reclaim-token',
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  branches.pre_call_pending_reclaim = branchTrace(
    'pre_call_pending_reclaim',
    [submission, revise(submission.intent, 'pre_call_reclaim', leaseExpiry, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      preCallLookupStatus: null,
      preCallClaimMode: 'retry_same_attempt',
      nextAvailableAt: laterRetryAt,
    })],
    leaseExpiry,
  );

  submission = revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: 'node-absent-reclaim-token',
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  const absent = revise(submission.intent, 'lookup_absent', now, {
    preCallLookupStatus: 'absent',
  });
  branches.pre_call_absent_reclaim = branchTrace(
    'pre_call_absent_reclaim',
    [submission, absent, revise(absent.intent, 'pre_call_reclaim', leaseExpiry, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      preCallLookupStatus: null,
      preCallClaimMode: 'retry_same_attempt',
      nextAvailableAt: laterRetryAt,
    })],
    leaseExpiry,
  );

  submission = revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: 'node-result-token',
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  const absentForCall = revise(submission.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const callStarted = revise(absentForCall.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  branches.post_call_result_unavailable = branchTrace(
    'post_call_result_unavailable',
    [submission, absentForCall, callStarted, revise(callStarted.intent, 'result_unavailable', now, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      nextAvailableAt: retryAt,
    })],
    now,
  );

  branches.post_call_reclaim = branchTrace(
    'post_call_reclaim',
    [submission, absentForCall, callStarted, revise(callStarted.intent, 'post_call_reclaim', leaseExpiry, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      nextAvailableAt: postCallRetryAt,
    })],
    leaseExpiry,
  );

  submission = revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: 'node-reconciliation-token',
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  const absentForReconcile = revise(submission.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const uncertainForReconcile = revise(absentForReconcile.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  const unavailableForReconcile = revise(uncertainForReconcile.intent, 'result_unavailable', now, {
    leaseFencingToken: null,
    leaseExpiresAt: null,
    nextAvailableAt: retryAt,
  });
  const reconciliationClaim = revise(unavailableForReconcile.intent, 'external_reconciliation_claim', retryAt, {
    leaseFencingToken: 'node-reconciliation-claim-token',
    leaseExpiresAt: laterRetryAt,
    nextAvailableAt: null,
  });
  branches.post_call_reconciliation_unavailable = branchTrace(
    'post_call_reconciliation_unavailable',
    [submission, absentForReconcile, uncertainForReconcile, unavailableForReconcile, reconciliationClaim,
      revise(reconciliationClaim.intent, 'reconciliation_unavailable', retryAt, {
        leaseFencingToken: null,
        leaseExpiresAt: null,
        nextAvailableAt: laterRetryAt,
      })],
    retryAt,
  );

  submission = revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: 'node-post-absent-token',
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  const absentForPost = revise(submission.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const uncertainForPost = revise(absentForPost.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  const reclaimedForPost = revise(uncertainForPost.intent, 'post_call_reclaim', leaseExpiry, {
    leaseFencingToken: null,
    leaseExpiresAt: null,
    nextAvailableAt: postCallRetryAt,
  });
  const reconciliationClaimForPost = revise(reclaimedForPost.intent, 'external_reconciliation_claim', postCallRetryAt, {
    leaseFencingToken: 'node-post-reconciliation-token',
    leaseExpiresAt: addSecondsMicros(postCallRetryAt, 300),
    nextAvailableAt: null,
  });
  branches.post_call_absent_below32 = branchTrace(
    'post_call_absent_below32',
    [submission, absentForPost, uncertainForPost, reclaimedForPost, reconciliationClaimForPost,
      revise(reconciliationClaimForPost.intent, 'reconciliation_absent', postCallRetryAt, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      externalCallPhase: 'pre_call',
      preCallClaimMode: 'new_attempt',
      nextAvailableAt: addSecondsMicros(postCallRetryAt, 300),
    })],
    postCallRetryAt,
  );

  return { genesis, leaseExpiry, branches };
}

function buildDelayBranches(delay) {
  const genesis = intentFixture();
  const now = genesis.updatedAt;
  const leaseExpiry = addSecondsMicros(now, 300);
  const retryAt = addSecondsMicros(now, delay);
  const leaseRetryAt = addSecondsMicros(leaseExpiry, delay);
  const reconciliationRetryAt = addSecondsMicros(retryAt, delay);
  const branches = {};
  const submission = (token) => revise(genesis, 'external_submission_claim', now, {
    leaseFencingToken: token,
    leaseExpiresAt: leaseExpiry,
    attempt: 1,
    preCallLookupStatus: 'pending',
  });

  let claimed = submission(`external-token-lookup-${delay}`);
  branches.pre_call_lookup_unavailable = branchTrace(
    'pre_call_lookup_unavailable',
    [claimed, revise(claimed.intent, 'lookup_unavailable', now, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      preCallLookupStatus: null,
      preCallClaimMode: 'retry_same_attempt',
      nextAvailableAt: retryAt,
    })],
    now,
  );

  claimed = submission(`pending-token-${delay}`);
  branches.pre_call_pending_reclaim = branchTrace(
    'pre_call_pending_reclaim',
    [claimed, revise(claimed.intent, 'pre_call_reclaim', leaseExpiry, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      preCallLookupStatus: null,
      preCallClaimMode: 'retry_same_attempt',
      nextAvailableAt: leaseRetryAt,
    })],
    leaseExpiry,
  );

  claimed = submission(`absent-token-${delay}`);
  const absent = revise(claimed.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  branches.pre_call_absent_reclaim = branchTrace(
    'pre_call_absent_reclaim',
    [claimed, absent, revise(absent.intent, 'pre_call_reclaim', leaseExpiry, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      preCallLookupStatus: null,
      preCallClaimMode: 'retry_same_attempt',
      nextAvailableAt: leaseRetryAt,
    })],
    leaseExpiry,
  );

  claimed = submission(`result-token-${delay}`);
  const absentForCall = revise(claimed.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const callStarted = revise(absentForCall.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  branches.post_call_result_unavailable = branchTrace(
    'post_call_result_unavailable',
    [claimed, absentForCall, callStarted, revise(callStarted.intent, 'result_unavailable', now, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      nextAvailableAt: retryAt,
    })],
    now,
  );

  claimed = submission(`post-reclaim-token-${delay}`);
  const absentForReclaim = revise(claimed.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const uncertainForReclaim = revise(absentForReclaim.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  branches.post_call_reclaim = branchTrace(
    'post_call_reclaim',
    [claimed, absentForReclaim, uncertainForReclaim, revise(uncertainForReclaim.intent, 'post_call_reclaim', leaseExpiry, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      nextAvailableAt: leaseRetryAt,
    })],
    leaseExpiry,
  );

  claimed = submission(`reconciliation-base-token-${delay}`);
  const absentForReconcile = revise(claimed.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const uncertainForReconcile = revise(absentForReconcile.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  const unavailableForReconcile = revise(uncertainForReconcile.intent, 'result_unavailable', now, {
    leaseFencingToken: null,
    leaseExpiresAt: null,
    nextAvailableAt: retryAt,
  });
  const reconciliationClaim = revise(unavailableForReconcile.intent, 'external_reconciliation_claim', retryAt, {
    leaseFencingToken: `reconciliation-token-${delay}`,
    leaseExpiresAt: addSecondsMicros(retryAt, 300),
    nextAvailableAt: null,
  });
  branches.post_call_reconciliation_unavailable = branchTrace(
    'post_call_reconciliation_unavailable',
    [claimed, absentForReconcile, uncertainForReconcile, unavailableForReconcile, reconciliationClaim,
      revise(reconciliationClaim.intent, 'reconciliation_unavailable', retryAt, {
        leaseFencingToken: null,
        leaseExpiresAt: null,
        nextAvailableAt: reconciliationRetryAt,
      })],
    retryAt,
  );

  claimed = submission(`post-absent-token-${delay}`);
  const absentForPost = revise(claimed.intent, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
  const uncertainForPost = revise(absentForPost.intent, 'call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  const reclaimedForPost = revise(uncertainForPost.intent, 'post_call_reclaim', leaseExpiry, {
    leaseFencingToken: null,
    leaseExpiresAt: null,
    nextAvailableAt: leaseRetryAt,
  });
  const reconciliationClaimForPost = revise(reclaimedForPost.intent, 'external_reconciliation_claim', leaseRetryAt, {
    leaseFencingToken: `post-reconciliation-token-${delay}`,
    leaseExpiresAt: addSecondsMicros(leaseRetryAt, 300),
    nextAvailableAt: null,
  });
  branches.post_call_absent_below32 = branchTrace(
    'post_call_absent_below32',
    [claimed, absentForPost, uncertainForPost, reclaimedForPost, reconciliationClaimForPost,
      revise(reconciliationClaimForPost.intent, 'reconciliation_absent', leaseRetryAt, {
        leaseFencingToken: null,
        leaseExpiresAt: null,
        externalCallPhase: 'pre_call',
        preCallClaimMode: 'new_attempt',
        preCallLookupStatus: null,
        nextAvailableAt: addSecondsMicros(leaseRetryAt, delay),
      })],
    leaseRetryAt,
  );

  return branches;
}

function buildAttemptTrace(delay, targetAttempt) {
  const genesis = intentFixture();
  const transitions = [];
  let current = genesis;
  let now = genesis.updatedAt;
  for (let attempt = 1; attempt <= targetAttempt; ++attempt) {
    const claimed = revise(current, 'external_submission_claim', now, {
      leaseFencingToken: `external-token-${attempt}`,
      leaseExpiresAt: addSecondsMicros(now, 300),
      attempt,
      preCallLookupStatus: 'pending',
      nextAvailableAt: null,
    });
    transitions.push(claimed);
    current = claimed.intent;
    const absent = revise(current, 'lookup_absent', now, { preCallLookupStatus: 'absent' });
    transitions.push(absent);
    current = absent.intent;
    const started = revise(current, 'call_start', now, {
      externalCallPhase: 'post_call_uncertain',
      preCallClaimMode: null,
      preCallLookupStatus: null,
    });
    transitions.push(started);
    current = started.intent;
    const retryAt = addSecondsMicros(now, delay);
    const unavailable = revise(current, 'result_unavailable', now, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      nextAvailableAt: retryAt,
    });
    transitions.push(unavailable);
    current = unavailable.intent;
    if (attempt === targetAttempt) {
      break;
    }
    const reconciliationClaim = revise(current, 'external_reconciliation_claim', retryAt, {
      leaseFencingToken: `reconciliation-token-${attempt}`,
      leaseExpiresAt: addSecondsMicros(retryAt, 300),
      nextAvailableAt: null,
    });
    transitions.push(reconciliationClaim);
    current = reconciliationClaim.intent;
    const nextAttemptAt = addSecondsMicros(retryAt, delay);
    const absentReconciliation = revise(current, 'reconciliation_absent', retryAt, {
      leaseFencingToken: null,
      leaseExpiresAt: null,
      externalCallPhase: 'pre_call',
      preCallClaimMode: 'new_attempt',
      preCallLookupStatus: null,
      nextAvailableAt: nextAttemptAt,
    });
    transitions.push(absentReconciliation);
    current = absentReconciliation.intent;
    now = nextAttemptAt;
  }
  return transitions.map(({ intent, ...transition }) => transition);
}

function buildCompleteTrace(receipt) {
  const genesis = intentFixture();
  const now = genesis.updatedAt;
  const signedAt = receipt.signedAt;
  const leaseExpiry = addSecondsMicros(signedAt, 300);
  const transitions = [];
  let current = genesis;
  const advance = (operation, transitionNow, changes) => {
    const transition = revise(current, operation, transitionNow, changes);
    transitions.push(transition);
    current = transition.intent;
  };

  advance('external_submission_claim', now, {
    leaseFencingToken: 'external-token-1',
    leaseExpiresAt: addSecondsMicros(now, 300),
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  advance('lookup_absent', now, { preCallLookupStatus: 'absent' });
  advance('call_start', now, {
    externalCallPhase: 'post_call_uncertain',
    preCallClaimMode: null,
    preCallLookupStatus: null,
  });
  advance('result_accepted', now, {
    state: 'externally_accepted',
    acceptedObjectVersion: receipt.anchorObjectVersion,
    acceptedObjectHash: receipt.checkpointBytesHash,
    acceptedAnchoredAt: receipt.anchoredAt,
    anchorReceiptId: receipt.anchorReceiptId,
    segmentId: receipt.segmentId,
    previousAnchorReceiptHash: receipt.previousAnchorReceiptHash,
    externalCallPhase: null,
    preCallClaimMode: null,
    preCallLookupStatus: null,
    leaseFencingToken: null,
    leaseExpiresAt: null,
    nextAvailableAt: null,
  });
  advance('receipt_input_claim', signedAt, {
    leaseFencingToken: 'input-token',
    leaseExpiresAt: leaseExpiry,
  });
  advance('receipt_input_persist', signedAt, {
    state: 'receipt_inputs_persisted',
    receiptSigningKeyRef: receipt.signingKeyRef,
    receiptSignedAt: signedAt,
    receiptPreimageHash: receipt.hashes.receiptPreimageHash,
    receiptBytesHash: receipt.hashes.receiptBytesHash,
    leaseFencingToken: null,
    leaseExpiresAt: null,
  });
  advance('receipt_persist_claim', signedAt, {
    leaseFencingToken: 'persist-token',
    leaseExpiresAt: leaseExpiry,
  });
  advance('receipt_persist', signedAt, {
    state: 'receipt_persisted',
    anchorReceiptHash: receipt.hashes.publicReceiptHash,
    leaseFencingToken: null,
    leaseExpiresAt: null,
  });
  advance('acknowledgement_claim', signedAt, {
    leaseFencingToken: 'ack-token',
    leaseExpiresAt: leaseExpiry,
  });
  advance('acknowledge', signedAt, {
    state: 'acknowledged',
    acknowledgedAt: signedAt,
    leaseFencingToken: null,
    leaseExpiresAt: null,
  });

  return transitions.map(({ intent, ...transition }) => transition);
}

function foundLookup() {
  const request = {
    intentId: 'intent-001',
    partitionId: 'system:global',
    checkpointId: 'checkpoint-001',
    checkpointHash: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    checkpointBytesHash: 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
    anchorStoreRef: 'anchor-store',
    anchorObjectRef: 'object-001',
    expectedPredecessorVersion: 7,
    expectedPredecessorHash: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
    conditionalCreateToken: 'conditional-token-001',
  };
  const binding = {
    segmentId: '01941f29-7c00-7000-8000-000000000102',
    previousAnchorReceiptHash: 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
    checkpointSignedAt: '2030-01-02T03:03:05.123456Z',
    anchorReceiptId: 'receipt-001',
  };
  const foundObject = {
    anchorObjectRef: 'object-001',
    anchorObjectVersion: 8,
    acceptedObjectHash: request.checkpointBytesHash,
    acceptedAnchoredAt: '2030-01-02T03:05:05.123456Z',
    segmentId: binding.segmentId,
    previousAnchorReceiptHash: binding.previousAnchorReceiptHash,
  };
  return {
    request,
    binding,
    reconciliation: {
      schemaVersion: 1,
      kind: 'audit_anchor_reconciliation',
      outcome: 'external_object_found',
      foundObject,
      reasonCode: null,
    },
  };
}

function buildFoundCompleteTrace(receipt) {
  const lookup = foundLookup();
  const genesis = {
    ...intentFixture(),
    expectedPredecessorVersion: lookup.request.expectedPredecessorVersion,
    expectedPredecessorHash: lookup.request.expectedPredecessorHash,
  };
  genesis.stateHash = stateHash(genesis);
  const now = genesis.updatedAt;
  const signedAt = receipt.signedAt;
  const leaseExpiry = addSecondsMicros(signedAt, 300);
  const transitions = [];
  let current = genesis;
  const advance = (operation, transitionNow, changes) => {
    const transition = revise(current, operation, transitionNow, changes);
    transitions.push(transition);
    current = transition.intent;
  };

  advance('external_submission_claim', now, {
    leaseFencingToken: 'found-external-token',
    leaseExpiresAt: addSecondsMicros(now, 300),
    attempt: 1,
    preCallLookupStatus: 'pending',
  });
  advance('lookup_found', now, {
    state: 'externally_accepted',
    acceptedObjectVersion: lookup.reconciliation.foundObject.anchorObjectVersion,
    acceptedObjectHash: lookup.reconciliation.foundObject.acceptedObjectHash,
    acceptedAnchoredAt: lookup.reconciliation.foundObject.acceptedAnchoredAt,
    anchorReceiptId: lookup.binding.anchorReceiptId,
    segmentId: lookup.binding.segmentId,
    previousAnchorReceiptHash: lookup.binding.previousAnchorReceiptHash,
    externalCallPhase: null,
    preCallClaimMode: null,
    preCallLookupStatus: null,
    leaseFencingToken: null,
    leaseExpiresAt: null,
    nextAvailableAt: null,
  });
  advance('receipt_input_claim', signedAt, {
    leaseFencingToken: 'found-input-token',
    leaseExpiresAt: leaseExpiry,
  });
  advance('receipt_input_persist', signedAt, {
    state: 'receipt_inputs_persisted',
    receiptSigningKeyRef: receipt.signingKeyRef,
    receiptSignedAt: signedAt,
    receiptPreimageHash: receipt.hashes.receiptPreimageHash,
    receiptBytesHash: receipt.hashes.receiptBytesHash,
    leaseFencingToken: null,
    leaseExpiresAt: null,
  });
  advance('receipt_persist_claim', signedAt, {
    leaseFencingToken: 'found-persist-token',
    leaseExpiresAt: leaseExpiry,
  });
  advance('receipt_persist', signedAt, {
    state: 'receipt_persisted',
    anchorReceiptHash: receipt.hashes.publicReceiptHash,
    leaseFencingToken: null,
    leaseExpiresAt: null,
  });
  advance('acknowledgement_claim', signedAt, {
    leaseFencingToken: 'found-ack-token',
    leaseExpiresAt: leaseExpiry,
  });
  advance('acknowledge', signedAt, {
    state: 'acknowledged',
    acknowledgedAt: signedAt,
    leaseFencingToken: null,
    leaseExpiresAt: null,
  });

  const { hashes, ...receiptBodyValue } = receipt;
  return {
    ...lookup,
    receiptBody: receiptBodyValue,
    receiptHashes: hashes,
    transitions: transitions.map(({ intent, ...transition }) => transition),
  };
}

function fixtureValue() {
  const body = receiptBody();
  const preimage = receiptDigest('blackops.audit.anchor.intent.receipt-preimage.v1', body);
  const bytes = receiptDigest('blackops.audit.anchor.intent.receipt-bytes.v1', body);
  const publicHash = receiptDigest('blackops.audit.anchor.hash.v1', body);
  const foundBody = receiptBody({
    anchorObjectVersion: 8,
    previousAnchorReceiptHash: 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc',
  });
  const foundPreimage = receiptDigest('blackops.audit.anchor.intent.receipt-preimage.v1', foundBody);
  const foundBytes = receiptDigest('blackops.audit.anchor.intent.receipt-bytes.v1', foundBody);
  const foundPublicHash = receiptDigest('blackops.audit.anchor.hash.v1', foundBody);
  const trace = buildTrace();
  const delayVectors = Object.fromEntries(
    retryDelays.map((delay) => [String(delay), buildDelayBranches(delay)]),
  );
  const attemptSequences = Object.fromEntries(
    [1, 31, 32].map((attempt) => [String(attempt), buildAttemptTrace(300, attempt)]),
  );
  const completeSequence = buildCompleteTrace({
    ...body,
    hashes: {
      receiptPreimageHash: preimage.hash,
      receiptBytesHash: bytes.hash,
      publicReceiptHash: publicHash.hash,
    },
  });
  const foundSequence = buildFoundCompleteTrace({
    ...foundBody,
    hashes: {
      receiptPreimageHash: foundPreimage.hash,
      receiptPreimageBytesHex: foundPreimage.preimage.toString('hex'),
      receiptPreimageBodyBytesHex: foundPreimage.bytes.toString('hex'),
      receiptBytesHash: foundBytes.hash,
      receiptBytesPreimageHex: foundBytes.preimage.toString('hex'),
      receiptBytesBodyBytesHex: foundBytes.bytes.toString('hex'),
      publicReceiptHash: foundPublicHash.hash,
      publicReceiptPreimageHex: foundPublicHash.preimage.toString('hex'),
    },
  });
  assert.equal(preimage.hash, expectedReceiptHashes.preimage);
  assert.equal(bytes.hash, expectedReceiptHashes.bytes);
  assert.equal(publicHash.hash, expectedReceiptHashes.public);
  assert.equal(foundPreimage.hash, expectedFoundReceiptHashes.preimage);
  assert.equal(foundBytes.hash, expectedFoundReceiptHashes.bytes);
  assert.equal(foundPublicHash.hash, expectedFoundReceiptHashes.public);
  assert.equal(trace.genesis.stateHash, expectedGenesisStateHash);
  assert.deepEqual(
    completeSequence.map((transition) => transition.successorStateHash),
    expectedCompleteStateHashes,
  );
  if (expectedFoundStateHashes.length > 0) {
    assert.deepEqual(
      foundSequence.transitions.map((transition) => transition.successorStateHash),
      expectedFoundStateHashes,
    );
  }
  const branchNames = Object.keys(trace.branches);
  const branchSchedules = Object.fromEntries(branchNames.map((name) => {
    const anchor = trace.branches[name].scheduleAnchor;
    return [name, Object.fromEntries(retryDelays.map((delay) => [String(delay), addSecondsMicros(anchor, delay)]))];
  }));
  assert.deepEqual(branchSchedules, expectedBranchSchedules);
  return {
    schemaVersion: 1,
    kind: 'audit_anchor_state_fixture',
    intent: trace.genesis,
    receiptBody: body,
    hashes: {
      receiptPreimageHash: preimage.hash,
      receiptPreimageBytesHex: preimage.preimage.toString('hex'),
      receiptPreimageBodyBytesHex: preimage.bytes.toString('hex'),
      receiptBytesHash: bytes.hash,
      receiptBytesPreimageHex: bytes.preimage.toString('hex'),
      receiptBytesBodyBytesHex: bytes.bytes.toString('hex'),
      publicReceiptHash: publicHash.hash,
      publicReceiptPreimageHex: publicHash.preimage.toString('hex'),
    },
    schedule: {
      transitionNow: trace.genesis.updatedAt,
      retryDelays,
      leaseExpiry: trace.leaseExpiry,
      branchNames,
      branchSchedules,
      dueBefore: '2030-01-02T03:09:05.123455Z',
      dueAt: '2030-01-02T03:09:05.123456Z',
      dueAfter: '2030-01-02T03:09:05.123457Z',
      calendarDay: '2030-01-03T03:04:05.123456Z',
      calendarYearOverflowInput: '9999-12-31T23:59:59.999999Z',
    },
    trace: {
      genesisStateHash: trace.genesis.stateHash,
      branches: trace.branches,
      completeSequence,
      foundLookup: {
        request: foundSequence.request,
        binding: foundSequence.binding,
        reconciliation: foundSequence.reconciliation,
      },
      foundReceiptBody: foundSequence.receiptBody,
      foundHashes: foundSequence.receiptHashes,
      foundSequence: foundSequence.transitions,
      delayVectors,
      attemptSequences,
    },
  };
}

function fixtureBytes() {
  return `${JSON.stringify(fixtureValue(), null, 2)}\n`;
}

function due(now, scheduled) {
  return parseTimestamp(now) >= parseTimestamp(scheduled);
}

const mode = process.argv[2];
if (mode === '--write') {
  writeFileSync(fixturePath, fixtureBytes(), 'utf8');
  process.stdout.write(`${fixturePath}\n`);
} else if (mode === '--check') {
  const actual = readFileSync(fixturePath, 'utf8');
  assert.equal(actual, fixtureBytes(), 'fixture bytes changed');
  process.stdout.write(`${createHash('sha256').update(actual).digest('hex')}\n`);
} else {
  test('state_lp_domain_and_complete_member_binding', () => {
    const intent = intentFixture();
    assert.equal(Object.keys(intent).length, 38);
    assert.equal(intent.stateHash, stateHash(intent));
    assert.equal(intent.stateHash, expectedGenesisStateHash);
    assert.notEqual(intent.stateHash, stateHash({ ...intent, attempt: 1 }));
    assert.notEqual(intent.stateHash, stateHash({ ...intent, state: 'externally_accepted' }));
  });

  test('idempotency_lp_domain_and_utf8_lengths', () => {
    const hash = idempotencyHash('intent-001', '鍵');
    assert.equal(hash.length, 64);
    assert.notEqual(hash, idempotencyHash('intent-001', '鍵 '));
    assert.equal(Buffer.byteLength('鍵', 'utf8'), 3);
    assert.notDeepEqual(lp('鍵'), lp('鍵 '));
    const nfc256 = `${'a'.repeat(254)}é`;
    const nfc257 = `${'a'.repeat(255)}é`;
    assert.equal(Buffer.byteLength(nfc256, 'utf8'), 256);
    assert.equal(Buffer.byteLength(nfc257, 'utf8'), 257);
    assert.notEqual(idempotencyHash('intent-001', nfc256), idempotencyHash('intent-001', nfc257));
    assert.notDeepEqual(Buffer.from('é', 'utf8'), Buffer.from('e\u0301', 'utf8'));
  });

  test('receipt_preimage_typed_encoding', () => {
    const body = receiptBody();
    const fixture = fixtureValue();
    const bytes = Buffer.from(canonical(body), 'utf8');
    const preimage = typed('blackops.audit.anchor.intent.receipt-preimage.v1', bytes);
    assert.equal(preimage.toString('hex'), fixture.hashes.receiptPreimageBytesHex);
    assert.equal(sha256(preimage), fixture.hashes.receiptPreimageHash);
    assert.equal(sha256(preimage), expectedReceiptHashes.preimage);
    assert.equal(preimage.toString('hex'), fixture.hashes.receiptPreimageBytesHex);
    assert.notEqual(sha256(Buffer.concat([lp('blackops.audit.anchor.intent.receipt-preimage.v1'), lp(bytes.toString('utf8'))])), fixture.hashes.receiptPreimageHash);
    assert.notEqual(sha256(typed('blackops.audit.anchor.intent.receipt-preimage.v2', bytes)), fixture.hashes.receiptPreimageHash);
    assert.notEqual(sha256(typed('blackops.audit.anchor.intent.receipt-preimage.v1', Buffer.from(canonical({ ...body, signingKeyRef: 'other' }), 'utf8'))), fixture.hashes.receiptPreimageHash);
    assert.notEqual(sha256(typed('blackops.audit.anchor.intent.receipt-preimage.v1', Buffer.from(canonical({ ...body, anchorObjectVersion: '1' }), 'utf8'))), fixture.hashes.receiptPreimageHash);
  });

  test('receipt_bytes_typed_encoding', () => {
    const body = receiptBody();
    const fixture = fixtureValue();
    const bytes = Buffer.from(canonical(body), 'utf8');
    const preimage = typed('blackops.audit.anchor.intent.receipt-bytes.v1', bytes);
    assert.equal(preimage.toString('hex'), fixture.hashes.receiptBytesPreimageHex);
    assert.equal(sha256(preimage), fixture.hashes.receiptBytesHash);
    assert.equal(sha256(preimage), expectedReceiptHashes.bytes);
    assert.notEqual(sha256(Buffer.concat([lp('blackops.audit.anchor.intent.receipt-bytes.v1'), lp(bytes.toString('utf8'))])), fixture.hashes.receiptBytesHash);
    assert.notEqual(sha256(typed('blackops.audit.anchor.intent.receipt-bytes.v2', bytes)), fixture.hashes.receiptBytesHash);
    assert.notEqual(sha256(typed('blackops.audit.anchor.intent.receipt-bytes.v1', Buffer.from(canonical({ ...body, checkpointId: 'other' }), 'utf8'))), fixture.hashes.receiptBytesHash);
    assert.notEqual(fixture.hashes.receiptPreimageHash, fixture.hashes.receiptBytesHash);
  });

  test('public_receipt_hash_domain', () => {
    const body = receiptBody();
    const fixture = fixtureValue();
    const foundBody = fixture.trace.foundReceiptBody;
    assert.equal(publicReceiptHash(body), fixture.hashes.publicReceiptHash);
    assert.equal(publicReceiptHash(body), expectedReceiptHashes.public);
    assert.equal(receiptDigest('blackops.audit.anchor.hash.v1', body).preimage.toString('hex'), fixture.hashes.publicReceiptPreimageHex);
    assert.notEqual(publicReceiptHash(body), typedDigest('blackops.audit.anchor.intent.receipt-bytes.v1', Buffer.from(canonical(body), 'utf8')));
    assert.notEqual(fixture.hashes.publicReceiptHash, fixture.hashes.receiptBytesHash);
    assert.equal(receiptDigest('blackops.audit.anchor.intent.receipt-preimage.v1', foundBody).hash, fixture.trace.foundHashes.receiptPreimageHash);
    assert.equal(receiptDigest('blackops.audit.anchor.intent.receipt-preimage.v1', foundBody).preimage.toString('hex'), fixture.trace.foundHashes.receiptPreimageBytesHex);
    assert.equal(receiptDigest('blackops.audit.anchor.intent.receipt-preimage.v1', foundBody).bytes.toString('hex'), fixture.trace.foundHashes.receiptPreimageBodyBytesHex);
    assert.equal(receiptDigest('blackops.audit.anchor.intent.receipt-bytes.v1', foundBody).hash, fixture.trace.foundHashes.receiptBytesHash);
    assert.equal(receiptDigest('blackops.audit.anchor.intent.receipt-bytes.v1', foundBody).preimage.toString('hex'), fixture.trace.foundHashes.receiptBytesPreimageHex);
    assert.equal(receiptDigest('blackops.audit.anchor.intent.receipt-bytes.v1', foundBody).bytes.toString('hex'), fixture.trace.foundHashes.receiptBytesBodyBytesHex);
    assert.equal(publicReceiptHash(foundBody), fixture.trace.foundHashes.publicReceiptHash);
    assert.equal(receiptDigest('blackops.audit.anchor.hash.v1', foundBody).preimage.toString('hex'), fixture.trace.foundHashes.publicReceiptPreimageHex);
    assert.notEqual(fixture.trace.foundHashes.publicReceiptHash, fixture.hashes.publicReceiptHash);
  });

  test('six_retry_schedules_preserve_microseconds', () => {
    const fixture = fixtureValue();
    assert.deepEqual(fixture.schedule.retryDelays, retryDelays);
    assert.equal(fixture.schedule.branchNames.length, 7);
    for (const branch of fixture.schedule.branchNames) {
      const entry = fixture.trace.branches[branch];
      assert.deepEqual(fixture.schedule.branchSchedules[branch], expectedBranchSchedules[branch]);
      assert.deepEqual(
        entry.transitions.map((transition) => transition.successorStateHash),
        expectedBranchStateHashes[branch],
      );
      for (const delay of retryDelays) {
        assert.equal(fixture.schedule.branchSchedules[branch][String(delay)], addSecondsMicros(entry.scheduleAnchor, delay));
      }
      assert.equal(entry.scheduledAt, fixture.schedule.branchSchedules[branch]['300']);
      assert.ok(entry.transitions.length >= 2);
      for (const transition of entry.transitions) {
        assert.equal(transition.toVersion, transition.fromVersion + 1);
        assert.equal(transition.previousStateHash.length, 64);
        const successor = JSON.parse(transition.successorBytes);
        assert.equal(successor.stateHash, transition.successorStateHash);
        assert.equal(stateHash(successor), transition.successorStateHash);
        assert.equal(successor.previousStateHash, transition.previousStateHash);
        assert.equal(successor.updatedAt, transition.updatedAt);
      }
    }
    assert.ok(fixture.schedule.branchNames.includes('pre_call_pending_reclaim'));
    assert.ok(fixture.schedule.branchNames.includes('pre_call_absent_reclaim'));
    assert.ok(fixture.schedule.branchNames.includes('post_call_reclaim'));
    assert.ok(fixture.schedule.branchNames.includes('post_call_absent_below32'));
    assert.ok(fixture.trace.branches.post_call_absent_below32.transitions.some(
      (transition) => transition.operation === 'external_reconciliation_claim',
    ));
    for (const delay of retryDelays) {
      const branches = fixture.trace.delayVectors[String(delay)];
      assert.equal(Object.keys(branches).length, 7);
      for (const branch of Object.values(branches)) {
        assert.ok(branch.transitions.length >= 2);
        for (const transition of branch.transitions) {
          const successor = JSON.parse(transition.successorBytes);
          assert.equal(transition.toVersion, transition.fromVersion + 1);
          assert.equal(successor.previousStateHash, transition.previousStateHash);
          assert.equal(successor.stateHash, transition.successorStateHash);
          assert.equal(stateHash(successor), transition.successorStateHash);
        }
      }
    }
    for (const attempt of [1, 31, 32]) {
      const sequence = fixture.trace.attemptSequences[String(attempt)];
      assert.equal(sequence.at(-1).operation, 'result_unavailable');
      assert.equal(JSON.parse(sequence.at(-1).successorBytes).attempt, attempt);
    }
    const complete = fixture.trace.completeSequence;
    assert.deepEqual(
      complete.map((transition) => transition.operation),
      [
        'external_submission_claim',
        'lookup_absent',
        'call_start',
        'result_accepted',
        'receipt_input_claim',
        'receipt_input_persist',
        'receipt_persist_claim',
        'receipt_persist',
        'acknowledgement_claim',
        'acknowledge',
      ],
    );
    assert.deepEqual(
      complete.map((transition) => transition.toVersion),
      [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
    );
    assert.deepEqual(
      complete.map((transition) => transition.successorStateHash),
      expectedCompleteStateHashes,
    );
    for (const transition of complete) {
      const successor = JSON.parse(transition.successorBytes);
      assert.equal(transition.toVersion, transition.fromVersion + 1);
      assert.equal(successor.previousStateHash, transition.previousStateHash);
      assert.equal(successor.stateHash, transition.successorStateHash);
      assert.equal(stateHash(successor), transition.successorStateHash);
    }
    const found = fixture.trace.foundSequence;
    assert.deepEqual(
      found.map((transition) => transition.operation),
      [
        'external_submission_claim',
        'lookup_found',
        'receipt_input_claim',
        'receipt_input_persist',
        'receipt_persist_claim',
        'receipt_persist',
        'acknowledgement_claim',
        'acknowledge',
      ],
    );
    if (expectedFoundStateHashes.length > 0) {
      assert.deepEqual(
        found.map((transition) => transition.successorStateHash),
        expectedFoundStateHashes,
      );
    }
    for (const transition of found) {
      const successor = JSON.parse(transition.successorBytes);
      assert.equal(transition.toVersion, transition.fromVersion + 1);
      assert.equal(successor.previousStateHash, transition.previousStateHash);
      assert.equal(successor.stateHash, transition.successorStateHash);
      assert.equal(stateHash(successor), transition.successorStateHash);
      assert.equal(successor.version, transition.toVersion);
    }
    assert.deepEqual(fixture.trace.foundLookup.request, foundLookup().request);
    assert.deepEqual(fixture.trace.foundLookup.binding, foundLookup().binding);
    assert.deepEqual(fixture.trace.foundLookup.reconciliation, foundLookup().reconciliation);
    assert.equal(fixture.trace.foundLookup.request.expectedPredecessorVersion, 7);
    assert.equal(fixture.trace.foundLookup.reconciliation.foundObject.anchorObjectVersion, 8);
    assert.notEqual(
      fixture.trace.foundLookup.request.expectedPredecessorHash,
      fixture.trace.foundLookup.binding.previousAnchorReceiptHash,
    );
    assert.notEqual(
      fixture.trace.foundLookup.reconciliation.foundObject.acceptedObjectHash,
      fixture.trace.foundLookup.binding.previousAnchorReceiptHash,
    );
  });

  test('due_expiry_and_calendar_boundaries', () => {
    const fixture = fixtureValue();
    assert.equal(due(fixture.schedule.dueBefore, fixture.schedule.dueAt), false);
    assert.equal(due(fixture.schedule.dueAt, fixture.schedule.dueAt), true);
    assert.equal(due(fixture.schedule.dueAfter, fixture.schedule.dueAt), true);
    assert.equal(fixture.schedule.leaseExpiry, addSecondsMicros(fixture.schedule.transitionNow, 300));
    assert.equal(addSecondsMicros(fixture.schedule.transitionNow, 86_400), fixture.schedule.calendarDay);
    assert.throws(() => parseTimestamp('2030-02-30T03:04:05.123456Z'));
    assert.throws(() => addSecondsMicros(fixture.schedule.calendarYearOverflowInput, 1));
    assert.equal(maxSafeInteger, 9_007_199_254_740_991n);
    assert.ok(maxSafeInteger > 32n);
    assert.equal(parseTimestamp(fixture.schedule.dueAt) - parseTimestamp(fixture.schedule.dueBefore), 1n);
    assert.equal(parseTimestamp(fixture.schedule.dueAfter) - parseTimestamp(fixture.schedule.dueAt), 1n);
  });

  test('fixture_bytes_are_exact_and_reproducible', () => {
    const bytes = fixtureBytes();
    assert.equal(bytes, `${JSON.stringify(JSON.parse(bytes), null, 2)}\n`);
    assert.equal(JSON.parse(bytes).intent.stateHash, intentFixture().stateHash);
    assert.equal(JSON.parse(bytes).trace.genesisStateHash, intentFixture().stateHash);
  });
}
