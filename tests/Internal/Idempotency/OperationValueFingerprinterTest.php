<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Idempotency;

use BlackOps\Core\Codec\OperationCodecException;
use BlackOps\Core\OperationValue;
use BlackOps\Internal\Idempotency\OperationValueFingerprinter;
use PHPUnit\Framework\TestCase;

final class OperationValueFingerprinterTest extends TestCase
{
    public function testFingerprintIsDeterministicAndIncludesOperationTypeAndFields(): void
    {
        $fingerprinter = new OperationValueFingerprinter();

        $first = $fingerprinter->fingerprint('reports.create', new FingerprintValue('weekly', 7, ['b' => 2, 'a' => 1]));
        $same = $fingerprinter->fingerprint('reports.create', new FingerprintValue('weekly', 7, ['a' => 1, 'b' => 2]));
        $differentType = $fingerprinter->fingerprint('reports.update', new FingerprintValue('weekly', 7, [
            'a' => 1,
            'b' => 2,
        ]));
        $differentValue = $fingerprinter->fingerprint('reports.create', new FingerprintValue('weekly', 8, [
            'a' => 1,
            'b' => 2,
        ]));

        self::assertTrue($first->equals($same));
        self::assertFalse($first->equals($differentType));
        self::assertFalse($first->equals($differentValue));
    }

    public function testUnsupportedValueShapeFailsWithoutIncludingValue(): void
    {
        $this->expectException(OperationCodecException::class);
        $this->expectExceptionMessage('fingerprint codec');

        new OperationValueFingerprinter()->fingerprint('reports.create', new FingerprintObjectValue(new \stdClass()));
    }

    public function testFiniteFloatsAreDeterministicAndNonFiniteFloatsFailSafely(): void
    {
        $fingerprinter = new OperationValueFingerprinter();
        $same = $fingerprinter->fingerprint('reports.create', new FingerprintFloatValue(1.5));
        $sameAgain = $fingerprinter->fingerprint('reports.create', new FingerprintFloatValue(1.5));
        $different = $fingerprinter->fingerprint('reports.create', new FingerprintFloatValue(1.75));

        self::assertTrue($same->equals($sameAgain));
        self::assertFalse($same->equals($different));

        foreach ([INF, NAN] as $value) {
            try {
                $fingerprinter->fingerprint('reports.create', new FingerprintFloatValue($value));
                self::fail('Expected non-finite float failure.');
            } catch (OperationCodecException $exception) {
                self::assertSame('Operation value contains an unsupported float.', $exception->getMessage());
            }
        }
    }
}

/** @mago-expect lint:single-class-per-file */
final readonly class FingerprintValue implements OperationValue
{
    /** @param array<string, int> $tags */
    public function __construct(
        public string $name,
        public int $count,
        public array $tags,
    ) {}
}

/** @mago-expect lint:single-class-per-file */
final readonly class FingerprintObjectValue implements OperationValue
{
    public function __construct(
        public object $unsupported,
    ) {}
}

/** @mago-expect lint:single-class-per-file */
final readonly class FingerprintFloatValue implements OperationValue
{
    public function __construct(
        public float $amount,
    ) {}
}
