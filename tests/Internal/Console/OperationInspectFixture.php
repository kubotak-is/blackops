<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Console;

use BlackOps\Internal\Diagnostics\DiagnosticsAttempt;
use BlackOps\Internal\Diagnostics\DiagnosticsAvailability;
use BlackOps\Internal\Diagnostics\DiagnosticsAvailabilitySet;
use BlackOps\Internal\Diagnostics\DiagnosticsIdentity;
use BlackOps\Internal\Diagnostics\DiagnosticsOutcome;
use BlackOps\Internal\Diagnostics\DiagnosticsSafeActor;
use BlackOps\Internal\Diagnostics\DiagnosticsSafeActorContext;
use BlackOps\Internal\Diagnostics\DiagnosticsState;
use BlackOps\Internal\Diagnostics\DiagnosticsTimelineEntry;
use BlackOps\Internal\Diagnostics\OperationDiagnostics;
use BlackOps\Journal\LifecycleState;

final readonly class OperationInspectFixture
{
    public const ATTEMPT_ID = '01980d2e-7001-7000-8000-000000000002';
    public const OPERATION_ID = '01980d2e-7000-7000-8000-000000000001';

    public static function diagnostics(): OperationDiagnostics
    {
        return new OperationDiagnostics(
            new DiagnosticsIdentity(
                self::OPERATION_ID,
                'report.generate',
                1,
                'deferred',
                '01980d2e-7002-7000-8000-000000000003',
                null,
                new DiagnosticsSafeActorContext(
                    new DiagnosticsSafeActor('user'),
                    new DiagnosticsSafeActor('user'),
                    new DiagnosticsSafeActor('worker'),
                ),
            ),
            new DiagnosticsState(LifecycleState::Completed, true, 'transport'),
            new DiagnosticsAvailabilitySet(
                DiagnosticsAvailability::Purged,
                DiagnosticsAvailability::Available,
                DiagnosticsAvailability::Available,
                DiagnosticsAvailability::NotApplicable,
            ),
            [
                new DiagnosticsTimelineEntry(1, 'operation.received', '2026-07-18T12:00:00.123456Z', null, null, [
                    'recipientEmail' => '[masked]',
                ]),
                new DiagnosticsTimelineEntry(
                    2,
                    'attempt.started',
                    '2026-07-18T12:00:01.123456Z',
                    self::ATTEMPT_ID,
                    1,
                    [],
                ),
            ],
            [
                new DiagnosticsAttempt(self::ATTEMPT_ID, 1, '2026-07-18T12:00:01.123456Z', [2]),
            ],
            new DiagnosticsOutcome('report.generated', '2026-07-18T12:00:02.123456Z', 'outcome_store', [
                'status' => 'ready',
            ]),
        );
    }

    public static function diagnosticsWithControlCharacters(): OperationDiagnostics
    {
        $diagnostics = self::diagnostics();

        return new OperationDiagnostics(
            new DiagnosticsIdentity(
                $diagnostics->identity->operationId,
                "report\nState\n  Current: forged\x1b[31m",
                $diagnostics->identity->schemaVersion,
                "deferred\r\nOutcome",
                $diagnostics->identity->correlationId,
                "cause\t\"quoted\"\\path",
                new DiagnosticsSafeActorContext(
                    new DiagnosticsSafeActor("user\nTimeline\x1b[2J\x7f\u{0085}"),
                    new DiagnosticsSafeActor('authorization'),
                    new DiagnosticsSafeActor('worker'),
                ),
            ),
            new DiagnosticsState($diagnostics->state->current, $diagnostics->state->terminal, "transport\nActors"),
            $diagnostics->availability,
            $diagnostics->timeline,
            $diagnostics->attempts,
            new DiagnosticsOutcome(
                "report.generated\nOperation\x1b[0m",
                $diagnostics->outcome?->completedAt,
                "outcome_store\r\nState",
                ['status' => 'ready'],
            ),
        );
    }
}
