<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\Audit\Authentication;

use BlackOps\Core\ActorRef;
use BlackOps\Core\TenantRef;
use BlackOps\Http\Authentication\AuthenticationMiddleware;
use BlackOps\Http\Authentication\AuthenticationResult;
use BlackOps\Http\Authentication\HttpAuthenticator;
use BlackOps\Http\Responder\JsonOperationResponder;
use BlackOps\Internal\Application\ApplicationDatabaseConnectionLifecycle;
use BlackOps\Internal\Application\ApplicationHttpRequestHandler;
use BlackOps\Internal\Application\ApplicationOperationInvocationLifecycle;
use BlackOps\Internal\Audit\Authentication\AuditedHttpAuthenticator;
use BlackOps\Internal\Audit\Authentication\AuthenticationAuditEventWriter;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryException;
use BlackOps\Internal\Audit\Orchestration\AuditDeliveryFailure;
use BlackOps\Internal\Database\DoctrineDatabaseManager;
use BlackOps\Internal\Execution\ExecutionScopeProvider;
use BlackOps\Internal\Logging\ExecutionScopedLogger;
use Doctrine\DBAL\Connection;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use ReflectionClass;
use RuntimeException;
use Stringable;
use Throwable;

final class AuditedHttpAuthenticatorTest extends TestCase
{
    private const string CREDENTIAL_CANARY = 'http-authentication-credential-canary';

    public function testAnonymousReturnsTheOriginalResultWithoutCallingTheWriter(): void
    {
        $connection = $this->connection();
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::anonymous());
        $writer = new AuthenticationDecoratorWriter();
        $result = new AuditedHttpAuthenticator($delegate, $writer, $connection)->authenticate($this->request());

        self::assertSame($delegate->result, $result);
        self::assertSame(1, $delegate->calls);
        self::assertSame(0, $writer->calls);
    }

    public function testAnonymousPostDelegateTransactionFailureIsDeniedBeforeWriterWork(): void
    {
        $active = false;
        $delegate =
            new AuthenticationDecoratorDelegate(AuthenticationResult::anonymous(), after: static function () use (
                &$active,
            ): void {
                $active = true;
            });
        $writer = new AuthenticationDecoratorWriter();

        try {
            new AuditedHttpAuthenticator($delegate, $writer, $this->connection($active))->authenticate(
                $this->request(),
            );
            self::fail('anonymous transaction leak was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
        }
        self::assertSame(0, $writer->calls);
    }

    public function testAuthenticatedResultIsReleasedOnlyAfterOneWriterCall(): void
    {
        $events = [];
        $delegate = new AuthenticationDecoratorDelegate(
            AuthenticationResult::authenticated(new ActorRef('actor-1', 'user'), new TenantRef('account', 'tenant-a')),
            after: static function () use (&$events): void {
                $events[] = 'delegate';
            },
        );
        $writer = new AuthenticationDecoratorWriter(static function () use (&$events): void {
            $events[] = 'writer';
        });
        $result = new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate($this->request());

        self::assertSame($delegate->result, $result);
        self::assertSame(['delegate', 'writer'], $events);
        self::assertSame(1, $writer->calls);
        self::assertSame('actor-1', $result->actor()?->id());
    }

    public function testInvalidWriterFailureReturnsTheOriginalInvalidResultAndDoesNotRetry(): void
    {
        $result = AuthenticationResult::invalid('authentication.invalid');
        $delegate = new AuthenticationDecoratorDelegate($result);
        $writer = new AuthenticationDecoratorWriter(failure: new RuntimeException(self::CREDENTIAL_CANARY));
        $returned = new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate(
            $this->request(),
        );

        self::assertSame($result, $returned);
        self::assertTrue($returned->isInvalid());
        self::assertSame(1, $writer->calls);
    }

    public function testInvalidPostDelegateTransactionLeakReturnsInvalidWithoutWriterWork(): void
    {
        $active = false;
        $connection = $this->connection($active);
        $delegate = new AuthenticationDecoratorDelegate(
            AuthenticationResult::invalid('authentication.invalid'),
            after: static function () use (&$active): void {
                $active = true;
            },
        );
        $writer = new AuthenticationDecoratorWriter();
        $returned = new AuditedHttpAuthenticator($delegate, $writer, $connection)->authenticate($this->request());

        self::assertSame($delegate->result, $returned);
        self::assertSame(0, $writer->calls);
    }

    public function testInvalidWriterTransactionLeakReturnsInvalidForTheOuterLifecycleToClose(): void
    {
        $active = false;
        $connection = $this->connection($active);
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::invalid('authentication.invalid'));
        $writer = new AuthenticationDecoratorWriter(static function () use (&$active): void {
            $active = true;
        });
        $returned = new AuditedHttpAuthenticator($delegate, $writer, $connection)->authenticate($this->request());

        self::assertSame($delegate->result, $returned);
        self::assertSame(1, $writer->calls);
        self::assertTrue($active);
    }

    public function testAuthenticatedPostDelegateTransactionFailureWithholdsResult(): void
    {
        $active = false;
        $delegate = new AuthenticationDecoratorDelegate(
            AuthenticationResult::authenticated(new ActorRef('actor-1', 'user')),
            after: static function () use (&$active): void {
                $active = true;
            },
        );
        $writer = new AuthenticationDecoratorWriter();

        try {
            new AuditedHttpAuthenticator($delegate, $writer, $this->connection($active))->authenticate(
                $this->request(),
            );
            self::fail('authenticated transaction leak was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
        }
        self::assertSame(0, $writer->calls);
    }

    public function testAuthenticatedPostWriterTransactionFailureWithholdsResult(): void
    {
        $active = false;
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
            'actor-1',
            'user',
        )));
        $writer = new AuthenticationDecoratorWriter(static function () use (&$active): void {
            $active = true;
        });

        try {
            new AuditedHttpAuthenticator($delegate, $writer, $this->connection($active))->authenticate(
                $this->request(),
            );
            self::fail('writer transaction leak was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
        }
        self::assertSame(1, $writer->calls);
    }

    public function testAuthenticatedWriterFailureIsFreshAndWithholdsTheResult(): void
    {
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
            'actor-1',
            'user',
        )));
        $writer = new AuthenticationDecoratorWriter(failure: new RuntimeException(self::CREDENTIAL_CANARY));
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate($this->request());
                self::fail('authenticated result was released after writer failure');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
                self::assertSame('Audit delivery failed.', $failure->getMessage());
                self::assertSame(0, $failure->getCode());
                self::assertNull($failure->getPrevious());
                self::assertStringNotContainsString(self::CREDENTIAL_CANARY, $failure->getTraceAsString());
                self::assertStringNotContainsString(self::CREDENTIAL_CANARY, var_export($failure->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
        self::assertSame(1, $delegate->calls);
        self::assertSame(1, $writer->calls);
    }

    public function testKnownDeliveryFailureIsRemappedFreshWithoutItsLegacyTrace(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $original = null;
            $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
                'actor-1',
                'user',
            )));
            $writer = new AuthenticationDecoratorWriter(failureFactory: static function (string $canary) use (
                &$original,
            ): AuditDeliveryException {
                return $original = self::deliveryFailureWithCanary($canary, AuditDeliveryFailure::ResourceUnavailable);
            }, canary: self::CREDENTIAL_CANARY);

            try {
                new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate($this->request());
                self::fail('known delivery failure was accepted');
            } catch (AuditDeliveryException $mapped) {
                self::assertInstanceOf(AuditDeliveryException::class, $original);
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $original->failure());
                self::assertNull($original->getPrevious());
                $originalTrace = var_export($original->getTrace(), true);
                self::assertStringContainsString(self::CREDENTIAL_CANARY, $originalTrace);
                self::assertStringContainsString('deliveryFailureWithCanary', $originalTrace);
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $mapped->failure());
                self::assertNotSame($original, $mapped);
                self::assertNull($mapped->getPrevious());
                $mappedTrace = var_export($mapped->getTrace(), true);
                self::assertStringNotContainsString(self::CREDENTIAL_CANARY, $mappedTrace);
                self::assertStringNotContainsString('deliveryFailureWithCanary', $mappedTrace);
                self::assertStringNotContainsString(AuditDeliveryException::class, $mappedTrace);
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testEntryTransactionFailurePreventsDelegateAndWriter(): void
    {
        $connection = $this->entryConnection();
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
            'actor-1',
            'user',
        )));
        $writer = new AuthenticationDecoratorWriter();
        try {
            new AuditedHttpAuthenticator($delegate, $writer, $connection)->authenticate($this->request());
            self::fail('ambient transaction was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $delegate->calls);
        self::assertSame(0, $writer->calls);
    }

    public function testEntryNestingFailurePreventsDelegateAndWriter(): void
    {
        $active = false;
        $nesting = 1;
        $closed = 0;
        $connection = $this->connectionWithState($active, $nesting, $closed, true);
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
            'actor-1',
            'user',
        )));
        $writer = new AuthenticationDecoratorWriter();
        try {
            new AuditedHttpAuthenticator($delegate, $writer, $connection)->authenticate($this->request());
            self::fail('ambient nesting was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::TransactionInvalid, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
        self::assertSame(0, $delegate->calls);
        self::assertSame(0, $writer->calls);
    }

    public function testDelegateFailureIsFreshAndDoesNotRetainRequestCredential(): void
    {
        $delegate = new AuthenticationDecoratorDelegate(null, failure: new RuntimeException(self::CREDENTIAL_CANARY));
        $writer = new AuthenticationDecoratorWriter();
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            try {
                new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate($this->request());
                self::fail('delegate failure was accepted');
            } catch (AuditDeliveryException $failure) {
                self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
                self::assertSame('Audit delivery failed.', $failure->getMessage());
                self::assertNull($failure->getPrevious());
                self::assertStringNotContainsString(self::CREDENTIAL_CANARY, $failure->getTraceAsString());
                self::assertStringNotContainsString(self::CREDENTIAL_CANARY, var_export($failure->getTrace(), true));
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
        self::assertSame(1, $delegate->calls);
        self::assertSame(0, $writer->calls);
    }

    public function testActualMiddlewareCallerCanStillRetainItsUnmarkedRequestFrame(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            $delegate = new AuthenticationDecoratorDelegate(
                null,
                failure: new RuntimeException(self::CREDENTIAL_CANARY),
            );
            $adapter = new AuditedHttpAuthenticator(
                $delegate,
                new AuthenticationDecoratorWriter(),
                $this->connection(),
            );
            try {
                new AuthenticationMiddleware($adapter)->process($this->request(), new AuthenticationDecoratorHandler());
                self::fail('middleware accepted delegate failure');
            } catch (AuditDeliveryException $failure) {
                $trace = var_export($failure->getTrace(), true);
                self::assertStringContainsString('AuthenticationMiddleware', $trace);
                self::assertStringContainsString(self::CREDENTIAL_CANARY, $trace);
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testOuterApplicationBoundaryMapsAuthenticationAuditFailureToSafe500(): void
    {
        $previous = ini_get('zend.exception_ignore_args');
        self::assertNotFalse(ini_set('zend.exception_ignore_args', '0'));
        try {
            foreach ([
                [
                    new AuthenticationDecoratorDelegate(null, failure: new RuntimeException(self::CREDENTIAL_CANARY)),
                    new AuthenticationDecoratorWriter(),
                ],
                [
                    new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
                        'actor-1',
                        'user',
                    ))),
                    new AuthenticationDecoratorWriter(failure: new RuntimeException(self::CREDENTIAL_CANARY)),
                ],
            ] as [$delegate, $writer]) {
                $connection = $this->connection();
                $middleware = new AuthenticationMiddleware(new AuditedHttpAuthenticator(
                    $delegate,
                    $writer,
                    $connection,
                ));
                $terminal = new AuthenticationDecoratorHandler();
                [$handler, $logger] = $this->outerHandler(
                    new AuthenticationDecoratorMiddlewareHandler($middleware, $terminal),
                    $connection,
                );

                $response = $handler->handle($this->request());

                $this->assertSafe500($response, $logger, AuditDeliveryException::class);
                self::assertSame(0, $terminal->calls);
            }
        } finally {
            if ($previous !== false) {
                ini_set('zend.exception_ignore_args', $previous);
            }
        }
    }

    public function testOuterApplicationBoundaryClosesInvalidLeakAndRecoversNextRequest(): void
    {
        $active = false;
        $closed = 0;
        $connection = $this->connection($active, $closed);
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::invalid('authentication.invalid'));
        $writer = new AuthenticationDecoratorWriter();
        $writer->after = static function () use (&$active, &$writer): void {
            if ($writer->calls === 1) {
                $active = true;
            }
        };
        $middleware = new AuthenticationMiddleware(new AuditedHttpAuthenticator($delegate, $writer, $connection));
        $terminal = new AuthenticationDecoratorHandler();
        [$handler, $logger] = $this->outerHandler(
            new AuthenticationDecoratorMiddlewareHandler($middleware, $terminal),
            $connection,
        );

        $first = $handler->handle($this->request());
        $second = $handler->handle($this->request());

        self::assertSame(500, $first->getStatusCode());
        self::assertSame(401, $second->getStatusCode());
        self::assertSame(2, $delegate->calls);
        self::assertSame(2, $writer->calls);
        self::assertSame(1, $closed);
        self::assertSame(0, $terminal->calls);
        self::assertCount(1, $logger->records);
        self::assertSame(\LogicException::class, $logger->records[0]['context']['context']['failure']['type']);
        self::assertStringNotContainsString(self::CREDENTIAL_CANARY, serialize($logger->records));
    }

    public function testSnapshotsAndUnserializationAreValueFree(): void
    {
        $adapter = new AuditedHttpAuthenticator(
            new AuthenticationDecoratorDelegate(AuthenticationResult::anonymous(), canary: self::CREDENTIAL_CANARY),
            new AuthenticationDecoratorWriter(canary: self::CREDENTIAL_CANARY),
            $this->connection(),
        );

        self::assertSame([], $adapter->__debugInfo());
        self::assertSame([], $adapter->__serialize());
        $surface =
            print_r($adapter, true)
            . var_export($adapter, true)
            . serialize($adapter)
            . json_encode($adapter, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(self::CREDENTIAL_CANARY, $surface);
        try {
            unserialize(serialize($adapter), ['allowed_classes' => true]);
            self::fail('adapter unserialization was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
            self::assertNull($failure->getPrevious());
        }
    }

    public function testMalformedResultIsRejectedWithoutWriterCall(): void
    {
        $reflection = new ReflectionClass(AuthenticationResult::class);
        $malformed = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('state')->setValue($malformed, 99);
        $reflection->getProperty('actor')->setValue($malformed, null);
        $reflection->getProperty('tenant')->setValue($malformed, null);
        $reflection->getProperty('code')->setValue($malformed, null);
        $delegate = new AuthenticationDecoratorDelegate($malformed);
        $writer = new AuthenticationDecoratorWriter();

        try {
            new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate($this->request());
            self::fail('malformed authentication result was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::PolicyInvalid, $failure->failure());
        }
        self::assertSame(0, $writer->calls);
    }

    public function testDelegateTypeFailureIsAResourceFailure(): void
    {
        $writer = new AuthenticationDecoratorWriter();
        try {
            new AuditedHttpAuthenticator(
                new AuthenticationDecoratorDelegate(
                    null,
                    failure: new \TypeError('delegate returned an invalid authentication result type'),
                ),
                $writer,
                $this->connection(),
            )->authenticate($this->request());
            self::fail('delegate type failure was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::ResourceUnavailable, $failure->failure());
        }
        self::assertSame(0, $writer->calls);
    }

    public function testFreshFailureReconstructionHandlesCorruptKnownFailureEnums(): void
    {
        $failure = new ReflectionClass(AuditDeliveryException::class)->newInstanceWithoutConstructor();
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::authenticated(new ActorRef(
            'actor-1',
            'user',
        )));
        $writer = new AuthenticationDecoratorWriter(failure: $failure);

        try {
            new AuditedHttpAuthenticator($delegate, $writer, $this->connection())->authenticate($this->request());
            self::fail('corrupt known failure was accepted');
        } catch (AuditDeliveryException $mapped) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $mapped->failure());
            self::assertNull($mapped->getPrevious());
            self::assertNotSame($failure, $mapped);
        }
    }

    public function testCorruptAndUninitializedSnapshotsFailClosedBeforeDelegate(): void
    {
        $delegate = new AuthenticationDecoratorDelegate(AuthenticationResult::anonymous());
        $writer = new AuthenticationDecoratorWriter();
        $adapter = new AuditedHttpAuthenticator($delegate, $writer, $this->connection());
        $reflection = new ReflectionClass($adapter);
        $reflection->getProperty('snapshot')->setValue($adapter, new \SensitiveParameterValue([
            'wrong' => self::CREDENTIAL_CANARY,
        ]));
        try {
            $adapter->authenticate($this->request());
            self::fail('corrupt adapter snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
        }
        self::assertSame(0, $delegate->calls);

        $uninitialized = $reflection->newInstanceWithoutConstructor();
        try {
            $uninitialized->authenticate($this->request());
            self::fail('uninitialized adapter snapshot was accepted');
        } catch (AuditDeliveryException $failure) {
            self::assertSame(AuditDeliveryFailure::StorageUnavailable, $failure->failure());
        }
    }

    public function testCloneAndRepeatedCallsDoNotRetainRequestState(): void
    {
        $delegate = new AuthenticationDecoratorDelegate(
            AuthenticationResult::anonymous(),
            canary: self::CREDENTIAL_CANARY,
        );
        $writer = new AuthenticationDecoratorWriter(canary: self::CREDENTIAL_CANARY);
        $adapter = new AuditedHttpAuthenticator($delegate, $writer, $this->connection());
        $clone = clone $adapter;

        self::assertSame($delegate->result, $adapter->authenticate($this->request()));
        self::assertSame($delegate->result, $clone->authenticate($this->request()));
        self::assertSame(2, $delegate->calls);
        self::assertSame(0, $writer->calls);
        self::assertSame([], $clone->__serialize());
    }

    private function request(): ServerRequestInterface
    {
        return new ServerRequest('GET', '/private', [
            'Authorization' => 'Bearer ' . self::CREDENTIAL_CANARY,
        ]);
    }

    private static function deliveryFailureWithCanary(
        string $canary,
        AuditDeliveryFailure $failure,
    ): AuditDeliveryException {
        if ($canary === '') {
            throw new RuntimeException('missing delivery failure canary');
        }

        return new AuditDeliveryException($failure);
    }

    private function connection(bool &$active = false, int &$closed = 0): Connection
    {
        $nesting = 0;

        return $this->connectionWithState($active, $nesting, $closed, true);
    }

    private function entryConnection(): Connection
    {
        $active = false;
        $nesting = 0;
        $closed = 0;

        return $this->connectionWithState($active, $nesting, $closed, false);
    }

    private function connectionWithState(bool &$active, int &$nesting, int &$closed, bool $autoCommit): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isAutoCommit')->willReturn($autoCommit);
        $connection
            ->method('isTransactionActive')
            ->willReturnCallback(function () use (&$active): bool {
                return $active;
            });
        $connection
            ->method('getTransactionNestingLevel')
            ->willReturnCallback(function () use (&$nesting): int {
                return $nesting;
            });
        $connection->method('fetchOne')->willReturn(1);
        $connection
            ->method('close')
            ->willReturnCallback(static function () use (&$active, &$closed): void {
                $active = false;
                ++$closed;
            });

        return $connection;
    }

    /** @return array{0:ApplicationHttpRequestHandler,1:AuthenticationDecoratorLogger} */
    private function outerHandler(RequestHandlerInterface $inner, Connection $connection): array
    {
        $manager = new DoctrineDatabaseManager(
            'app',
            ['app' => []],
            static fn(array $parameters): Connection => $connection,
        );
        $manager->connection();
        $scope = new ExecutionScopeProvider();
        $logger = new AuthenticationDecoratorLogger();
        $psr17 = new Psr17Factory();
        $handler = new ApplicationHttpRequestHandler(
            $inner,
            new ApplicationOperationInvocationLifecycle(
                $scope,
                new ApplicationDatabaseConnectionLifecycle($manager),
                null,
            ),
            new JsonOperationResponder($psr17, $psr17),
            new ExecutionScopedLogger($logger, $scope),
        );

        return [$handler, $logger];
    }

    private function assertSafe500(
        ResponseInterface $response,
        AuthenticationDecoratorLogger $logger,
        string $failureType,
    ): void {
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"error","code":"internal_error"}', (string) $response->getBody());
        self::assertSame(
            [
                [
                    'message' => 'Application request failed.',
                    'context' => [
                        'schemaVersion' => 1,
                        'kind' => 'framework',
                        'context' => [
                            'failure' => [
                                'classification' => 'internal_error',
                                'type' => $failureType,
                            ],
                        ],
                    ],
                ],
            ],
            $logger->records,
        );
        self::assertStringNotContainsString(self::CREDENTIAL_CANARY, (string) $response->getBody());
        self::assertStringNotContainsString(self::CREDENTIAL_CANARY, serialize($logger->records));
    }
}

final class AuthenticationDecoratorDelegate implements HttpAuthenticator
{
    public int $calls = 0;

    /** @param null|Closure():void $after */
    public function __construct(
        public readonly ?AuthenticationResult $result,
        public readonly ?Throwable $failure = null,
        public readonly ?\Closure $after = null,
        public readonly string $canary = '',
    ) {}

    public function authenticate(ServerRequestInterface $request): AuthenticationResult
    {
        ++$this->calls;
        if ($this->after !== null) {
            ($this->after)();
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if (!$this->result instanceof AuthenticationResult) {
            throw new RuntimeException('missing authentication result');
        }

        return $this->result;
    }
}

final class AuthenticationDecoratorWriter implements AuthenticationAuditEventWriter
{
    public int $calls = 0;

    /** @param null|Closure():void $after */
    public function __construct(
        public ?\Closure $after = null,
        public readonly ?Throwable $failure = null,
        public readonly string $canary = '',
        /** @var null|Closure(string):AuditDeliveryException */
        public readonly ?\Closure $failureFactory = null,
    ) {}

    public function write(#[\SensitiveParameter] AuthenticationResult $result): void
    {
        ++$this->calls;
        if ($this->after !== null) {
            ($this->after)();
        }
        if ($this->failureFactory !== null) {
            throw ($this->failureFactory)($this->canary);
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final class AuthenticationDecoratorHandler implements RequestHandlerInterface
{
    public int $calls = 0;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        ++$this->calls;

        return new Response(204);
    }
}

final class AuthenticationDecoratorMiddlewareHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly MiddlewareInterface $middleware,
        private readonly RequestHandlerInterface $handler,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->middleware->process($request, $this->handler);
    }
}

final class AuthenticationDecoratorLogger extends AbstractLogger
{
    /** @var list<array{message:string|Stringable,context:array<array-key,mixed>}> */
    public array $records = [];

    /** @param array<array-key,mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['message' => $message, 'context' => $context];
    }
}
