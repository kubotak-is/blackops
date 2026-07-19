<?php

declare(strict_types=1);

namespace BlackOps\Tests\Http\Binding;

use BlackOps\Core\OperationValue;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromHeader;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use BlackOps\Http\Binding\HttpParameterBinder;
use BlackOps\Http\Binding\OperationValueBinder;
use BlackOps\Http\Binding\OperationValueBindingException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class OperationValueBinderScalarCoercionTest extends TestCase
{
    private Psr17Factory $psr17;

    protected function setUp(): void
    {
        $this->psr17 = new Psr17Factory();
    }

    public function testPathQueryAndHeaderStringsDecodeBeforeConstruction(): void
    {
        $request = $this->psr17
            ->createServerRequest('POST', '/items/42')
            ->withQueryParams(['rate' => '1.25e+2', 'term' => ''])
            ->withHeader('X-Dry-Run', 'false');

        $value = new OperationValueBinder()->bind(NonBodyScalarValue::class, $request, ['id' => '42']);

        self::assertInstanceOf(NonBodyScalarValue::class, $value);
        self::assertSame(42, $value->id);
        self::assertSame(125.0, $value->rate);
        self::assertFalse($value->dryRun);
        self::assertSame('', $value->term);
    }

    #[DataProvider('invalidNonBodyValues')]
    public function testEachNonBodySourceRejectsInvalidCanonicalString(
        string $parameterName,
        array $path,
        array $query,
        ?string $header,
    ): void {
        $request = $this->psr17->createServerRequest('POST', '/items');
        $request = $request->withQueryParams($query);

        if ($header !== null) {
            $request = $request->withHeader('X-Dry-Run', $header);
        }

        $constructor = new ReflectionClass(NonBodyScalarValue::class)->getConstructor();
        self::assertNotNull($constructor);
        $parameter = $constructor->getParameters()[$parameterName === 'id' ? 0 : ($parameterName === 'rate' ? 1 : 2)];

        try {
            new HttpParameterBinder()->bind($parameter, $request, $path, $query, []);
            self::fail('Expected non-body scalar binding to fail.');
        } catch (OperationValueBindingException $exception) {
            self::assertSame($parameterName, $exception->violations()[0]->field);
            self::assertSame('binding.type', $exception->violations()[0]->code);
            self::assertStringNotContainsString('secret-wire-value', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, array<string, string>, array<string, string>, string|null}>
     */
    public static function invalidNonBodyValues(): iterable
    {
        yield 'path integer' => ['id', ['id' => '01'], [], null];
        yield 'query float' => ['rate', [], ['rate' => 'Infinity'], null];
        yield 'header boolean' => ['dryRun', [], [], 'secret-wire-value'];
    }

    public function testMissingUsesDefaultAndDoesNotTurnEmptyIntoNull(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/search')->withQueryParams(['optional' => '']);

        $value = new OperationValueBinder()->bind(OptionalQueryValue::class, $request);

        self::assertInstanceOf(OptionalQueryValue::class, $value);
        self::assertNull($value->page);
        self::assertSame('', $value->optional);
        self::assertSame(25, $value->limit);
    }

    public function testEmptyNullableIntegerIsTypeFailureRatherThanNull(): void
    {
        $request = $this->psr17->createServerRequest('GET', '/search')->withQueryParams(['page' => '']);

        $this->expectException(OperationValueBindingException::class);

        new OperationValueBinder()->bind(OptionalQueryValue::class, $request);
    }

    public function testNativeBodyScalarsKeepTheirDecodedTypes(): void
    {
        $request = $this->jsonRequest('{"quantity":42,"rate":1.5,"enabled":false}');

        $value = new OperationValueBinder()->bind(BodyScalarValue::class, $request);

        self::assertInstanceOf(BodyScalarValue::class, $value);
        self::assertSame(42, $value->quantity);
        self::assertSame(1.5, $value->rate);
        self::assertFalse($value->enabled);
    }

    #[DataProvider('bodyStringScalars')]
    public function testBodyStringsAreNotCoercedToOtherScalarTypes(string $body): void
    {
        $this->expectException(OperationValueBindingException::class);

        new OperationValueBinder()->bind(BodyScalarValue::class, $this->jsonRequest($body));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function bodyStringScalars(): iterable
    {
        yield 'integer string' => ['{"quantity":"42","rate":1.5,"enabled":false}'];
        yield 'float string' => ['{"quantity":42,"rate":"1.5","enabled":false}'];
        yield 'boolean string' => ['{"quantity":42,"rate":1.5,"enabled":"false"}'];
    }

    private function jsonRequest(string $body): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->psr17->createServerRequest('POST', '/items')->withBody($this->psr17->createStream($body));
    }
}

final readonly class NonBodyScalarValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public int $id,
        #[FromQuery]
        public float $rate,
        #[FromHeader('X-Dry-Run')]
        public bool $dryRun,
        #[FromQuery]
        public string $term,
    ) {}
}

final readonly class OptionalQueryValue implements OperationValue
{
    public function __construct(
        #[FromQuery]
        public ?int $page = null,
        #[FromQuery]
        public ?string $optional = null,
        #[FromQuery]
        public int $limit = 25,
    ) {}
}

final readonly class BodyScalarValue implements OperationValue
{
    public function __construct(
        #[FromBody]
        public int $quantity,
        #[FromBody]
        public float $rate,
        #[FromBody]
        public bool $enabled,
    ) {}
}
