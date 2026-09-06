<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Read;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Audit\AuditDelivery;
use BlackOps\Core\Audit\AuditOpaqueIdPurpose;
use BlackOps\Core\Audit\AuditReadAuthorizationDecision;
use BlackOps\Core\Audit\AuditReadContext;
use BlackOps\Core\Audit\AuditReadPurpose;
use BlackOps\Core\Audit\AuditRecordDraft;
use BlackOps\Core\Audit\AuditSourceRuntime;
use BlackOps\Core\Audit\AuditTypedReference;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\Internal\Audit\HmacAuditOpaqueIdDeriver;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Audit\Read\AuditReadEventWriter;
use BlackOps\Internal\Audit\Read\OperationReadAuditSession;
use BlackOps\Internal\Audit\Read\OperationReadAuditSessionFactory;
use BlackOps\Internal\Audit\Read\OperationReadExecutionSource;
use BlackOps\Internal\Audit\Read\OperationReadNonceSource;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use ReflectionClass;

final class OperationReadAuditSessionTest extends TestCase
{
    public function testFactoryBuildsAllPurposesAndCommitsRequestedBeforeReturningSession(): void
    {
        $expectedReferences = [
            'status' => 'oid:v1:5a83343aa8fcf1e3baba2147571a92fa35c5424507c0b27407a0ca19766a3b3a',
            'journal' => 'oid:v1:2726a66731f81f741ccb83a471637381cd80b944687aa9889b6975f100eb5096',
            'outcome' => 'oid:v1:3ac6231d80a97591552b92f933f4a1e50efd69e93c149961da65855f1c4b2e67',
            'diagnostics' => 'oid:v1:aa763c94292e005ea5dc420909a1b90372602265d3fe9318ae053b1b214fca4c',
        ];
        foreach (AuditReadPurpose::cases() as $purpose) {
            $writer = new OperationReadTestWriter();
            $factory = $this->factory($writer);
            $session = $factory->begin(
                $purpose,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                new ActorRef('actor-1', 'user'),
                new TenantRef('account', 'tenant-1'),
            );

            self::assertCount(1, $writer->requested);
            self::assertSame($expectedReferences[$purpose->value], $writer->requested[0][0]->requestRef());
            self::assertSame($writer->requested[0][0]->requestRef(), $writer->requested[0][1]->action->id);
            self::assertSame(
                'oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115',
                $writer->requested[0][1]->resource->id,
            );
            self::assertSame(
                'access.operation_' . $purpose->value . '.requested',
                $writer->requested[0][1]->event->type,
            );
            self::assertSame('operation-' . $purpose->value, $writer->requested[0][1]->source->component);
            self::assertSame(AuditDelivery::Required, $writer->requested[0][1]->policy->auditDelivery);
            self::assertSame([], $writer->requested[0][1]->correlation->toArray());
            self::assertSame(['version' => 1], $writer->requested[0][1]->evidence->toArray());
            self::assertSame(0, $writer->releaseCount);

            $session->accessed();
            self::assertSame(1, $writer->releaseCount);
            self::assertSame(AuditReadAuthorizationDecision::Allow, $writer->terminals[0][0]);
            self::assertSame(
                'access.operation_' . $purpose->value . '.accessed',
                $writer->terminals[0][1]->event->type,
            );
        }
    }

    public function testUnavailableTerminalUsesTheFixedUnavailableDraftAndIsOneShot(): void
    {
        $writer = new OperationReadTestWriter();
        $session = $this->factory($writer)->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );

        $session->unavailable();
        self::assertCount(1, $writer->terminals);
        self::assertSame(AuditReadAuthorizationDecision::ResourceUnavailable, $writer->terminals[0][0]);
        self::assertSame('access.operation_status.unavailable', $writer->terminals[0][1]->event->type);
        try {
            $session->accessed();
            self::fail('a second terminal attempt was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
        self::assertCount(1, $writer->terminals);
    }

    public function testFactoryBuildsUnavailableTerminalForEveryPurpose(): void
    {
        foreach (AuditReadPurpose::cases() as $purpose) {
            $writer = new OperationReadTestWriter();
            $session = $this->factory($writer)->begin(
                $purpose,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );

            self::assertCount(1, $writer->requested, $purpose->value);
            $session->unavailable();
            self::assertCount(1, $writer->terminals, $purpose->value);
            self::assertSame(
                AuditReadAuthorizationDecision::ResourceUnavailable,
                $writer->terminals[0][0],
                $purpose->value,
            );
            self::assertSame(
                'access.operation_' . $purpose->value . '.unavailable',
                $writer->terminals[0][1]->event->type,
                $purpose->value,
            );
        }
    }

    public function testWriterDecisionMismatchClosesSessionAfterTheAppend(): void
    {
        $writer = new OperationReadTestWriter();
        $writer->terminalDecision = AuditReadAuthorizationDecision::Deny;
        $session = $this->factory($writer)->begin(
            AuditReadPurpose::Outcome,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );

        try {
            $session->accessed();
            self::fail('a mismatching writer decision was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        try {
            $session->unavailable();
            self::fail('a closed session was retried');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
        self::assertSame(1, $writer->releaseCount);
    }

    public function testExplicitWriterFailureIdentityIsPreservedAndNoTerminalRetryOccurs(): void
    {
        $writer = new OperationReadTestWriter();
        $requestedFailure = new AuditDeliveryException(AuditDeliveryFailure::ResourceUnavailable);
        $writer->requestedFailure = $requestedFailure;
        try {
            $this->factory($writer)->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('requested writer failure was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame($requestedFailure, $exception);
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
        }

        $writer = new OperationReadTestWriter();
        $terminalFailure = new AuditDeliveryException(AuditDeliveryFailure::TransactionInvalid);
        $writer->terminalFailure = $terminalFailure;
        $session = $this->factory($writer)->begin(
            AuditReadPurpose::Journal,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        try {
            $session->unavailable();
            self::fail('terminal writer failure was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame($terminalFailure, $exception);
        }
        try {
            $session->unavailable();
            self::fail('terminal writer failure allowed a retry');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
        self::assertSame(1, $writer->releaseCount);
    }

    public function testUnexpectedWriterThrowablesContractAndCloseWithoutLeakingDetails(): void
    {
        $writer = new OperationReadTestWriter();
        $writer->requestedThrowable = new \RuntimeException('unexpected requested canary');
        try {
            $this->factory($writer)->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('unexpected requested throwable was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('unexpected requested canary', $exception->getTraceAsString());
        }
        self::assertCount(0, $writer->requested);

        $writer = new OperationReadTestWriter();
        $writer->terminalThrowable = new \RuntimeException('unexpected terminal canary');
        $session = $this->factory($writer)->begin(
            AuditReadPurpose::Outcome,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        try {
            $session->accessed();
            self::fail('unexpected terminal throwable was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('unexpected terminal canary', $exception->getTraceAsString());
        }
        try {
            $session->unavailable();
            self::fail('unexpected terminal throwable allowed a retry');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
        self::assertSame(1, $writer->releaseCount);
        self::assertCount(0, $writer->terminals);
    }

    public function testEveryTerminalDuplicatePermutationPerformsNoSecondWriterCall(): void
    {
        foreach ([
            ['accessed',    'accessed'],
            ['accessed',    'unavailable'],
            ['unavailable', 'accessed'],
            ['unavailable', 'unavailable'],
        ] as [$first, $second]) {
            $writer = new OperationReadTestWriter();
            $session = $this->factory($writer)->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            $session->{$first}();
            try {
                $session->{$second}();
                self::fail($first . ' then ' . $second . ' duplicate was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
                self::assertNull($exception->getPrevious());
            }
            self::assertSame(1, $writer->releaseCount, $first . ':' . $second);
            self::assertCount(1, $writer->terminals, $first . ':' . $second);
        }
    }

    public function testActualCloneAndDestructorHaveNoWriterOrStoreWork(): void
    {
        $writer = new OperationReadTestWriter();
        $session = $this->factory($writer)->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        try {
            $unused = clone $session;
            self::fail('actual session clone was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        unset($unused);
        self::assertSame(0, $writer->releaseCount);
        unset($session);
        self::assertSame(0, $writer->releaseCount);
        self::assertCount(0, $writer->terminals);
    }

    public function testFactoryCloneIsIoFreeAndRetainsOnlyOpaqueDependencies(): void
    {
        $writer = new OperationReadTestWriter();
        $nonce = new OperationReadTestNonceSource();
        $clock = new OperationReadTestClock();
        $execution = new OperationReadTestExecutionSource();
        $factory = $this->factory($writer, $nonce, $clock, $execution);
        $clone = clone $factory;
        self::assertSame(0, $nonce->calls);
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $execution->calls);
        $clone->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        self::assertSame(1, $nonce->calls);
        self::assertSame(1, $clock->calls);
        self::assertSame(1, $execution->calls);
        self::assertCount(1, $writer->requested);
    }

    public function testFactoryFailurePrecedenceAndDependencyCallCounts(): void
    {
        $execution = new OperationReadTestExecutionSource();
        $nonce = new OperationReadTestNonceSource();
        $clock = new OperationReadTestClock();
        $writer = new OperationReadTestWriter();
        $factory = $this->factory($writer, $nonce, $clock, $execution);
        $execution->failure = new \RuntimeException('hidden execution detail');
        try {
            $factory->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('execution failure was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure());
            self::assertStringNotContainsString('hidden execution detail', $exception->getTraceAsString());
        }
        self::assertSame(1, $execution->calls);
        self::assertSame(0, $nonce->calls);
        self::assertSame(0, $clock->calls);
        self::assertCount(0, $writer->requested);

        $nonce = new OperationReadTestNonceSource();
        $nonce->bytes = str_repeat('x', 15);
        $factory = $this->factory(new OperationReadTestWriter(), $nonce);
        try {
            $factory->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('wrong nonce length was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
        }
        self::assertSame(1, $nonce->calls);
    }

    public function testInvalidBuildIsRejectedBeforeAnyDependencyCall(): void
    {
        $writer = new OperationReadTestWriter();
        $nonce = new OperationReadTestNonceSource();
        $clock = new OperationReadTestClock();
        $execution = new OperationReadTestExecutionSource();
        try {
            $this->factory($writer, $nonce, $clock, $execution, "e\u{0301}");
            self::fail('non-NFC build ID was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure());
            self::assertNull($exception->getPrevious());
        }
        self::assertCount(0, $writer->requested);
        self::assertSame(0, $nonce->calls);
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $execution->calls);
    }

    public function testBuildBoundariesAndEveryTypedRuntimeAreAccepted(): void
    {
        foreach (AuditSourceRuntime::cases() as $runtime) {
            foreach (['b', str_repeat('b', 256), 'build-é'] as $buildId) {
                $writer = new OperationReadTestWriter();
                $session = $this->factory($writer, buildId: $buildId, runtime: $runtime)->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::assertSame($runtime, $writer->requested[0][1]->source->runtime, $runtime->value);
                self::assertSame($buildId, $writer->requested[0][1]->source->buildId, $runtime->value);
                $session->unavailable();
            }
        }
    }

    public function testEveryInvalidBuildFormFailsBeforeAllDependencyCalls(): void
    {
        foreach ([
            'empty' => '',
            '257-bytes' => str_repeat('b', 257),
            'invalid-utf8' => "\xC3\x28",
            'control' => "build-\x01",
            'format-control' => "build-\u{200B}",
            'non-nfc' => "e\u{0301}",
        ] as $name => $buildId) {
            $writer = new OperationReadTestWriter();
            $nonce = new OperationReadTestNonceSource();
            $clock = new OperationReadTestClock();
            $execution = new OperationReadTestExecutionSource();
            try {
                $this->factory($writer, $nonce, $clock, $execution, $buildId);
                self::fail($name . ' build ID was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ConfigurationInvalid, $exception->failure(), $name);
                self::assertNull($exception->getPrevious(), $name);
            }
            self::assertCount(0, $writer->requested, $name);
            self::assertSame(0, $nonce->calls, $name);
            self::assertSame(0, $clock->calls, $name);
            self::assertSame(0, $execution->calls, $name);
        }
    }

    public function testActorAndTenantQuadrantsAndAlternateReferencesAreCopied(): void
    {
        $actor = new ActorRef('alternate-actor', 'service');
        $tenant = new TenantRef('organization', 'alternate-tenant');
        foreach ([
            [$actor, $tenant],
            [$actor, null],
            [null, $tenant],
            [null, null],
        ] as [$currentActor, $currentTenant]) {
            $writer = new OperationReadTestWriter();
            $this->factory($writer)->begin(
                AuditReadPurpose::Journal,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                $currentActor,
                $currentTenant,
            );
            self::assertSame($currentActor?->type(), $writer->requested[0][0]->actor?->type);
            self::assertSame($currentActor?->id(), $writer->requested[0][0]->actor?->id);
            self::assertSame($currentTenant?->type(), $writer->requested[0][0]->tenant?->type);
            self::assertSame($currentTenant?->id(), $writer->requested[0][0]->tenant?->id);
        }
    }

    public function testAllRequestReferenceVectorsPinEveryPurposeAndNullAxis(): void
    {
        $references = [
            'present' => [
                'status' => 'oid:v1:5a83343aa8fcf1e3baba2147571a92fa35c5424507c0b27407a0ca19766a3b3a',
                'journal' => 'oid:v1:2726a66731f81f741ccb83a471637381cd80b944687aa9889b6975f100eb5096',
                'outcome' => 'oid:v1:3ac6231d80a97591552b92f933f4a1e50efd69e93c149961da65855f1c4b2e67',
                'diagnostics' => 'oid:v1:aa763c94292e005ea5dc420909a1b90372602265d3fe9318ae053b1b214fca4c',
            ],
            'actor-null' => [
                'status' => 'oid:v1:cd89b14808a199faa3cf13e514d7ad5ec95eb32a8516b7c2321a8f1d3cc95ddc',
                'journal' => 'oid:v1:c2957ec7306b126e7643d534126033606f168a81dc9a41442d3ef1ea9b9d6b8a',
                'outcome' => 'oid:v1:95ad1f9ccb0f32b3f2085827a9f501de19c97fe78c4220d78dd9d20e50f403da',
                'diagnostics' => 'oid:v1:bc163d83033e9c7ca45b3f5a1c16ae397209bcefd3e36767baab0c2aad480524',
            ],
            'tenant-null' => [
                'status' => 'oid:v1:5c31e549662bda11f2460c0f7731fca583b10d88bd7bc58f821c1af0eb44cc22',
                'journal' => 'oid:v1:28c20b340ecedd146a60b1455d5a9a8e65d9a531b1c22380c8df82c346479071',
                'outcome' => 'oid:v1:11aa1377029d9cd2b3f3bc9dfd30c6f10579c835c5329c8084ce920c9a6d3e4e',
                'diagnostics' => 'oid:v1:b6a8cd01e830497c0fda670c626936235a72ffafa170f7a934563f207c26e40a',
            ],
            'both-null' => [
                'status' => 'oid:v1:54ec3cca56a9889bd2db4121bda3fbd43a5cf4dab7cc499ae8fac99d269f2d14',
                'journal' => 'oid:v1:4cfa5543b93026dceb5b64e614cfad302449258690a77b1fcda8e898226f57fa',
                'outcome' => 'oid:v1:94bd6287233881dff051aca0a2882616221326a89794638f336089ea88b24d42',
                'diagnostics' => 'oid:v1:7c8b5fd6c25a78be84297746523e5bb8146672a46793c60b9c730e0fd00317b3',
            ],
        ];
        $actor = new ActorRef('actor-1', 'user');
        $tenant = new TenantRef('account', 'tenant-1');
        foreach ([
            'present' => [$actor, $tenant],
            'actor-null' => [null, $tenant],
            'tenant-null' => [$actor, null],
            'both-null' => [null, null],
        ] as $axis => [$currentActor, $currentTenant]) {
            foreach (AuditReadPurpose::cases() as $purpose) {
                $writer = new OperationReadTestWriter();
                $this->factory($writer)->begin(
                    $purpose,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    $currentActor,
                    $currentTenant,
                );
                self::assertSame($references[$axis][$purpose->value], $writer->requested[0][0]->requestRef());
                self::assertSame($references[$axis][$purpose->value], $writer->requested[0][1]->action->id);
            }
        }
    }

    public function testMalformedActorAndTenantConversionsFailBeforeDependencyCalls(): void
    {
        foreach ([
            'actor-type' => [$this->malformedActor('', 'actor-1'), null],
            'actor-id' => [$this->malformedActor('user', ''), null],
            'tenant-type' => [null, $this->malformedTenant('', 'tenant-1')],
            'tenant-id' => [null, $this->malformedTenant('account', '')],
        ] as $name => [$actor, $tenant]) {
            $writer = new OperationReadTestWriter();
            $nonce = new OperationReadTestNonceSource();
            $clock = new OperationReadTestClock();
            $execution = new OperationReadTestExecutionSource();
            try {
                $this->factory($writer, $nonce, $clock, $execution)->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    $actor,
                    $tenant,
                );
                self::fail($name . ' malformed reference was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure(), $name);
                self::assertNull($exception->getPrevious(), $name);
            }
            self::assertSame(0, $execution->calls, $name);
            self::assertSame(0, $clock->calls, $name);
            self::assertSame(0, $nonce->calls, $name);
            self::assertCount(0, $writer->requested, $name);
        }
    }

    public function testExecutionSourceThrowTypeErrorAndMalformedReferenceHaveExactPrecedence(): void
    {
        foreach ([
            'throwable' => [
                new \RuntimeException('hidden execution detail'),
                false,
                AuditDeliveryFailure::ResourceUnavailable,
            ],
            'return-type-error' => [null, true, AuditDeliveryFailure::ResourceUnavailable],
            'malformed-type' => [null, false, AuditDeliveryFailure::PolicyInvalid],
            'malformed-id' => [null, false, AuditDeliveryFailure::PolicyInvalid],
        ] as $name => [$failure, $returnTypeError, $expectedFailure]) {
            $writer = new OperationReadTestWriter();
            $execution = new OperationReadTestExecutionSource();
            $execution->failure = $failure;
            $execution->returnTypeError = $returnTypeError;
            if ($name === 'malformed-type') {
                $execution->reference = $this->malformedTypedReference('', 'blackops-cli');
            } elseif ($name === 'malformed-id') {
                $execution->reference = $this->malformedTypedReference('process', '');
            }
            $clock = new OperationReadTestClock();
            $nonce = new OperationReadTestNonceSource();
            try {
                $this->factory($writer, $nonce, $clock, $execution)->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::fail($name . ' execution state was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame($expectedFailure, $exception->failure(), $name);
                self::assertNull($exception->getPrevious(), $name);
                self::assertStringNotContainsString('hidden execution detail', $exception->getTraceAsString(), $name);
            }
            self::assertSame(1, $execution->calls, $name);
            self::assertSame(0, $clock->calls, $name);
            self::assertSame(0, $nonce->calls, $name);
            self::assertCount(0, $writer->requested, $name);
        }
    }

    public function testResourceDerivationUsesOneKeyedResourcePurposeAndConstrictsBadKeyState(): void
    {
        $wrongKey = new HmacAuditOpaqueIdDeriver('fedcba9876543210fedcba9876543210');
        $writer = new OperationReadTestWriter();
        $this->factory($writer, opaqueIds: $wrongKey)->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        self::assertNotSame(
            'oid:v1:e697b9f4332f3532bd84ba2bbe553152c5d223663d3bdec36e8b38e420f1d115',
            $writer->requested[0][1]->resource->id,
        );
        $operationId = '0194a000-0000-7000-8000-0000000000d2';
        $resourceId = (string) $wrongKey->derive(AuditOpaqueIdPurpose::Resource, $operationId);
        self::assertSame($resourceId, $writer->requested[0][1]->resource->id);
        foreach (AuditOpaqueIdPurpose::cases() as $purpose) {
            if ($purpose === AuditOpaqueIdPurpose::Resource) {
                continue;
            }
            $thrown = false;
            $otherId = null;
            try {
                $otherId = (string) $wrongKey->derive($purpose, $operationId);
            } catch (\Throwable) {
                $thrown = true;
            }
            if (in_array($purpose, [AuditOpaqueIdPurpose::Tenant, AuditOpaqueIdPurpose::Evidence], true)) {
                self::assertTrue($thrown, $purpose->value);
                continue;
            }
            self::assertFalse($thrown, $purpose->value);
            self::assertNotSame($resourceId, $otherId, $purpose->value);
        }

        $writer = new OperationReadTestWriter();
        $execution = new OperationReadTestExecutionSource();
        $clock = new OperationReadTestClock();
        $nonce = new OperationReadTestNonceSource();
        try {
            $this->factory(
                $writer,
                $nonce,
                $clock,
                $execution,
                opaqueIds: $this->malformedDeriver('wrong-key-state'),
            )->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('malformed HMAC key state was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::KeyUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('wrong-key-state', $exception->getTraceAsString());
        }
        self::assertSame(1, $execution->calls);
        self::assertSame(0, $clock->calls);
        self::assertSame(0, $nonce->calls);
        self::assertCount(0, $writer->requested);
    }

    public function testClockIsCapturedOnceNormalizedToUtcAndRejectsUnsupportedYears(): void
    {
        $writer = new OperationReadTestWriter();
        $clock = new OperationReadTestClock();
        $clock->value = new \DateTimeImmutable('2026-09-05T11:00:00.123456+09:00');
        $execution = new OperationReadTestExecutionSource();
        $nonce = new OperationReadTestNonceSource();
        $this->factory($writer, $nonce, $clock, $execution)->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        self::assertSame('2026-09-05T02:00:00.123456Z', $writer->requested[0][0]->requestedAt);
        self::assertSame(1, $clock->calls);
        self::assertSame(1, $nonce->calls);

        foreach ([
            'clock-throwable' => static function (OperationReadTestClock $clock): void {
                $clock->failure = new \RuntimeException('hidden clock detail');
            },
            'expanded-year' => static function (OperationReadTestClock $clock): void {
                $clock->value = new \DateTimeImmutable('+10000-01-01T00:00:00.123456Z');
            },
        ] as $name => $configure) {
            $writer = new OperationReadTestWriter();
            $clock = new OperationReadTestClock();
            $configure($clock);
            $nonce = new OperationReadTestNonceSource();
            $execution = new OperationReadTestExecutionSource();
            try {
                $this->factory($writer, $nonce, $clock, $execution)->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                self::fail($name . ' clock state was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $exception->failure(), $name);
                self::assertNull($exception->getPrevious(), $name);
                self::assertStringNotContainsString('hidden clock detail', $exception->getTraceAsString(), $name);
            }
            self::assertSame(1, $execution->calls, $name);
            self::assertSame(1, $clock->calls, $name);
            self::assertSame(0, $nonce->calls, $name);
            self::assertCount(0, $writer->requested, $name);
        }
    }

    public function testNonceHasOneCallExactSixteenBytesAndLowercaseEncoding(): void
    {
        foreach ([
            'exact16' => ["\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff", null],
            'nul-containing' => ["A\x00BCDEFGHIJKLMNO", null],
            '15' => [str_repeat('x', 15), AuditDeliveryFailure::ConfigurationInvalid],
            '17' => [str_repeat('x', 17), AuditDeliveryFailure::ConfigurationInvalid],
            'empty' => ['', AuditDeliveryFailure::ConfigurationInvalid],
            'throwable' => [null, AuditDeliveryFailure::ResourceUnavailable],
        ] as $name => [$bytes, $expectedFailure]) {
            $label = (string) $name;
            $writer = new OperationReadTestWriter();
            $nonce = new OperationReadTestNonceSource();
            $nonce->bytes = $bytes ?? $nonce->bytes;
            if ($name === 'throwable') {
                $nonce->failure = new \RuntimeException('hidden nonce detail');
            }
            $clock = new OperationReadTestClock();
            $execution = new OperationReadTestExecutionSource();
            try {
                $this->factory($writer, $nonce, $clock, $execution)->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    null,
                    null,
                );
                if ($expectedFailure !== null) {
                    self::fail($label . ' nonce state was accepted');
                }
            } catch (AuditDeliveryException $exception) {
                self::assertNotNull($expectedFailure, $label);
                self::assertSame($expectedFailure, $exception->failure(), $label);
                self::assertNull($exception->getPrevious(), $label);
                self::assertStringNotContainsString('hidden nonce detail', $exception->getTraceAsString(), $label);
            }
            self::assertSame(1, $nonce->calls, $label);
            self::assertSame(1, $clock->calls, $label);
            self::assertCount($expectedFailure === null ? 1 : 0, $writer->requested, $label);
            if ($expectedFailure === null) {
                if ($label === 'exact16') {
                    self::assertSame('00112233445566778899aabbccddeeff', $writer->requested[0][0]->nonce, $label);
                }
                self::assertSame(32, strlen($writer->requested[0][0]->nonce), $label);
                self::assertSame(strtolower($writer->requested[0][0]->nonce), $writer->requested[0][0]->nonce, $label);
            }
        }
    }

    public function testSerializationAndCorruptSnapshotsNeverExposeOrPerformWork(): void
    {
        $writer = new OperationReadTestWriter();
        $factory = $this->factory($writer);
        self::assertSame([], $factory->__debugInfo());
        self::assertSame([], $factory->__serialize());
        self::assertStringNotContainsString('0194a000', print_r($factory, true));
        try {
            $factory->__unserialize([]);
            self::fail('factory unserialize was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }
        $factoryReflection = new ReflectionClass($factory);
        $factoryReflection->getProperty('snapshot')->setValue($factory, new \SensitiveParameterValue([]));
        try {
            $factory->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('corrupt factory snapshot was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }
        self::assertCount(0, $writer->requested);

        $writer = new OperationReadTestWriter();
        $session = $this->factory($writer)->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        self::assertSame([], $session->__debugInfo());
        self::assertSame([], $session->__serialize());
        try {
            $session->__clone();
            self::fail('session clone was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
        try {
            $session->__unserialize([]);
            self::fail('session unserialize was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }
        $sessionReflection = new ReflectionClass($session);
        $sessionReflection->getProperty('snapshot')->setValue($session, new \SensitiveParameterValue([]));
        try {
            $session->accessed();
            self::fail('corrupt session snapshot was accepted');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
        }
        self::assertCount(0, $writer->terminals);
    }

    public function testEveryFactorySnapshotShapeMutationFailsBeforeDependencyWork(): void
    {
        $writer = new OperationReadTestWriter();
        $nonce = new OperationReadTestNonceSource();
        $clock = new OperationReadTestClock();
        $execution = new OperationReadTestExecutionSource();
        $factory = $this->factory($writer, $nonce, $clock, $execution);
        $reflection = new ReflectionClass($factory);
        $property = $reflection->getProperty('snapshot');
        $snapshot = $property->getValue($factory)->getValue();
        $mutations = [
            'missing-writer' => static function (array &$value): void {
                unset($value['writer']);
            },
            'extra-key' => static function (array &$value): void {
                $value['extra'] = 'snapshot-canary';
            },
            'wrong-writer' => static function (array &$value): void {
                $value['writer'] = new \stdClass();
            },
            'wrong-opaque-ids' => static function (array &$value): void {
                $value['opaqueIds'] = new \stdClass();
            },
            'wrong-nonces' => static function (array &$value): void {
                $value['nonces'] = new \stdClass();
            },
            'wrong-clock' => static function (array &$value): void {
                $value['clock'] = new \stdClass();
            },
            'wrong-executions' => static function (array &$value): void {
                $value['executions'] = new \stdClass();
            },
            'wrong-runtime' => static function (array &$value): void {
                $value['runtime'] = 'cli';
            },
            'wrong-build' => static function (array &$value): void {
                $value['buildId'] = ['snapshot-canary'];
            },
            'unsafe-build' => static function (array &$value): void {
                $value['buildId'] = "build-\0snapshot-canary";
            },
        ];
        foreach ($mutations as $name => $mutate) {
            $candidate = $snapshot;
            $mutate($candidate);
            $property->setValue($factory, new \SensitiveParameterValue($candidate));
            try {
                $factory->begin(
                    AuditReadPurpose::Status,
                    OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                    new ActorRef('actor-1', 'user'),
                    new TenantRef('account', 'tenant-1'),
                );
                self::fail($name . ' factory snapshot was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure(), $name);
                self::assertNull($exception->getPrevious(), $name);
                self::assertStringNotContainsString('snapshot-canary', $exception->getTraceAsString(), $name);
            }
            self::assertSame(0, $execution->calls, $name);
            self::assertSame(0, $clock->calls, $name);
            self::assertSame(0, $nonce->calls, $name);
            self::assertCount(0, $writer->requested, $name);
        }
    }

    public function testEverySessionSnapshotShapeMutationClosesWithoutTerminalWork(): void
    {
        $mutations = [
            'missing-writer' => static function (array &$value): void {
                unset($value['writer']);
            },
            'extra-key' => static function (array &$value): void {
                $value['extra'] = 'session-canary';
            },
            'wrong-writer' => static function (array &$value): void {
                $value['writer'] = new \stdClass();
            },
            'wrong-basis' => static function (array &$value): void {
                $value['basis'] = new \stdClass();
            },
            'wrong-accessed' => static function (array &$value): void {
                $value['accessed'] = new \stdClass();
            },
            'wrong-unavailable' => static function (array &$value): void {
                $value['unavailable'] = new \stdClass();
            },
        ];
        foreach ($mutations as $name => $mutate) {
            $writer = new OperationReadTestWriter();
            $session = $this->factory($writer)->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            $reflection = new ReflectionClass($session);
            $property = $reflection->getProperty('snapshot');
            $candidate = $property->getValue($session)->getValue();
            $mutate($candidate);
            $property->setValue($session, new \SensitiveParameterValue($candidate));
            try {
                $session->accessed();
                self::fail($name . ' session snapshot was accepted');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure(), $name);
                self::assertNull($exception->getPrevious(), $name);
            }
            try {
                $session->unavailable();
                self::fail($name . ' corrupt session was retried');
            } catch (AuditDeliveryException $exception) {
                self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure(), $name);
            }
            self::assertSame(0, $writer->releaseCount, $name);
            self::assertCount(0, $writer->terminals, $name);
        }
    }

    public function testFactorySessionAndFailureSurfacesDoNotExposeSensitiveCanaries(): void
    {
        $canary = 'operation-read-sensitive-canary';
        $writer = new OperationReadTestWriter();
        $factory = $this->factory($writer, buildId: $canary);
        $session = $factory->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        $this->assertValueFree($factory, $canary);
        $this->assertValueFree($session, $canary);

        $writer = new OperationReadTestWriter();
        $writer->requestedThrowable = new \RuntimeException($canary);
        try {
            $this->factory($writer, buildId: $canary)->begin(
                AuditReadPurpose::Status,
                OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
                null,
                null,
            );
            self::fail('sensitive requested failure was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($canary, $exception->getMessage());
            self::assertStringNotContainsString($canary, $exception->getTraceAsString());
            $this->assertValueFree($exception, $canary);
        }

        $writer = new OperationReadTestWriter();
        $writer->terminalThrowable = new \RuntimeException($canary);
        $session = $this->factory($writer, buildId: $canary)->begin(
            AuditReadPurpose::Status,
            OperationId::fromString('0194a000-0000-7000-8000-0000000000d2'),
            null,
            null,
        );
        try {
            $session->accessed();
            self::fail('sensitive terminal failure was ignored');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $exception->failure());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($canary, $exception->getMessage());
            self::assertStringNotContainsString($canary, $exception->getTraceAsString());
            $this->assertValueFree($exception, $canary);
        }
        try {
            $session->unavailable();
            self::fail('sensitive terminal failure allowed a retry');
        } catch (AuditDeliveryException $exception) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $exception->failure());
        }
        self::assertSame(1, $writer->releaseCount);
        self::assertCount(0, $writer->terminals);
    }

    private function factory(
        OperationReadTestWriter $writer,
        ?OperationReadTestNonceSource $nonce = null,
        ?OperationReadTestClock $clock = null,
        ?OperationReadTestExecutionSource $execution = null,
        string $buildId = 'build-1',
        ?HmacAuditOpaqueIdDeriver $opaqueIds = null,
        AuditSourceRuntime $runtime = AuditSourceRuntime::Cli,
    ): OperationReadAuditSessionFactory {
        return new OperationReadAuditSessionFactory(
            $writer,
            $opaqueIds ?? new HmacAuditOpaqueIdDeriver('0123456789abcdef0123456789abcdef'),
            $nonce ?? new OperationReadTestNonceSource(),
            $clock ?? new OperationReadTestClock(),
            $execution ?? new OperationReadTestExecutionSource(),
            $runtime,
            $buildId,
        );
    }

    private function assertValueFree(object $value, string $canary): void
    {
        $surfaces = [
            'print_r' => print_r($value, true),
            'serialize' => $this->captureSurface(static fn(): string => serialize($value)),
            'json' => $this->captureSurface(static fn(): string => (string) json_encode($value, JSON_THROW_ON_ERROR)),
            'var_export' => var_export($value, true),
        ];
        ob_start();
        debug_zval_dump($value);
        $surfaces['debug'] = (string) ob_get_clean();
        foreach ($surfaces as $surface => $output) {
            self::assertIsString($output, $surface);
            self::assertStringNotContainsString($canary, $output, $surface);
        }
    }

    private function captureSurface(\Closure $surface): string
    {
        try {
            return $surface();
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }

    private function malformedActor(string $type, string $id): ActorRef
    {
        $reflection = new ReflectionClass(ActorRef::class);
        $actor = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('id')->setValue($actor, $id);
        $reflection->getProperty('type')->setValue($actor, $type);

        return $actor;
    }

    private function malformedTenant(string $type, string $id): TenantRef
    {
        $reflection = new ReflectionClass(TenantRef::class);
        $tenant = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('type')->setValue($tenant, $type);
        $reflection->getProperty('id')->setValue($tenant, $id);

        return $tenant;
    }

    private function malformedTypedReference(string $type, string $id): AuditTypedReference
    {
        $reflection = new ReflectionClass(AuditTypedReference::class);
        $reference = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('type')->setValue($reference, $type);
        $reflection->getProperty('id')->setValue($reference, $id);

        return $reference;
    }

    private function malformedDeriver(mixed $key): HmacAuditOpaqueIdDeriver
    {
        $reflection = new ReflectionClass(HmacAuditOpaqueIdDeriver::class);
        $deriver = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('key')->setValue($deriver, new \SensitiveParameterValue($key));

        return $deriver;
    }
}

final class OperationReadTestWriter implements AuditReadEventWriter
{
    /** @var list<array{0:\BlackOps\Core\Audit\AuditReadRequestBasis,1:AuditRecordDraft}> */
    public array $requested = [];

    /** @var list<array{0:AuditReadAuthorizationDecision,1:AuditRecordDraft}> */
    public array $terminals = [];

    public int $releaseCount = 0;

    public ?AuditDeliveryException $requestedFailure = null;

    public ?AuditDeliveryException $terminalFailure = null;

    public ?\Throwable $requestedThrowable = null;

    public ?\Throwable $terminalThrowable = null;

    public ?AuditReadAuthorizationDecision $terminalDecision = null;

    public function requested(
        #[\SensitiveParameter]
        \BlackOps\Core\Audit\AuditReadRequestBasis $basis,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): void {
        if ($this->requestedFailure !== null) {
            throw $this->requestedFailure;
        }
        if ($this->requestedThrowable !== null) {
            throw $this->requestedThrowable;
        }
        $this->requested[] = [$basis, $draft];
    }

    public function terminal(
        #[\SensitiveParameter]
        AuditReadContext $context,
        #[\SensitiveParameter]
        AuditRecordDraft $draft,
    ): AuditReadAuthorizationDecision {
        $this->releaseCount++;
        if ($this->terminalFailure !== null) {
            throw $this->terminalFailure;
        }
        if ($this->terminalThrowable !== null) {
            throw $this->terminalThrowable;
        }
        $decision = $context->authorizationDecision;
        $this->terminals[] = [$decision, $draft];

        return $this->terminalDecision ?? $decision;
    }
}

final class OperationReadTestNonceSource implements OperationReadNonceSource
{
    public int $calls = 0;

    public ?\Throwable $failure = null;

    public string $bytes = "\x00\x11\x22\x33\x44\x55\x66\x77\x88\x99\xaa\xbb\xcc\xdd\xee\xff";

    public function generate(): string
    {
        $this->calls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->bytes;
    }
}

final class OperationReadTestExecutionSource implements OperationReadExecutionSource
{
    public int $calls = 0;

    public ?\Throwable $failure = null;

    public bool $returnTypeError = false;

    public AuditTypedReference $reference;

    public function __construct()
    {
        $this->reference = new AuditTypedReference('process', 'blackops-cli');
    }

    public function current(): AuditTypedReference
    {
        $this->calls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->returnTypeError) {
            throw new \TypeError('execution source returned an invalid reference');
        }

        return $this->reference;
    }
}

final class OperationReadTestClock implements ClockInterface
{
    public int $calls = 0;

    public ?\Throwable $failure = null;

    public \DateTimeImmutable $value;

    public function __construct()
    {
        $this->value = new \DateTimeImmutable('2026-09-05T02:00:00.123456Z');
    }

    public function now(): \DateTimeImmutable
    {
        $this->calls++;
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->value;
    }
}
