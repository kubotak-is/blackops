<?php

declare(strict_types=1);

namespace BlackOps\Tests\Console\Observability;

use BlackOps\Console\Observability\OperationalHealthCliAdapter;
use BlackOps\Console\Observability\OperationalHealthCliFormatter;
use BlackOps\Internal\Observability\DefaultOperationalHealthQuery;
use BlackOps\Observability\OperationalHealthKind;
use BlackOps\Observability\OperationalHealthQuery;
use BlackOps\Observability\OperationalHealthReport;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OperationalHealthCliFormatterTest extends TestCase
{
    public function testExplicitAdapterProvidesHumanAndOneLineJsonOutputAndExitCode(): void
    {
        $adapter = new OperationalHealthCliAdapter(
            new DefaultOperationalHealthQuery(),
            new OperationalHealthCliFormatter(),
        );

        $human = $adapter->run(OperationalHealthKind::Liveness);
        $json = $adapter->run(OperationalHealthKind::Liveness, json: true);

        self::assertSame(0, $human['exitCode']);
        self::assertStringContainsString("kind: liveness\n", $human['output']);
        self::assertSame("\n", substr($json['output'], -1));
        self::assertSame(0, substr_count(trim($json['output']), needle: "\n"));
        self::assertSame(
            'liveness',
            json_decode($json['output'], associative: true, flags: JSON_THROW_ON_ERROR)['kind'],
        );
    }

    public function testThrowingQueryProducesSafeFailureAndExitCodeOne(): void
    {
        $query = new class implements OperationalHealthQuery {
            public function check(OperationalHealthKind $kind): OperationalHealthReport
            {
                throw new RuntimeException('dsn=password-secret');
            }
        };

        $result = new OperationalHealthCliAdapter($query)->run(OperationalHealthKind::Readiness, json: true);
        $payload = json_decode($result['output'], associative: true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(1, $result['exitCode']);
        self::assertSame('fail', $payload['status']);
        self::assertSame([['code' => 'query_failed', 'status' => 'fail']], $payload['checks']);
        self::assertStringNotContainsString('password-secret', $result['output']);
    }
}
