import { test } from 'node:test';
import { strict as assert } from 'node:assert';
import {
  createHash,
  createPrivateKey,
  createPublicKey,
  sign,
  verify,
} from 'node:crypto';
import { readFileSync } from 'node:fs';

const fixture = JSON.parse(readFileSync(new URL('./audit-signed-artifacts-v1.json', import.meta.url), 'utf8'));
const bytes = value => Buffer.from(value, 'utf8');
const utf16Compare = (left, right) => {
  for (let index = 0; index < Math.min(left.length, right.length); index += 1) {
    const leftCode = left.charCodeAt(index);
    const rightCode = right.charCodeAt(index);
    if (leftCode !== rightCode) return leftCode - rightCode;
  }
  return left.length - right.length;
};
const jcs = value => {
  if (Array.isArray(value)) return `[${value.map(jcs).join(',')}]`;
  if (value !== null && typeof value === 'object') {
    return `{${Object.keys(value).sort(utf16Compare).map(key => `${JSON.stringify(key)}:${jcs(value[key])}`).join(',')}}`;
  }
  if (typeof value === 'number' && !Number.isSafeInteger(value)) throw new Error('unsafe number');
  if (value === undefined) throw new Error('undefined is not a JCS value');
  return JSON.stringify(value);
};
const stringEncoding = value => bytes(`s:${Buffer.byteLength(value, 'utf8')}:${value}`);
const bytesEncoding = value => {
  const raw = Buffer.isBuffer(value) ? value : bytes(value);
  return Buffer.concat([bytes(`b:${raw.length}:`), raw]);
};
const frame = (domain, payload) => Buffer.concat([stringEncoding(domain), bytesEncoding(payload)]);
const u32Encoding = value => {
  const raw = Buffer.isBuffer(value) ? value : bytes(value);
  const length = Buffer.alloc(4);
  length.writeUInt32BE(raw.length);
  return Buffer.concat([length, raw]);
};
const u32Frame = (domain, payload) => Buffer.concat([u32Encoding(domain), u32Encoding(payload)]);
const digest = value => createHash('sha256').update(value).digest('hex');
const hash = (domain, payload) => digest(frame(domain, Buffer.isBuffer(payload) ? payload : bytes(payload)));
const preimage = (domain, digestHex) => frame(domain, Buffer.from(digestHex, 'hex'));
const privateDer = seed => Buffer.concat([Buffer.from('302e020100300506032b657004220420', 'hex'), seed]);
const publicDer = publicKey => Buffer.concat([Buffer.from('302a300506032b6570032100', 'hex'), publicKey]);
const publicKey = seed => createPublicKey(
  createPrivateKey({ key: privateDer(seed), format: 'der', type: 'pkcs8' }),
).export({ format: 'der', type: 'spki' }).subarray(-32);
const signature = (domain, digestHex, seed) => sign(null, preimage(domain, digestHex), {
  key: privateDer(seed),
  format: 'der',
  type: 'pkcs8',
});
const encoded = value => value.toString('base64url');
const decoded = value => Buffer.from(value, 'base64url');
const assertCanonicalBase64 = value => assert.equal(encoded(decoded(value)), value);
const checkpointSeed = Buffer.from(fixture.seeds.checkpoint, 'hex');
const anchorSeed = Buffer.from(fixture.seeds.anchor, 'hex');
const exportSeed = Buffer.from(fixture.seeds.export, 'hex');

assert.equal(fixture.schemaVersion, 1);
assert.equal(fixture.kind, 'audit_signed_artifacts_fixture');

test('checkpoint literals', () => {
  const checkpointKey = createPublicKey({ key: publicDer(publicKey(checkpointSeed)), format: 'der', type: 'spki' });
  for (const [name, row] of Object.entries(fixture.checkpoints)) {
    const unsignedBytes = jcs(row.unsigned);
    const checkpointHash = hash('blackops.audit.checkpoint.hash.v1', unsignedBytes);
    const signaturePreimage = preimage('blackops.audit.checkpoint.signature.v1', checkpointHash);
    const expectedSignature = signature('blackops.audit.checkpoint.signature.v1', checkpointHash, checkpointSeed);
    const complete = { ...row.unsigned, checkpointHash, signature: encoded(expectedSignature) };
    const completeBytes = jcs(complete);
    assert.equal(row.unsignedBytes, unsignedBytes, `${name} unsigned bytes`);
    assert.equal(row.checkpointHash, checkpointHash, `${name} hash`);
    assert.equal(row.signaturePreimageHex, signaturePreimage.toString('hex'), `${name} preimage`);
    assert.equal(row.signature, encoded(expectedSignature), `${name} signature`);
    assert.equal(row.completeBytes, completeBytes, `${name} complete bytes`);
    assert.deepEqual(row.complete, complete, `${name} complete value`);
    assert.equal(row.completeBytesHash, hash('blackops.audit.anchor.checkpoint-bytes.v1', completeBytes), `${name} bytes hash`);
    assert.equal(verify(null, signaturePreimage, checkpointKey, decoded(row.signature)), true, `${name} verify`);
    assertCanonicalBase64(row.signature);
    assert.equal(row.completeBytes.endsWith('\n'), false, `${name} no LF`);
    assert.equal(Buffer.byteLength(row.completeBytes, 'utf8') <= 1_048_575, true, `${name} byte cap`);
  }
});

test('receipt literals', () => {
  const anchorKey = createPublicKey({ key: publicDer(publicKey(anchorSeed)), format: 'der', type: 'spki' });
  const checkpointKey = createPublicKey({ key: publicDer(publicKey(checkpointSeed)), format: 'der', type: 'spki' });
  for (const [name, row] of Object.entries(fixture.receipts)) {
    const checkpoint = fixture.checkpoints[row.checkpoint];
    assert.equal(row.unsigned.partitionId, checkpoint.unsigned.partitionId, `${name} partition binding`);
    assert.equal(row.unsigned.segmentId, checkpoint.unsigned.segmentId, `${name} segment binding`);
    assert.equal(row.unsigned.checkpointId, checkpoint.unsigned.checkpointId, `${name} checkpoint binding`);
    assert.equal(row.unsigned.checkpointHash, checkpoint.checkpointHash, `${name} checkpoint hash binding`);
    assert.equal(row.unsigned.checkpointBytesHash, checkpoint.completeBytesHash, `${name} bytes hash binding`);
    const unsignedBytes = jcs(row.unsigned);
    const anchorReceiptHash = hash('blackops.audit.anchor.hash.v1', unsignedBytes);
    const signaturePreimage = preimage('blackops.audit.anchor.signature.v1', anchorReceiptHash);
    const expectedSignature = signature('blackops.audit.anchor.signature.v1', anchorReceiptHash, anchorSeed);
    const complete = { ...row.unsigned, anchorReceiptHash, signature: encoded(expectedSignature) };
    assert.equal(row.unsignedBytes, unsignedBytes, `${name} unsigned bytes`);
    assert.equal(row.anchorReceiptHash, anchorReceiptHash, `${name} hash`);
    assert.equal(row.signaturePreimageHex, signaturePreimage.toString('hex'), `${name} preimage`);
    assert.equal(row.signature, encoded(expectedSignature), `${name} signature`);
    assert.equal(row.completeBytes, jcs(complete), `${name} complete bytes`);
    assert.deepEqual(row.complete, complete, `${name} complete value`);
    assert.equal(checkpoint.complete.signedAt <= row.unsigned.anchoredAt, true, `${name} checkpoint chronology`);
    assert.equal(row.unsigned.anchoredAt <= row.unsigned.signedAt, true, `${name} receipt chronology`);
    assert.equal(row.completeBytes.endsWith('\n'), false, `${name} no LF`);
    assert.equal(verify(null, signaturePreimage, anchorKey, decoded(row.signature)), true, `${name} verify`);
    assert.equal(verify(null, signaturePreimage, checkpointKey, decoded(row.signature)), false, `${name} role separation`);
    assertCanonicalBase64(row.signature);
    assert.equal(Buffer.byteLength(row.completeBytes, 'utf8') <= 1_048_575, true, `${name} byte cap`);
  }
  const first = fixture.receipts.receiptV1.unsigned;
  const second = fixture.receipts.receiptV2.unsigned;
  assert.equal(first.anchorObjectVersion, 1);
  assert.equal(first.previousAnchorReceiptHash, null);
  assert.equal(second.anchorObjectVersion, 2);
  assert.equal(second.previousAnchorReceiptHash, fixture.receipts.receiptV1.anchorReceiptHash);
  assert.equal(first.partitionId, second.partitionId);
  assert.equal(first.anchorStoreRef, second.anchorStoreRef);
  assert.equal(first.anchorObjectRef, second.anchorObjectRef);
  assert.equal(first.signedAt < second.anchoredAt, true);
});

test('trust hash and material', () => {
  const snapshot = fixture.trust.snapshot;
  const body = { ...snapshot };
  delete body.hash;
  assert.equal(snapshot.hash, hash('blackops.audit.trust-store-snapshot.v1', jcs(body)));
  const seenRefs = new Set();
  const seenFingerprints = new Set();
  for (const entry of snapshot.entries) {
    assert.equal(seenRefs.has(entry.keyRef), false);
    assert.equal(seenFingerprints.has(entry.publicKeyFingerprint), false);
    seenRefs.add(entry.keyRef);
    seenFingerprints.add(entry.publicKeyFingerprint);
    assertCanonicalBase64(entry.publicKey);
    const material = decoded(entry.publicKey);
    assert.equal(material.length, 32);
    assert.equal(entry.publicKeyFingerprint, digest(material));
    const seed = entry.role === 'checkpoint' ? checkpointSeed : entry.role === 'anchor' ? anchorSeed : exportSeed;
    assert.deepEqual(material, publicKey(seed), `${entry.role} public material`);
  }
  assert.deepEqual(
    snapshot.entries.map(entry => entry.keyRef),
    [...snapshot.entries].map(entry => entry.keyRef).sort((left, right) => Buffer.from(left).compare(Buffer.from(right))),
  );
});

test('system and tenant record-derived vectors', () => {
  const lineage = fixture.recordLineage;
  assert.equal(lineage.source.sha256, '2601ed2cc7f2ae099f4afc990af617f6a5e55168756fc566b263a5a76f7d5127');
  assert.deepEqual(lineage.source.records, ['systemGenesis', 'tenantGenesis', 'tenantNonGenesis']);
  assert.equal(lineage.rows.length, 51);
  const partitionFor = tenant => {
    if (tenant === null) return 'system:global';
    return `tenant:${hash('blackops.audit.partition.tenant.v1', jcs(tenant))}`;
  };
  const contentHash = logical => hash('blackops.audit.content.v1', jcs(logical));
  const recordHash = (chain, content) => digest(Buffer.concat([
    stringEncoding('blackops.audit.record.v1'),
    stringEncoding(chain.partitionId),
    stringEncoding(chain.segmentId),
    bytes(`i:${chain.sequence}`),
    bytesEncoding(chain.previousRecordHash === null ? Buffer.alloc(32) : Buffer.from(chain.previousRecordHash, 'hex')),
    bytesEncoding(Buffer.from(content, 'hex')),
    stringEncoding('audit-sha256-jcs-v1'),
  ]));
  const completeBytes = (logical, chain, content, record) => `${jcs({
    ...logical,
    integrity: {
      hashSuite: 'audit-sha256-jcs-v1',
      partitionId: chain.partitionId,
      segmentId: chain.segmentId,
      sequence: chain.sequence,
      previousRecordHash: chain.previousRecordHash,
      contentHash: content,
      recordHash: record,
    },
  })}\n`;
  const tenantRows = [];
  for (const row of lineage.rows) {
    const expectedPartition = partitionFor(row.logical.tenant);
    assert.equal(row.chain.partitionId, expectedPartition, `${row.rowId} partition`);
    const expectedContent = contentHash(row.logical);
    const expectedRecord = recordHash(row.chain, expectedContent);
    assert.equal(row.logicalBytes, jcs(row.logical), `${row.rowId} logical bytes`);
    assert.equal(row.contentHash, expectedContent, `${row.rowId} content hash`);
    assert.equal(row.recordHash, expectedRecord, `${row.rowId} record hash`);
    assert.equal(row.completeBytes, completeBytes(row.logical, row.chain, expectedContent, expectedRecord), `${row.rowId} complete bytes`);
    if (row.scope === 'tenant') tenantRows.push(row);
  }
  assert.equal(tenantRows.length, 50);
  for (let index = 0; index < tenantRows.length; index += 1) {
    const row = tenantRows[index];
    assert.equal(row.chain.sequence, index + 1);
    assert.equal(row.chain.previousRecordHash, index === 0 ? null : tenantRows[index - 1].recordHash);
  }
  assert.equal(tenantRows[41].chain.sequence, 42);
  assert.equal(tenantRows[42].chain.sequence, 43);
  assert.notEqual(tenantRows[41].chain.segmentId, tenantRows[42].chain.segmentId);
  assert.equal(fixture.recordEndpoints.systemGenesis, lineage.rows[0].recordHash);
  assert.equal(fixture.recordEndpoints.tenantSequence1, tenantRows[0].recordHash);
  assert.equal(fixture.recordEndpoints.tenantSequence42, tenantRows[41].recordHash);
  assert.equal(fixture.recordEndpoints.tenantSequence43, tenantRows[42].recordHash);
  assert.equal(fixture.recordEndpoints.tenantSequence50, tenantRows[49].recordHash);
  const first = fixture.checkpoints.tenantRange1to42.unsigned;
  const second = fixture.checkpoints.tenantRange43to50.unsigned;
  assert.deepEqual(
    [first.firstRecordHash, first.chainHeadHash, second.firstRecordHash, second.chainHeadHash],
    [tenantRows[0].recordHash, tenantRows[41].recordHash, tenantRows[42].recordHash, tenantRows[49].recordHash],
  );
  assert.equal(second.previousCheckpointHash, fixture.checkpoints.tenantRange1to42.checkpointHash);
});

test('signature domain and material substitutions', () => {
  const checkpointKey = createPublicKey({ key: publicDer(publicKey(checkpointSeed)), format: 'der', type: 'spki' });
  const anchorKey = createPublicKey({ key: publicDer(publicKey(anchorSeed)), format: 'der', type: 'spki' });
  for (const row of Object.values(fixture.checkpoints)) {
    const digestBytes = Buffer.from(row.checkpointHash, 'hex');
    const good = decoded(row.signature);
    const input = preimage('blackops.audit.checkpoint.signature.v1', row.checkpointHash);
    assert.equal(verify(null, input, checkpointKey, good), true);
    assert.equal(verify(null, frame('blackops.audit.anchor.signature.v1', digestBytes), checkpointKey, good), false);
    const changed = Buffer.from(digestBytes);
    changed[0] ^= 1;
    assert.equal(verify(null, frame('blackops.audit.checkpoint.signature.v1', changed), checkpointKey, good), false);
    assert.equal(
      verify(null, frame('blackops.audit.checkpoint.signature.v1', bytes(row.checkpointHash)), checkpointKey, good),
      false,
    );
    const completeHash = hash('blackops.audit.anchor.checkpoint-bytes.v1', row.completeBytes);
    assert.equal(row.completeBytesHash, completeHash);
    assert.notEqual(completeHash, row.checkpointHash);
    assert.equal(
      verify(null, preimage('blackops.audit.checkpoint.signature.v1', completeHash), checkpointKey, good),
      false,
    );
    const wrongFormulaHash = hash('blackops.audit.checkpoint.hash.v1', row.completeBytes);
    assert.notEqual(wrongFormulaHash, row.checkpointHash);
    assert.equal(
      verify(null, preimage('blackops.audit.checkpoint.signature.v1', wrongFormulaHash), checkpointKey, good),
      false,
    );
    const wrongArtifactDomainHash = hash('blackops.audit.anchor.hash.v1', row.unsignedBytes);
    assert.notEqual(wrongArtifactDomainHash, row.checkpointHash);
    assert.equal(
      verify(null, preimage('blackops.audit.checkpoint.signature.v1', wrongArtifactDomainHash), checkpointKey, good),
      false,
    );
    const withSignatureField = { ...row.unsigned, signature: row.signature };
    assert.notEqual(
      hash('blackops.audit.checkpoint.hash.v1', jcs(withSignatureField)),
      row.checkpointHash,
    );
    const alteredUnsigned = { ...row.unsigned, checkpointId: `${row.unsigned.checkpointId}-altered` };
    const alteredHash = hash('blackops.audit.checkpoint.hash.v1', jcs(alteredUnsigned));
    assert.notEqual(alteredHash, row.checkpointHash);
    assert.equal(
      verify(null, preimage('blackops.audit.checkpoint.signature.v1', alteredHash), checkpointKey, good),
      false,
    );
    assert.equal(verify(null, input, checkpointKey, good.subarray(0, 63)), false);
    assert.equal(verify(null, input, anchorKey, good), false);
  }
  for (const row of Object.values(fixture.receipts)) {
    const digestBytes = Buffer.from(row.anchorReceiptHash, 'hex');
    const good = decoded(row.signature);
    const input = preimage('blackops.audit.anchor.signature.v1', row.anchorReceiptHash);
    assert.equal(verify(null, input, anchorKey, good), true);
    assert.equal(verify(null, frame('blackops.audit.checkpoint.signature.v1', digestBytes), anchorKey, good), false);
    assert.equal(
      verify(null, frame('blackops.audit.anchor.signature.v1', bytes(row.anchorReceiptHash)), anchorKey, good),
      false,
    );
    const wrongFormulaHash = hash('blackops.audit.anchor.hash.v1', row.completeBytes);
    assert.notEqual(wrongFormulaHash, row.anchorReceiptHash);
    assert.equal(
      verify(null, preimage('blackops.audit.anchor.signature.v1', wrongFormulaHash), anchorKey, good),
      false,
    );
    const wrongArtifactDomainHash = hash('blackops.audit.checkpoint.hash.v1', row.unsignedBytes);
    assert.notEqual(wrongArtifactDomainHash, row.anchorReceiptHash);
    assert.equal(
      verify(null, preimage('blackops.audit.anchor.signature.v1', wrongArtifactDomainHash), anchorKey, good),
      false,
    );
    const withSignatureField = { ...row.unsigned, signature: row.signature };
    assert.notEqual(
      hash('blackops.audit.anchor.hash.v1', jcs(withSignatureField)),
      row.anchorReceiptHash,
    );
    const withHashField = { ...row.unsigned, anchorReceiptHash: row.anchorReceiptHash };
    assert.notEqual(
      hash('blackops.audit.anchor.hash.v1', jcs(withHashField)),
      row.anchorReceiptHash,
    );
    const withoutSignedAt = { ...row.unsigned };
    delete withoutSignedAt.signedAt;
    assert.notEqual(
      hash('blackops.audit.anchor.hash.v1', jcs(withoutSignedAt)),
      row.anchorReceiptHash,
    );
    const alteredUnsigned = { ...row.unsigned, anchorObjectRef: `${row.unsigned.anchorObjectRef}-altered` };
    const alteredHash = hash('blackops.audit.anchor.hash.v1', jcs(alteredUnsigned));
    assert.notEqual(alteredHash, row.anchorReceiptHash);
    assert.equal(
      verify(null, preimage('blackops.audit.anchor.signature.v1', alteredHash), anchorKey, good),
      false,
    );
    assert.equal(verify(null, input, anchorKey, good.subarray(0, 63)), false);
    assert.equal(verify(null, input, checkpointKey, good), false);
  }
});

test('encoding and digest boundaries', () => {
  const domain = 'blackops.audit.checkpoint.hash.v1';
  const payload = bytes('{}');
  const framed = frame(domain, payload);
  const expectedFrame = `s:${Buffer.byteLength(domain, 'utf8')}:${domain}b:2:{}`;
  assert.equal(framed.toString('utf8'), expectedFrame);
  assert.equal(framed.subarray(0, Buffer.byteLength(`s:${domain.length}:${domain}`)).toString('utf8'), `s:${domain.length}:${domain}`);
  assert.equal(framed.subarray(-2).toString('utf8'), '{}');
  assert.equal(hash(domain, payload), createHash('sha256').update(framed).digest('hex'));
  assert.equal(hash(domain, payload), digest(framed));
  assert.notEqual(hash(domain, bytes(payload.toString('hex'))), hash(domain, payload));
  const typedPayload = 'typed-string-payload';
  assert.notDeepEqual(stringEncoding(typedPayload), bytesEncoding(bytes(typedPayload)));
  const typedFrame = Buffer.concat([stringEncoding(domain), stringEncoding(typedPayload)]);
  assert.notDeepEqual(typedFrame, frame(domain, bytes(typedPayload)));
  assert.notEqual(
    digest(typedFrame),
    hash(domain, bytes(typedPayload)),
  );
  const u32Payload = u32Encoding(payload);
  const u32Framed = u32Frame(domain, payload);
  assert.notDeepEqual(u32Payload, bytesEncoding(payload));
  assert.notDeepEqual(u32Framed, framed);
  assert.notEqual(digest(u32Framed), hash(domain, payload));
  const checkpoint = fixture.checkpoints.systemOne;
  const checkpointCanonicalFrame = frame(
    'blackops.audit.checkpoint.hash.v1',
    bytes(checkpoint.unsignedBytes),
  );
  const checkpointTypedFrame = Buffer.concat([
    stringEncoding('blackops.audit.checkpoint.hash.v1'),
    stringEncoding(checkpoint.unsignedBytes),
  ]);
  assert.equal(digest(checkpointCanonicalFrame), checkpoint.checkpointHash);
  assert.notDeepEqual(checkpointTypedFrame, checkpointCanonicalFrame);
  assert.notEqual(digest(checkpointTypedFrame), checkpoint.checkpointHash);
  for (const row of Object.values(fixture.receipts)) {
    const canonicalFrame = frame('blackops.audit.anchor.hash.v1', bytes(row.unsignedBytes));
    const typedFrameForRow = Buffer.concat([
      stringEncoding('blackops.audit.anchor.hash.v1'),
      stringEncoding(row.unsignedBytes),
    ]);
    assert.equal(digest(canonicalFrame), row.anchorReceiptHash);
    assert.notDeepEqual(typedFrameForRow, canonicalFrame);
    assert.notEqual(digest(typedFrameForRow), row.anchorReceiptHash);
  }
  const rawDigest = Buffer.from(checkpoint.checkpointHash, 'hex');
  const hexDigest = bytes(checkpoint.checkpointHash);
  assert.equal(rawDigest.length, 32);
  assert.equal(hexDigest.length, 64);
  assert.notDeepEqual(
    preimage('blackops.audit.checkpoint.signature.v1', checkpoint.checkpointHash),
    frame('blackops.audit.checkpoint.signature.v1', hexDigest),
  );
  const checkpointKey = createPublicKey({ key: publicDer(publicKey(checkpointSeed)), format: 'der', type: 'spki' });
  assert.equal(
    verify(
      null,
      u32Frame('blackops.audit.checkpoint.signature.v1', rawDigest),
      checkpointKey,
      decoded(checkpoint.signature),
    ),
    false,
  );
  const withoutSignedAt = { ...checkpoint.unsigned };
  delete withoutSignedAt.signedAt;
  assert.notEqual(
    hash('blackops.audit.checkpoint.hash.v1', jcs(withoutSignedAt)),
    checkpoint.checkpointHash,
  );
  const withHashField = { ...checkpoint.unsigned, checkpointHash: checkpoint.checkpointHash };
  assert.notEqual(
    hash('blackops.audit.checkpoint.hash.v1', jcs(withHashField)),
    checkpoint.checkpointHash,
  );
  const unicodeFrame = stringEncoding('é');
  assert.equal(unicodeFrame.toString('utf8'), 's:2:é');
  assert.equal(Number.isSafeInteger(9_007_199_254_740_991), true);
  assert.equal(Number.isSafeInteger(9_007_199_254_740_992), false);
  assert.equal(Buffer.from(fixture.seeds.checkpoint, 'hex').length, 32);
  for (const row of Object.values(fixture.checkpoints)) {
    assert.match(row.checkpointHash, /^[0-9a-f]{64}$/);
    assert.equal(row.completeBytes.includes('\n'), false);
    assert.equal(Buffer.byteLength(row.completeBytes, 'utf8') <= 1_048_575, true);
    assertCanonicalBase64(row.signature);
    const padded = `${row.signature}=`;
    assert.equal(encoded(decoded(padded)), row.signature);
    assert.notEqual(padded, encoded(decoded(padded)));
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    const lastIndex = alphabet.indexOf(row.signature.at(-1));
    const alias = `${row.signature.slice(0, -1)}${alphabet[lastIndex + 1]}`;
    assert.deepEqual(decoded(alias), decoded(row.signature));
    assert.notEqual(alias, encoded(decoded(alias)));
  }
  for (const row of Object.values(fixture.receipts)) {
    assert.match(row.anchorReceiptHash, /^[0-9a-f]{64}$/);
    assertCanonicalBase64(row.signature);
    assert.equal(decoded(row.signature).length, 64);
    const padded = `${row.signature}=`;
    assert.equal(encoded(decoded(padded)), row.signature);
    assert.notEqual(padded, encoded(decoded(padded)));
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    const lastIndex = alphabet.indexOf(row.signature.at(-1));
    const alias = `${row.signature.slice(0, -1)}${alphabet[lastIndex + 1]}`;
    assert.deepEqual(decoded(alias), decoded(row.signature));
    assert.notEqual(alias, encoded(decoded(alias)));
  }
});
