<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Anchor;

use BlackOps\Internal\Audit\Anchor\AuditAnchorClaims;
use BlackOps\Internal\Audit\Anchor\AuditAnchorConfiguration;
use BlackOps\Internal\Audit\Anchor\AuditAnchorException;
use BlackOps\Internal\Audit\Anchor\AuditAnchorFailure;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPostCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorPreCallTransition;
use BlackOps\Internal\Audit\Anchor\AuditAnchorTime;
use PHPUnit\Framework\TestCase;

final class AuditAnchorConfigurationTest extends TestCase
{
    public function testDefaultsAndInclusiveBoundsAreNativeIntegers(): void
    {
        self::assertSame(300, new AuditAnchorConfiguration()->leaseDurationSeconds);
        $configuration = new AuditAnchorConfiguration(1, 86400);
        self::assertSame(1, $configuration->leaseDurationSeconds);
        self::assertSame(86400, $configuration->retryDelaySeconds);
    }

    public function testInvalidOrWrongTypeConfigurationIsFreshAndValueFree(): void
    {
        foreach ([
            [0, 300],
            [86401, 300],
            [300, 0],
            [300, 86401],
            ['300', 300],
            [300, '300'],
            [300.0, 300],
            [300, 300.0],
        ] as [$lease, $retry]) {
            try {
                new AuditAnchorConfiguration($lease, $retry);
                self::fail('invalid configuration accepted');
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::ConfigurationInvalid, $exception->failure());
                self::assertSame('Audit anchor state transition failed.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testCheckedTimestampArithmeticKeepsFractionsAndRejectsYearOverflow(): void
    {
        self::assertSame('2030-01-02T03:09:05.123456Z', AuditAnchorTime::addSeconds(
            '2030-01-02T03:04:05.123456Z',
            300,
        ));
        self::assertSame('2030-01-03T03:04:05.123456Z', AuditAnchorTime::addSeconds(
            '2030-01-02T03:04:05.123456Z',
            86400,
        ));
        try {
            AuditAnchorTime::addSeconds('9999-12-31T23:59:59.999999Z', 1);
            self::fail('four-digit year overflow accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::ResourceUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
    }

    public function testCalendarValidationUsesRealLeapAndInvalidDateBoundaries(): void
    {
        self::assertSame(
            '2032-02-29T23:59:59.123456Z',
            AuditAnchorTime::assertTimestamp('2032-02-29T23:59:59.123456Z'),
        );
        foreach (['2030-02-29T23:59:59.123456Z', '2032-04-31T23:59:59.123456Z'] as $invalid) {
            try {
                AuditAnchorTime::assertTimestamp($invalid);
                self::fail('invalid calendar date accepted: ' . $invalid);
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::IntegrityInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testDueAndExpiryBoundariesDistinguishMicroseconds(): void
    {
        $scheduled = '2030-01-02T03:04:05.123456Z';
        self::assertSame(-1, AuditAnchorTime::compare('2030-01-02T03:04:05.123455Z', $scheduled));
        self::assertSame(0, AuditAnchorTime::compare($scheduled, $scheduled));
        self::assertSame(1, AuditAnchorTime::compare('2030-01-02T03:04:05.123457Z', $scheduled));
    }

    public function testActualDueAndLeaseBoundariesUseIndependentMicrosecondVectors(): void
    {
        $configuration = new AuditAnchorConfiguration(300, 1);
        $claims = new AuditAnchorClaims($configuration);
        $pre = new AuditAnchorPreCallTransition($configuration);
        $post = new AuditAnchorPostCallTransition($configuration);
        $retryFrame = static function () use ($claims, $pre): \BlackOps\Internal\Audit\Anchor\AuditAnchorFrame {
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'due-token',
                AuditAnchorFixture::NOW,
            );
            return $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('lookup_unavailable'),
                AuditAnchorFixture::NOW,
            );
        };

        $beforeDue = $retryFrame();
        $beforeBytes = $beforeDue->toArray();
        try {
            $claims->preCallRetry(
                $beforeDue,
                AuditAnchorFixture::expectation($beforeDue),
                'before-due-token',
                '2030-01-02T03:04:06.123455Z',
            );
            self::fail('retry before due time accepted');
        } catch (AuditAnchorException $exception) {
            self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
        }
        self::assertSame($beforeBytes, $beforeDue->toArray());

        $atDue = $retryFrame();
        $claimedAtDue = $claims->preCallRetry(
            $atDue,
            AuditAnchorFixture::expectation($atDue),
            'at-due-token',
            '2030-01-02T03:04:06.123456Z',
        );
        self::assertSame('2030-01-02T03:04:06.123456Z', $claimedAtDue->intent()->toArray()['updatedAt']);

        $afterDue = $retryFrame();
        $claimedAfterDue = $claims->preCallRetry(
            $afterDue,
            AuditAnchorFixture::expectation($afterDue),
            'after-due-token',
            '2030-01-02T03:04:06.123457Z',
        );
        self::assertSame('2030-01-02T03:04:06.123457Z', $claimedAfterDue->intent()->toArray()['updatedAt']);

        $postFrame = static function () use ($claims, $pre): \BlackOps\Internal\Audit\Anchor\AuditAnchorFrame {
            $frame = AuditAnchorFixture::frame();
            $frame = $claims->externalSubmission(
                $frame,
                AuditAnchorFixture::expectation($frame),
                'lease-token',
                AuditAnchorFixture::NOW,
            );
            $frame = $pre->lookup(
                $frame,
                AuditAnchorFixture::expectation($frame),
                AuditAnchorFixture::reconciliation('external_object_absent'),
                AuditAnchorFixture::NOW,
            );
            return $pre->callStart($frame, AuditAnchorFixture::expectation($frame), AuditAnchorFixture::NOW);
        };

        $activeBeforeExpiry = $postFrame();
        $settled = $post->result(
            $activeBeforeExpiry,
            AuditAnchorFixture::expectation($activeBeforeExpiry),
            AuditAnchorFixture::result('unavailable'),
            '2030-01-02T03:09:05.123455Z',
        );
        self::assertSame('2030-01-02T03:09:06.123455Z', $settled->intent()->toArray()['nextAvailableAt']);

        foreach (['2030-01-02T03:09:05.123456Z', '2030-01-02T03:09:05.123457Z'] as $expiredAt) {
            $expired = $postFrame();
            $before = $expired->toArray();
            try {
                $post->result(
                    $expired,
                    AuditAnchorFixture::expectation($expired),
                    AuditAnchorFixture::result('unavailable'),
                    $expiredAt,
                );
                self::fail('expired post-call lease accepted at ' . $expiredAt);
            } catch (AuditAnchorException $exception) {
                self::assertSame(AuditAnchorFailure::TransitionConflict, $exception->failure());
            }
            self::assertSame($before, $expired->toArray());
        }
    }
}
