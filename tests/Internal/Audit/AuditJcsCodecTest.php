<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit;

use BlackOps\Core\Audit\AuditValidationException;
use BlackOps\Internal\Audit\AuditJcsCodec;
use BlackOps\Internal\Audit\AuditJcsObject;
use PHPUnit\Framework\TestCase;

final class AuditJcsCodecTest extends TestCase
{
    public function testUsesUtf16MemberOrderAndExactEscapes(): void
    {
        $codec = new AuditJcsCodec();
        self::assertSame('{"😀":2,"":1}', $codec->canonicalJson(["\u{E000}" => 1, "\u{1F600}" => 2]));
        self::assertSame("{\"value\":\"/\\\"\\\\\u{2028}\u{2029}\"}", $codec->canonicalJson([
            'value' => "/\"\\\u{2028}\u{2029}",
        ]));
    }

    public function testDistinguishesEmptyObjectAndList(): void
    {
        $codec = new AuditJcsCodec();
        self::assertSame('{}', $codec->canonicalJson(new AuditJcsObject([])));
        self::assertSame('[]', $codec->canonicalJson([]));
    }

    public function testWholeTreeShapePrecedesStringValidation(): void
    {
        $codec = new AuditJcsCodec();
        foreach ([
            ['bad' => "value\xFF", 'nested' => ['number' => 1.5]],
            new AuditJcsObject([
                ['key' => "key\xFF", 'value' => new \stdClass()],
            ]),
        ] as $value) {
            try {
                $codec->canonicalJson($value);
                self::fail('unsupported JCS shape accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
    }

    public function testWholeTreeTextStagesAreGlobalBeforeEncoding(): void
    {
        $codec = new AuditJcsCodec();
        try {
            $codec->canonicalJson([
                'early' => "e\u{0301}",
                'later' => "invalid\xFF",
            ]);
            self::fail('later UTF-8 defect was not reported');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
        try {
            $codec->canonicalJson([
                'early' => "e\u{0301}",
                'later' => "control\u{0001}",
            ]);
            self::fail('later control defect was not reported');
        } catch (AuditValidationException $exception) {
            self::assertSame('control_invalid', $exception->code());
        }
    }

    public function testPreservesNumericStringObjectKeysAcrossNestedRoundTrip(): void
    {
        $codec = new AuditJcsCodec();
        $raw = '{"nested":{"0":"x","1":"y"}}';
        self::assertSame($raw, $codec->canonicalJson($codec->decode($raw)));
        self::assertInstanceOf(AuditJcsObject::class, $codec->toNative($codec->decode('{"0":"x","1":"y"}')));
    }

    public function testInvalidUtf8ObjectKeysAreUtf8FailuresBeforeDecodedDuplicateDetection(): void
    {
        $codec = new AuditJcsCodec();
        try {
            $codec->decode("{\"\x80\":1,\"\x81\":2}");
            self::fail('invalid UTF-8 accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
    }

    public function testRejectsUnsupportedValuesAndDuplicateRawKeys(): void
    {
        $codec = new AuditJcsCodec();
        $this->expectException(AuditValidationException::class);
        $codec->canonicalJson(1.5);
    }

    public function testDirectDecodeRejectsNonIntegerNumbersAndUnsafeIntegers(): void
    {
        $codec = new AuditJcsCodec();
        foreach (['1.0', '1e0', '-0', '9007199254740992', '-9007199254740992', 'NaN', 'Infinity'] as $value) {
            try {
                $codec->decode($value);
                self::fail('unsupported number accepted: ' . $value);
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
        self::assertSame(9_007_199_254_740_991, $codec->decode('9007199254740991'));
        self::assertSame(-9_007_199_254_740_991, $codec->decode('-9007199254740991'));
    }

    public function testEncoderAppliesAuditStringPolicyAndRawEscapeIsNotCanonical(): void
    {
        $codec = new AuditJcsCodec();
        foreach (["a\u{0000}", "a\u{200B}", "e\u{0301}", "\xFF"] as $value) {
            try {
                $codec->canonicalJson($value);
                self::fail('unsafe string accepted');
            } catch (AuditValidationException) {
                self::addToAssertionCount(1);
            }
        }
        foreach (['"\\/"', '"\\u2028"', '"\\u2029"'] as $raw) {
            try {
                $decoded = $codec->decode($raw);
                self::assertNotSame($raw, $codec->canonicalJson($decoded));
            } catch (AuditValidationException) {
                self::fail('valid escaped string rejected');
            }
        }
    }

    public function testDuplicateRawKeysAreRejectedBeforeObjectCollapse(): void
    {
        $codec = new AuditJcsCodec();
        foreach (['{"a":1,"a":2}', '{"a":1,"\\u0061":2}'] as $raw) {
            try {
                $codec->decode($raw);
                self::fail('duplicate key accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
    }

    public function testDecodedDuplicateWinsOverLaterSurrogateFailure(): void
    {
        $codec = new AuditJcsCodec();
        try {
            $codec->decode('{"a":"\\u' . 'D' . '800","\\u0061":"ok"}');
            self::fail('decoded duplicate accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
    }

    public function testUnpairedSurrogateIsUtf8FailureAndEmptyObjectIsNotAnEmptyList(): void
    {
        $codec = new AuditJcsCodec();
        try {
            $codec->decode('"\\u' . 'D' . '800"');
            self::fail('unpaired surrogate accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
        self::assertInstanceOf(AuditJcsObject::class, $codec->decode('{}'));
        self::assertIsArray($codec->decode('[]'));
        self::assertSame('\\u' . 'D' . '800', $codec->decode('"\\\\u' . 'D' . '800"'));
    }

    public function testSurrogateDetectionIsTokenAwareAroundEscapedBackslashesAndQuotes(): void
    {
        $codec = new AuditJcsCodec();
        $surrogate = 'D' . '800';
        self::assertSame('\\u' . $surrogate, $codec->decode('"\\\\u' . $surrogate . '"'));
        try {
            $codec->decode('{"value":"' . '\\"' . '\\u' . $surrogate . '"}');
            self::fail('unpaired surrogate after escaped quote accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
        $literal = '{"value":"' . '\\"' . '\\\\u' . $surrogate . '"}';
        self::assertSame($literal, $codec->canonicalJson($codec->decode($literal)));
    }

    public function testARejectedDecodeDoesNotLeakDeferredState(): void
    {
        $codec = new AuditJcsCodec();
        $codec->deferUtf8Validation();
        self::assertSame("\xEF\xBF\xBD", $codec->decode('"' . "\xFF" . '"'));
        self::assertTrue($codec->invalidUtf8Observed());
        self::assertSame('ok', $codec->decode('"ok"'));
        try {
            $codec->decode('"' . "\xFF" . '"');
            self::fail('deferred UTF-8 state leaked into the next decode');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
        try {
            $codec->decode('{');
            self::fail('malformed JSON accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('shape_invalid', $exception->code());
        }
        self::assertSame('ok', $codec->decode('"ok"'));
        try {
            $codec->decode('"\\u' . 'D' . '800"');
            self::fail('unpaired surrogate accepted');
        } catch (AuditValidationException $exception) {
            self::assertSame('utf8_invalid', $exception->code());
        }
    }

    public function testDirectObjectPairsRejectDuplicateDecodedKeysIncludingNestedPairs(): void
    {
        $codec = new AuditJcsCodec();
        foreach ([
            new AuditJcsObject([
                ['key' => 'duplicate', 'value' => 1],
                ['key' => 'duplicate', 'value' => 2],
            ]),
            new AuditJcsObject([
                [
                    'key' => 'outer',
                    'value' => new AuditJcsObject([
                        ['key' => 'duplicate', 'value' => true],
                        ['key' => 'duplicate', 'value' => false],
                    ]),
                ],
            ]),
        ] as $object) {
            try {
                $codec->canonicalJson($object);
                self::fail('duplicate direct object pair accepted');
            } catch (AuditValidationException $exception) {
                self::assertSame('shape_invalid', $exception->code());
            }
        }
    }
}
