<?php

declare(strict_types=1);

namespace BlackOps\Tests\OperationData;

use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\PublicApi;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\TenantRef;
use BlackOps\OperationData\DenyOperationDataReadAuthorizer;
use BlackOps\OperationData\Exception\OperationJournalQueryException;
use BlackOps\OperationData\Exception\OperationOutcomeQueryException;
use BlackOps\OperationData\OperationDataPurpose;
use BlackOps\OperationData\OperationDataReadAuthorizationDecision;
use BlackOps\OperationData\OperationDataReadAuthorizationRequest;
use BlackOps\OperationData\OperationDataReadAuthorizer;
use BlackOps\OperationData\OperationDataResource;
use BlackOps\OperationData\OperationJournalFound;
use BlackOps\OperationData\OperationJournalQuery;
use BlackOps\OperationData\OperationJournalReadResult;
use BlackOps\OperationData\OperationJournalUnavailable;
use BlackOps\OperationData\OperationOutcomeFound;
use BlackOps\OperationData\OperationOutcomeQuery;
use BlackOps\OperationData\OperationOutcomeReadResult;
use BlackOps\OperationData\OperationOutcomeUnavailable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationDataContractTest extends TestCase
{
    private const string ID = '019f32ab-2be0-7b38-a0a7-1ab2f9687697';

    public function testPurposeGrammarAndResourceAreBounded(): void
    {
        self::assertSame('application.view', OperationDataPurpose::fromString('application.view')->code());
        self::assertSame('canonical_journal', OperationDataResource::CanonicalJournal->value);
        $this->expectException(InvalidArgumentException::class);
        OperationDataPurpose::fromString(str_repeat('a', 129));
    }

    public function testAuthorizationRequestContainsOnlySafeSubject(): void
    {
        $request = new OperationDataReadAuthorizationRequest(
            OperationDataResource::Outcome,
            OperationDataPurpose::fromString('application.view'),
            OperationId::fromString(self::ID),
            'report.generate',
            new ActorRef('current', 'user'),
            new TenantRef('customer', 'tenant-a'),
            new ActorRef('origin', 'service'),
            new TenantRef('customer', 'tenant-a'),
        );
        self::assertSame('tenant-a', $request->currentTenant()?->id());
        self::assertSame('tenant-a', $request->originTenant()?->id());
        self::assertTrue(OperationDataReadAuthorizationDecision::allow()->isAllowed());
    }

    public function testDefaultAuthorizerDeniesAndPublicTypesAreMarked(): void
    {
        $authorizer = new DenyOperationDataReadAuthorizer();
        $request = new OperationDataReadAuthorizationRequest(
            OperationDataResource::CanonicalJournal,
            OperationDataPurpose::fromString('application.view'),
            OperationId::fromString(self::ID),
            'report.generate',
            null,
            null,
            null,
            null,
        );
        self::assertFalse($authorizer->decide($request)->isAllowed());
        foreach ([
            OperationDataResource::class,
            OperationDataPurpose::class,
            OperationDataReadAuthorizationRequest::class,
            OperationDataReadAuthorizationDecision::class,
            OperationDataReadAuthorizer::class,
            DenyOperationDataReadAuthorizer::class,
            OperationJournalQuery::class,
            OperationJournalReadResult::class,
            OperationJournalFound::class,
            OperationJournalUnavailable::class,
            OperationOutcomeQuery::class,
            OperationOutcomeReadResult::class,
            OperationOutcomeFound::class,
            OperationOutcomeUnavailable::class,
            OperationJournalQueryException::class,
            OperationOutcomeQueryException::class,
        ] as $type) {
            self::assertCount(1, new ReflectionClass($type)->getAttributes(PublicApi::class));
        }
    }
}
