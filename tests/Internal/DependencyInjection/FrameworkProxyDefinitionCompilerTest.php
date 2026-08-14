<?php

declare(strict_types=1);

namespace BlackOps\Tests\Internal\DependencyInjection;

require_once __DIR__ . '/../../Fixtures/DependencyInjection/FrameworkProxy/FrameworkProxyDefinitionFixtures.php';
require_once __DIR__ . '/../../Fixtures/DependencyInjection/FrameworkProxy/GlobalFrameworkProxyFixture.php';

use BlackOps\Internal\Aop\FrameworkProxyContract\FrameworkProxyDiagnosticCode;
use BlackOps\Internal\Aop\FrameworkProxyDefinition\FrameworkProxyDefinitionException;
use BlackOps\Internal\DependencyInjection\FrameworkProxyDefinitionCompiler;
use BlackOps\Tests\Fixtures\DependencyInjection\FrameworkProxy\FrameworkProxyDefinitionDependency;
use BlackOps\Tests\Fixtures\DependencyInjection\FrameworkProxy\PlainFrameworkService;
use BlackOps\Tests\Fixtures\DependencyInjection\FrameworkProxy\PreservedFrameworkService;
use BlackOps\Tests\Fixtures\DependencyInjection\FrameworkProxy\SyntheticFrameworkService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class FrameworkProxyDefinitionCompilerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/blackops-framework-definition-' . bin2hex(random_bytes(5));
        mkdir($this->root, 0o755, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testPreservesDefinitionAndAliasIdentityAndState(): void
    {
        $builder = new ContainerBuilder();
        $definition = new Definition(PreservedFrameworkService::class)
            ->setArguments([new Reference('dependency')])
            ->setBindings(['$dependency' => new Reference('dependency')])
            ->setProperties(['configured' => 'before'])
            ->setPublic(false)
            ->setShared(false)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->addTag('framework.test', ['level' => 'one'])
            ->setInstanceofConditionals(['base' => new ChildDefinition('base')->addTag('framework.instanceof')])
            ->setMethodCalls([['configure', []], ['returnsClone', [], true]])
            ->setConfigurator([PreservedFrameworkService::class, 'configureService'])
            ->setFile(__FILE__)
            ->setDeprecated('blackops', '21.4', 'Use "%service_id%" another service.');
        $builder->setDefinition('preserved', $definition);
        $builder->setDefinition('dependency', new Definition(FrameworkProxyDefinitionDependency::class));
        $alias = $builder
            ->setAlias('legacy', 'preserved')
            ->setPublic(true)
            ->setDeprecated('blackops', '21.4', 'Use "%alias_id%" instead.');

        $before = $this->state($definition);
        $beforeChanges = $definition->getChanges();
        $compiler = new FrameworkProxyDefinitionCompiler();
        $result = $compiler->compile($builder, 'build-preserved', $this->root);

        self::assertSame($definition, $builder->getDefinition('preserved'));
        self::assertSame($alias, $builder->getAlias('legacy'));
        self::assertTrue($builder->getAlias('legacy')->isPublic());
        self::assertSame(
            ['package' => 'blackops', 'version' => '21.4', 'message' => 'Use "legacy" instead.'],
            $builder->getAlias('legacy')->getDeprecation('legacy'),
        );
        self::assertNotSame(PreservedFrameworkService::class, $definition->getClass());
        self::assertSame($beforeChanges + ['class' => true], $definition->getChanges());
        $after = $this->state($definition);
        unset($after['class'], $before['class']);
        self::assertSame($before, $after);
        self::assertSame($before['arguments'], $this->state($definition)['arguments']);
        self::assertSame($before['bindings'], $this->state($definition)['bindings']);
        self::assertSame($result->binding('preserved'), $compiler->binding($definition));
        self::assertSame(PreservedFrameworkService::class, $result->binding('preserved')?->sourceClass);
        self::assertSame($definition->getClass(), $result->binding('preserved')?->proxyClass);
        self::assertArrayHasKey('class', $definition->getChanges());
    }

    public function testGeneratedDefinitionAndAliasResolveToOneSharedInstance(): void
    {
        $builder = new ContainerBuilder();
        $definition = new Definition(PreservedFrameworkService::class)
            ->setArguments([new Reference('dependency')])
            ->setProperties(['configured' => 'before'])
            ->setPublic(true)
            ->setShared(true)
            ->setMethodCalls([['configure', []], ['returnsClone', [], true]])
            ->setConfigurator([PreservedFrameworkService::class, 'configureService'])
            ->setFile(__FILE__);
        $builder->setDefinition('preserved', $definition);
        $builder->setDefinition('dependency', new Definition(FrameworkProxyDefinitionDependency::class));
        $builder->setAlias('legacy', 'preserved')->setPublic(true);
        $result = new FrameworkProxyDefinitionCompiler()->compile($builder, 'build-runtime', $this->root);
        $builder->compile();

        $service = $builder->get('preserved');
        self::assertSame($service, $builder->get('legacy'));
        self::assertInstanceOf(PreservedFrameworkService::class, $service);
        self::assertSame($result->binding('preserved')?->proxyClass, $service::class);
        self::assertSame('before->configure->configurator', $service->configured);
    }

    #[DataProvider('unsupportedDefinitions')]
    public function testUnsupportedDefinitionFeaturesFailBeforeMutation(string $feature, string $code): void
    {
        $definition = new Definition(PreservedFrameworkService::class);
        match ($feature) {
            'factory' => $definition->setFactory(['sentinel.constructor', 'sentinelFactory']),
            'lazy' => $definition->setLazy(true),
            'synthetic' => $definition->setSynthetic(true),
            'abstract' => $definition->setAbstract(true),
            'decoration' => $definition->setDecoratedService('inner.service'),
        };
        $builder = new ContainerBuilder();
        $builder->setDefinition('service', $definition);
        $before = $definition->getClass();

        try {
            new FrameworkProxyDefinitionCompiler()->compile($builder, 'build-unsupported-' . $feature, $this->root);
            self::fail('Expected a definition diagnostic.');
        } catch (FrameworkProxyDefinitionException $exception) {
            self::assertSame($code, $exception->diagnostic->code);
            self::assertStringNotContainsString('sentinel', $exception->getMessage());
            self::assertStringNotContainsString(
                'sentinel',
                (string) json_encode($exception->diagnostic->toArray(), JSON_THROW_ON_ERROR),
            );
        }
        self::assertSame($before, $definition->getClass());
    }

    public function testUnsupportedTargetLeavesEarlierSupportedDefinitionsUnchanged(): void
    {
        $builder = new ContainerBuilder();
        $supported = new Definition(PreservedFrameworkService::class);
        $unsupported = new Definition(PreservedFrameworkService::class)->setLazy(true);
        $builder->setDefinition('a-supported', $supported);
        $builder->setDefinition('z-unsupported', $unsupported);

        try {
            new FrameworkProxyDefinitionCompiler()->compile($builder, 'build-no-partial-mutation', $this->root);
            self::fail('Expected a definition diagnostic.');
        } catch (FrameworkProxyDefinitionException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::DEFINITION_LAZY, $exception->diagnostic->code);
        }
        self::assertSame(PreservedFrameworkService::class, $supported->getClass());
        self::assertSame(PreservedFrameworkService::class, $unsupported->getClass());
    }

    /** @return array<string,array{string,string}> */
    public static function unsupportedDefinitions(): array
    {
        return [
            'factory' => ['factory', FrameworkProxyDiagnosticCode::DEFINITION_FACTORY],
            'lazy' => ['lazy', FrameworkProxyDiagnosticCode::DEFINITION_LAZY],
            'synthetic' => ['synthetic', FrameworkProxyDiagnosticCode::DEFINITION_SYNTHETIC],
            'abstract' => ['abstract', FrameworkProxyDiagnosticCode::DEFINITION_ABSTRACT],
            'decoration' => ['decoration', FrameworkProxyDiagnosticCode::DEFINITION_DECORATION],
        ];
    }

    public function testSyntheticDefinitionWithoutTargetIsSkipped(): void
    {
        $builder = new ContainerBuilder();
        $definition = new Definition(PlainFrameworkService::class)->setSynthetic(true);
        $builder->setDefinition('plain', $definition);

        $result = new FrameworkProxyDefinitionCompiler()->compile($builder, 'build-synthetic-skip', $this->root);

        self::assertSame([], $result->bindings);
        self::assertSame(PlainFrameworkService::class, $definition->getClass());
    }

    public function testSyntheticDefinitionWithTargetHasStableDiagnostic(): void
    {
        $builder = new ContainerBuilder();
        $definition = new Definition(SyntheticFrameworkService::class)->setSynthetic(true);
        $builder->setDefinition('synthetic', $definition);

        try {
            new FrameworkProxyDefinitionCompiler()->compile($builder, 'build-synthetic-fail', $this->root);
            self::fail('Expected a synthetic definition diagnostic.');
        } catch (FrameworkProxyDefinitionException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::DEFINITION_SYNTHETIC, $exception->diagnostic->code);
        }
        self::assertSame(SyntheticFrameworkService::class, $definition->getClass());
    }

    public function testGlobalGeneratedPrefixCannotEnterFrameworkMode(): void
    {
        $builder = new ContainerBuilder();
        $definition = new Definition('__BlackOpsProxy_FrameworkOwned');
        $builder->setDefinition('generated', $definition);

        try {
            new FrameworkProxyDefinitionCompiler()->compile($builder, 'build-global-dual-mode', $this->root);
            self::fail('Expected a mode conflict diagnostic.');
        } catch (FrameworkProxyDefinitionException $exception) {
            self::assertSame(FrameworkProxyDiagnosticCode::MODE_CONFLICT, $exception->diagnostic->code);
        }
        self::assertSame('__BlackOpsProxy_FrameworkOwned', $definition->getClass());
    }

    /** @return array<string,mixed> */
    private function state(Definition $definition): array
    {
        return [
            'class' => $definition->getClass(),
            'arguments' => $definition->getArguments(),
            'bindings' => $definition->getBindings(),
            'properties' => $definition->getProperties(),
            'public' => $definition->isPublic(),
            'shared' => $definition->isShared(),
            'autowired' => $definition->isAutowired(),
            'tags' => $definition->getTags(),
            'autoconfigured' => $definition->isAutoconfigured(),
            'instanceof' => $definition->getInstanceofConditionals(),
            'calls' => $definition->getMethodCalls(),
            'configurator' => $definition->getConfigurator(),
            'file' => $definition->getFile(),
            'deprecated' => $definition->isDeprecated() ? $definition->getDeprecation('preserved') : null,
        ];
    }

    private function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $child = $path . '/' . $entry;
            is_dir($child) ? $this->remove($child) : unlink($child);
        }
        rmdir($path);
    }
}
