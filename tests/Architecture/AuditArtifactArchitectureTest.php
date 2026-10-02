<?php

declare(strict_types=1);

namespace BlackOps\Tests\Architecture;

use BlackOps\Internal\Audit\Artifact\AuditAnchorBinding;
use BlackOps\Internal\Audit\Artifact\AuditAnchorReceiptCodec;
use BlackOps\Internal\Audit\Artifact\AuditArtifactCanonical;
use BlackOps\Internal\Audit\Artifact\AuditArtifactException;
use BlackOps\Internal\Audit\Artifact\AuditArtifactFailure;
use BlackOps\Internal\Audit\Artifact\AuditArtifactIntegrity;
use BlackOps\Internal\Audit\Artifact\AuditArtifactRelation;
use BlackOps\Internal\Audit\Artifact\AuditArtifactScalar;
use BlackOps\Internal\Audit\Artifact\AuditArtifactShape;
use BlackOps\Internal\Audit\Artifact\AuditArtifactSigner;
use BlackOps\Internal\Audit\Artifact\AuditArtifactVerifier;
use BlackOps\Internal\Audit\Artifact\AuditArtifactWirePreflight;
use BlackOps\Internal\Audit\Artifact\AuditCheckpointCodec;
use BlackOps\Internal\Audit\Artifact\AuditTrustEntryRelation;
use BlackOps\Internal\Audit\Artifact\AuditTrustEntryShape;
use BlackOps\Internal\Audit\Artifact\AuditTrustLookup;
use BlackOps\Internal\Audit\Artifact\AuditTrustScalar;
use BlackOps\Internal\Audit\Artifact\AuditTrustSnapshotCodec;
use BlackOps\Internal\Audit\Artifact\AuditTrustSnapshotShape;
use PhpParser\Node;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\DeclareItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Greater;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BinaryOp\Smaller;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Eval_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\ShellExec;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Finally_;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class AuditArtifactArchitectureTest extends TestCase
{
    private const string SOURCE_ROOT = __DIR__ . '/../../src/Internal/Audit/Artifact';

    private const string ARTIFACT_NAMESPACE = 'BlackOps\\Internal\\Audit\\Artifact';

    public function testExactNineteenTypeInventoryAndCallableSurfaces(): void
    {
        $types = $this->types();
        self::assertCount(19, $types);
        self::assertSame(
            ['ArtifactInvalid', 'TrustInvalid', 'SignatureInvalid', 'SigningUnavailable'],
            array_map(static fn(\BackedEnum $case): string => $case->name, AuditArtifactFailure::cases()),
        );
        self::assertSame(
            ['artifact_invalid', 'trust_invalid', 'signature_invalid', 'signing_unavailable'],
            array_map(static fn(\BackedEnum $case): string => $case->value, AuditArtifactFailure::cases()),
        );

        $expected = $this->expectedSignatures();
        foreach ($types as $type) {
            $reflection = new ReflectionClass($type);
            self::assertSame(self::ARTIFACT_NAMESPACE, $reflection->getNamespaceName(), $type . ' namespace');
            self::assertTrue($reflection->isFinal(), $type . ' final');
            self::assertSame([], $reflection->getAttributes(), $type . ' class attributes');
            if ($type === AuditArtifactFailure::class) {
                self::assertFalse($reflection->getParentClass(), $type . ' parent');
                self::assertNull($reflection->getConstructor(), $type . ' constructor');
                $propertyNames = array_map(
                    static fn(\ReflectionProperty $property): string => $property->getName(),
                    $reflection->getProperties(),
                );
                sort($propertyNames);
                self::assertSame(['name', 'value'], $propertyNames, 'enum built-in properties only');
                continue;
            }
            if ($type === AuditArtifactException::class) {
                self::assertSame(\RuntimeException::class, $reflection->getParentClass()?->getName());
            } else {
                self::assertFalse($reflection->getParentClass(), $type . ' parent');
                self::assertNull($reflection->getConstructor(), $type . ' constructor');
                self::assertSame([], $reflection->getProperties(), $type . ' properties');
                self::assertSame([], $reflection->getReflectionConstants(), $type . ' constants');
            }

            $actualMethods = [];
            foreach ($reflection->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() === $type) {
                    $actualMethods[$method->getName()] = $method;
                }
            }
            self::assertSame(
                array_keys($expected[$type] ?? []),
                array_keys($actualMethods),
                $type . ' declared methods',
            );
            foreach ($expected[$type] ?? [] as $name => $signature) {
                /** @var ReflectionMethod $method */
                $method = $actualMethods[$name];
                self::assertSame(
                    $signature['visibility'],
                    $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected'),
                );
                self::assertSame($signature['static'], $method->isStatic(), $type . '::' . $name . ' static');
                self::assertSame([], $method->getAttributes(), $type . '::' . $name . ' attributes');
                $this->assertReflectionSignature($method, $signature, $type . '::' . $name);
            }
        }

        $exception = new ReflectionClass(AuditArtifactException::class);
        $properties = array_values(array_filter(
            $exception->getProperties(),
            static fn(\ReflectionProperty $property): bool => (
                $property->getDeclaringClass()->getName() === AuditArtifactException::class
            ),
        ));
        self::assertCount(1, $properties);
        self::assertSame('failure', $properties[0]->getName());
        self::assertTrue($properties[0]->isPrivate());
        self::assertTrue($properties[0]->isReadOnly());
        self::assertSame(AuditArtifactFailure::class, $properties[0]->getType()?->getName());
    }

    public function testSourceDependencyNativeOwnershipAndExactTimeOwners(): void
    {
        $sources = $this->sources();
        self::assertSame(
            [
                'AuditAnchorBinding.php',
                'AuditAnchorReceiptCodec.php',
                'AuditArtifactCanonical.php',
                'AuditArtifactException.php',
                'AuditArtifactFailure.php',
                'AuditArtifactIntegrity.php',
                'AuditArtifactRelation.php',
                'AuditArtifactScalar.php',
                'AuditArtifactShape.php',
                'AuditArtifactSigner.php',
                'AuditArtifactVerifier.php',
                'AuditArtifactWirePreflight.php',
                'AuditCheckpointCodec.php',
                'AuditTrustEntryRelation.php',
                'AuditTrustEntryShape.php',
                'AuditTrustLookup.php',
                'AuditTrustScalar.php',
                'AuditTrustSnapshotCodec.php',
                'AuditTrustSnapshotShape.php',
            ],
            array_keys($sources),
        );
        foreach ($sources as $file => $source) {
            $this->assertSourceConforms($file, $source, $this->typeForFile($file));
        }
    }

    public function testNoOutsideProductionCallerOrRegistrationExists(): void
    {
        $root = dirname(__DIR__, 2) . '/src';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (
                !$file instanceof \SplFileInfo
                || $file->getExtension() !== 'php'
                || str_contains($file->getPathname(), '/Internal/Audit/Artifact/')
            ) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertFalse($this->hasArtifactReference($source), $file->getPathname() . ' outside artifact caller');
        }
    }

    public function testResolvedCallerGuardCoversDirectAliasGroupAndDynamicMutations(): void
    {
        $positive = [
            '<?php /* BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactSigner */',
            '<?php $message = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner";',
            '<?php $message = "AuditArtifactSigner::checkpoint";',
            '<?php use BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactSigner as S; $message = "S";',
            '<?php use BlackOps\\Internal\\Audit\\Artifact\\{AuditArtifactSigner}; $message = "AuditArtifactSigner";',
            '<?php $callable = ["BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner", "checkpoint"]; $message = "inert";',
            '<?php $callback = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner::checkpoint"; $message = "inert";',
            '<?php $callback = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditCheckpointCodec::decode"; $message = "inert";',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditCheckpointCodec"; $callback = [$class, "decode"]; $message = "inert";',
        ];
        foreach ($positive as $source) {
            self::assertFalse($this->hasArtifactReference($source), 'inert source must remain positive');
        }

        $negative = [
            '<?php \\BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactSigner::checkpoint([], "", [], "");',
            '<?php use BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactSigner as S; S::checkpoint([], "", [], "");',
            '<?php use BlackOps\\Internal\\Audit\\Artifact\\{AuditArtifactSigner}; AuditArtifactSigner::checkpoint([], "", [], "");',
            '<?php $class = \\BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactSigner::class; $class::checkpoint([], "", [], "");',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner"; class_exists($class);',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner"; new $class();',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner"; $callable = [$class, "checkpoint"]; $callable([], "", [], "");',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner"; register_shutdown_function($class);',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner"; $callback = [$class, "checkpoint"]; call_user_func($callback, [], "", [], "");',
            '<?php $callback = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner::checkpoint"; $callback([], "", [], "");',
            '<?php $callback = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner::checkpoint"; call_user_func_array($callback, [[], "", [], ""]);',
            '<?php $callback = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditArtifactSigner::checkpoint"; register_shutdown_function($callback);',
            '<?php $class = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditCheckpointCodec"; $callback = [$class, "decode"]; call_user_func($callback, "{}");',
            '<?php $callback = "BlackOps\\\\Internal\\\\Audit\\\\Artifact\\\\AuditCheckpointCodec::decode"; call_user_func_array($callback, ["{}"]);',
        ];
        foreach ($negative as $source) {
            self::assertTrue($this->hasArtifactReference($source), 'resolved caller mutation must be rejected');
        }
    }

    public function testResolvedStaticCallsStayWithinTheArtifactInventory(): void
    {
        foreach ($this->sources() as $file => $source) {
            self::assertTrue(
                $this->sourceConforms($file, $source, $this->typeForFile($file)),
                $file . ' must satisfy the resolved source oracle',
            );
        }
    }

    public function testResolvedAstMutationsAreRejectedAndInertTextIsAccepted(): void
    {
        $sources = $this->sources();
        foreach ($sources as $file => $source) {
            self::assertTrue(
                $this->sourceConforms($file, $source, $this->typeForFile($file)),
                $file . ' baseline source',
            );
        }

        $signer = $sources['AuditArtifactSigner.php'];
        $verifier = $sources['AuditArtifactVerifier.php'];
        $canonical = $sources['AuditArtifactCanonical.php'];
        $checkpoint = $sources['AuditCheckpointCodec.php'];
        $scalar = $sources['AuditArtifactScalar.php'];
        $trustScalar = $sources['AuditTrustScalar.php'];
        $integrity = $sources['AuditArtifactIntegrity.php'];
        $wire = $sources['AuditArtifactWirePreflight.php'];

        $mutations = [
            'extra type' => $signer . "\nfinal class AddedArtifactType {}\n",
            'namespace effect' => $canonical . "\nAuditArtifactCanonical::artifact([]);\n",
            'extra method' => $this->insertBeforeFinalBrace(
                $signer,
                "\n    private static function extra(): void {}\n",
            ),
            'typed callable' => $this->replaceFirst(
                $signer,
                "        #[\\SensitiveParameter]\n        string \$privateSeed,\n        #[\\SensitiveParameter]\n        array \$entry,",
                "        #[\\SensitiveParameter]\n        callable \$privateSeed,\n        #[\\SensitiveParameter]\n        array \$entry,",
            ),
            'extra property' => $this->insertBeforeFinalBrace($signer, "\n    private string \$extra;\n"),
            'secret state' => $this->insertBeforeFinalBrace($signer, "\n    private string \$retainedSecret;\n"),
            'public api attribute' => $this->replaceFirst(
                $signer,
                'final class AuditArtifactSigner',
                "#[\\BlackOps\\PublicApi]\nfinal class AuditArtifactSigner",
            ),
            'nested type' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        new class {};\n        return \$bytes;",
            ),
            'dependency import' => $this->replaceFirst(
                $canonical,
                'use BlackOps\\Internal\\Audit\\AuditJcsCodec;',
                "use DateTimeImmutable;\nuse BlackOps\\Internal\\Audit\\AuditJcsCodec;",
            ),
            'lost sensitivity' => $this->replaceFirst(
                $signer,
                "    public static function checkpoint(\n        #[\\SensitiveParameter]\n        array \$unsigned,",
                "    public static function checkpoint(\n        array \$unsigned,",
            ),
            'changed role' => $this->replaceFirst(
                $signer,
                "            \$normalized['signingKeyRef'],\n            'checkpoint',\n            \$trustedNow,",
                "            \$normalized['signingKeyRef'],\n            'anchor',\n            \$trustedNow,",
            ),
            'changed domain' => $this->replaceFirst(
                $checkpoint,
                "'blackops.audit.checkpoint.hash.v1'",
                "'blackops.audit.changed.v1'",
            ),
            'lowercase native constant' => $this->replaceFirst(
                $checkpoint,
                'SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING',
                'sodium_base64_variant_urlsafe_no_padding',
            ),
            'secret return' => $this->replaceFirst($signer, 'return $signature;', 'return $privateSeed;'),
            'secret logging' => $this->replaceFirst(
                $signer,
                'return $signature;',
                "error_log(\$privateSeed);\n            return \$signature;",
            ),
            'secret echo' => $this->replaceFirst(
                $signer,
                'return $signature;',
                "echo \$privateSeed;\n            return \$signature;",
            ),
            'print effect' => $this->replaceFirst(
                $signer,
                'return $signature;',
                "print \$privateSeed;\n            return \$signature;",
            ),
            'include effect' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        include __FILE__;\n        return \$bytes;",
            ),
            'eval effect' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        eval('return 1;');\n        return \$bytes;",
            ),
            'shell effect' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        `echo shell`;\n        return \$bytes;",
            ),
            'nullsafe call' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        \$object?->method();\n        return \$bytes;",
            ),
            'verifier to signer edge' => $this->replaceFirst(
                $verifier,
                '        $checkpoint = AuditCheckpointCodec::decode($checkpointBytes);',
                '        $checkpoint = AuditArtifactSigner::checkpoint($checkpointBytes);',
            ),
            'clock construction' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        new \\DateTimeImmutable();\n        return \$bytes;",
            ),
            'provider edge' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        Provider::resolve();\n        return \$bytes;",
            ),
            'append edge' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        \$store->append();\n        return \$bytes;",
            ),
            'dynamic free call' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        \$callable();\n        return \$bytes;",
            ),
            'dynamic static call' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        \$class::\$method();\n        return \$bytes;",
            ),
            'dynamic method call' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        \$object->\$method();\n        return \$bytes;",
            ),
            'closure extension' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        \$closure = static fn(): string => \$bytes;\n        return \$bytes;",
            ),
            'changed failure constant' => $this->replaceFirst(
                $canonical,
                'AuditArtifactFailure::ArtifactInvalid',
                'AuditArtifactFailure::TrustInvalid',
            ),
            'extra time owner' => $this->replaceFirst(
                $canonical,
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        return \$bytes;",
                "        if (\$bytes === '' || strlen(\$bytes) > 1_048_575) {\n            self::invalid();\n        }\n        AuditRecordTime::assertTimestamp(\$bytes);\n        return \$bytes;",
            ),
            'native owner' => $this->replaceFirst(
                $signer,
                'sodium_crypto_sign_detached',
                'sodium_crypto_sign_verify_detached',
            ),
            'public decode owner' => $this->replaceFirst($trustScalar, 'sodium_base642bin', 'sodium_bin2base64'),
            'preflight after decoder' => $this->swapFirst(
                $canonical,
                "        AuditArtifactWirePreflight::assertFlatObject(\$bytes);\n",
                "            \$decoded = \$jcs->decode(\$bytes);\n",
            ),
            'conditional preflight' => $this->replaceFirst(
                $canonical,
                "        AuditArtifactWirePreflight::assertFlatObject(\$bytes);\n",
                "        if (false) {\n            AuditArtifactWirePreflight::assertFlatObject(\$bytes);\n        }\n",
            ),
            'cleanup omission' => $this->replaceFirst($signer, 'sodium_memzero($secretKey);', ''),
            'conditional cleanup' => $this->replaceFirst(
                $signer,
                "            if (\$secretKey !== '') {\n                sodium_memzero(\$secretKey);\n            }",
                "            if (false) {\n                if (\$secretKey !== '') {\n                    sodium_memzero(\$secretKey);\n                }\n            }",
            ),
            'short-circuit cleanup' => $this->replaceFirst(
                $signer,
                '                sodium_memzero($secretKey);',
                '                false && sodium_memzero($secretKey);',
            ),
            'disabled string guard' => $this->replaceFirst(
                $scalar,
                "        if (!is_string(\$value) || strlen(\$value) < 1 || strlen(\$value) > 256) {",
                "        if (false && (!is_string(\$value) || strlen(\$value) < 1 || strlen(\$value) > 256)) {",
            ),
            'disabled integer guard' => $this->replaceFirst(
                $scalar,
                "        if (!is_int(\$value) || \$value < 1 || \$value > AuditJcsCodec::MAX_SAFE_INTEGER) {",
                "        if (false && (!is_int(\$value) || \$value < 1 || \$value > AuditJcsCodec::MAX_SAFE_INTEGER)) {",
            ),
            'inverted string bounds' => $this->replaceFirst(
                $scalar,
                "        if (!is_string(\$value) || strlen(\$value) < 1 || strlen(\$value) > 256) {",
                "        if (!is_string(\$value) || !(strlen(\$value) < 1 || strlen(\$value) > 256)) {",
            ),
            'inverted integer bounds' => $this->replaceFirst(
                $scalar,
                "        if (!is_int(\$value) || \$value < 1 || \$value > AuditJcsCodec::MAX_SAFE_INTEGER) {",
                "        if (!is_int(\$value) || !(\$value < 1 || \$value > AuditJcsCodec::MAX_SAFE_INTEGER)) {",
            ),
            'reordered string validator' => $this->replaceFirst(
                $scalar,
                "        if (!is_string(\$value) || strlen(\$value) < 1 || strlen(\$value) > 256) {\n            self::invalid();\n        }\n        try {\n            AuditStringValidator::assertSafe(\$value);\n        } catch (\\Throwable) {\n            self::invalid();\n        }",
                "        if (!is_string(\$value) || strlen(\$value) < 1 || strlen(\$value) > 256) {\n            self::invalid();\n            AuditStringValidator::assertSafe(\$value);\n        }\n        try {\n            \$value = \$value;\n        } catch (\\Throwable) {\n            self::invalid();\n        }",
            ),
            'unlisted hash operation' => $this->replaceFirst(
                $integrity,
                "hash('sha256', self::frame(\n            'blackops.audit.checkpoint.hash.v1',",
                "md5('sha256', self::frame(\n            'blackops.audit.checkpoint.hash.v1',",
            ),
            'unlisted preflight operation' => $this->replaceFirst(
                $wire,
                "            self::invalid();\n        }\n\n        \$inString = false;",
                "            AuditArtifactCanonical::decode(\$bytes);\n            self::invalid();\n        }\n\n        \$inString = false;",
            ),
        ];
        foreach ($mutations as $name => $mutated) {
            $file = match ($name) {
                'extra type',
                'extra method',
                'typed callable',
                'extra property',
                'secret state',
                'public api attribute',
                'lost sensitivity',
                'changed role',
                'secret return',
                'secret logging',
                'secret echo',
                'print effect',
                'native owner',
                'cleanup omission',
                'conditional cleanup',
                'short-circuit cleanup',
                    => 'AuditArtifactSigner.php',
                'verifier to signer edge' => 'AuditArtifactVerifier.php',
                'changed domain' => 'AuditCheckpointCodec.php',
                'lowercase native constant' => 'AuditCheckpointCodec.php',
                'disabled string guard',
                'disabled integer guard',
                'inverted string bounds',
                'inverted integer bounds',
                'reordered string validator',
                    => 'AuditArtifactScalar.php',
                'unlisted hash operation' => 'AuditArtifactIntegrity.php',
                'namespace effect',
                'nested type',
                'dependency import',
                'clock construction',
                'provider edge',
                'append edge',
                'dynamic free call',
                'dynamic static call',
                'dynamic method call',
                'closure extension',
                'include effect',
                'eval effect',
                'shell effect',
                'nullsafe call',
                'changed failure constant',
                'extra time owner',
                    => 'AuditArtifactCanonical.php',
                'public decode owner' => 'AuditTrustScalar.php',
                'preflight after decoder' => 'AuditArtifactCanonical.php',
                'conditional preflight' => 'AuditArtifactCanonical.php',
                'unlisted preflight operation' => 'AuditArtifactWirePreflight.php',
                default => self::fail('Unknown mutation ' . $name),
            };
            self::assertNotSame($sources[$file], $mutated, $name . ' must change the actual source');
            try {
                $this->parseSource($mutated);
            } catch (\Throwable $error) {
                self::fail($name . ' mutation must parse: ' . $error->getMessage());
            }
            self::assertFalse(
                $this->sourceConforms($file, $mutated, $this->typeForFile($file)),
                $name . ' must be rejected by the same AST oracle',
            );
        }

        $positiveMutation = $this->replaceFirst(
            $signer,
            'return $signature;',
            "/* BlackOps\\Internal\\Audit\\Artifact\\AuditArtifactVerifier */\n            \$inert = 'AuditArtifactSigner::checkpoint';\n            return \$signature;",
        );
        self::assertTrue(
            $this->sourceConforms('AuditArtifactSigner.php', $positiveMutation, AuditArtifactSigner::class),
            'comments and inert strings must remain positive',
        );
    }

    /** @param array{return:array{type:?string,allowsNull:bool},visibility:string,static:bool,params:list<array{name:string,type:?string,allowsNull:bool}>} $expected */
    private function assertReflectionSignature(ReflectionMethod $method, array $expected, string $label): void
    {
        $returnType = $method->getReturnType();
        self::assertSame($expected['return']['type'], $returnType?->getName(), $label . ' return type');
        self::assertSame(
            $expected['return']['allowsNull'],
            $returnType?->allowsNull() ?? false,
            $label . ' return nullability',
        );
        $parameters = $method->getParameters();
        self::assertCount(count($expected['params']), $parameters, $label . ' parameter count');
        foreach ($expected['params'] as $index => $parameterExpected) {
            $parameter = $parameters[$index];
            self::assertSame($parameterExpected['name'], $parameter->getName(), $label . ' parameter name');
            self::assertSame($parameterExpected['type'], $parameter->getType()?->getName(), $label . ' parameter type');
            self::assertSame(
                $parameterExpected['allowsNull'],
                $parameter->getType()?->allowsNull() ?? false,
                $label . ' parameter nullability',
            );
            self::assertFalse($parameter->isVariadic(), $label . ' variadic');
            self::assertFalse($parameter->isOptional(), $label . ' optional');
            $expectedSensitive = !(
                $method->getDeclaringClass()->getName() === AuditArtifactException::class
                && $method->getName() === '__construct'
            );
            self::assertCount(
                $expectedSensitive ? 1 : 0,
                $parameter->getAttributes(\SensitiveParameter::class),
                $label . ' parameter sensitivity',
            );
        }
    }

    /** @return array{nodes:list<Node>,declaration:ClassLike} */
    private function parseArtifact(string $source, string $expectedType): array
    {
        $nodes = $this->parseSource($source);
        $namespace = $this->assertSourceEnvelope($nodes);
        $declarations = [];
        foreach ([Class_::class, Enum_::class, Interface_::class, Trait_::class] as $kind) {
            foreach (new NodeFinder()->findInstanceOf($nodes, $kind) as $declaration) {
                $declarations[] = $declaration;
            }
        }
        self::assertCount(1, $declarations, 'one named declaration per Product file');
        $declaration = $declarations[0];
        self::assertInstanceOf(ClassLike::class, $declaration);
        self::assertSame($expectedType, $this->declarationName($declaration));
        $namespaceDeclarations = array_values(array_filter(
            $namespace->stmts ?? [],
            static fn(Node $statement): bool => $statement instanceof ClassLike,
        ));
        self::assertCount(1, $namespaceDeclarations, 'one direct declaration per Product namespace');
        self::assertSame($declaration, $namespaceDeclarations[0], 'declaration must be direct in namespace');
        return ['nodes' => $nodes, 'declaration' => $declaration];
    }

    /** @param list<Node> $nodes */
    private function assertSourceEnvelope(array $nodes): Namespace_
    {
        $topLevel = array_values(array_filter($nodes, static fn(Node $node): bool => !$node instanceof Nop));
        self::assertCount(2, $topLevel, 'strict declaration and one namespace envelope only');
        self::assertInstanceOf(Declare_::class, $topLevel[0]);
        self::assertNull($topLevel[0]->stmts, 'strict declare must not contain executable statements');
        self::assertCount(1, $topLevel[0]->declares, 'one strict_types declaration');
        $declare = $topLevel[0]->declares[0];
        self::assertInstanceOf(DeclareItem::class, $declare);
        self::assertSame('strict_types', $declare->key->toString());
        self::assertInstanceOf(Int_::class, $declare->value);
        self::assertSame(1, $declare->value->value);
        self::assertInstanceOf(Namespace_::class, $topLevel[1]);
        $namespace = $topLevel[1];
        self::assertSame(self::ARTIFACT_NAMESPACE, $namespace->name?->toString());
        foreach (array_values(array_filter(
            $namespace->stmts ?? [],
            static fn(Node $node): bool => !$node instanceof Nop,
        )) as $statement) {
            self::assertTrue(
                $statement instanceof Use_ || $statement instanceof GroupUse || $statement instanceof ClassLike,
                'namespace envelope contains only imports and one declaration',
            );
        }
        return $namespace;
    }

    private function assertSourceConforms(string $file, string $source, string $expectedType): void
    {
        $parsed = $this->parseArtifact($source, $expectedType);
        $nodes = $parsed['nodes'];
        $declaration = $parsed['declaration'];
        self::assertSame($this->expectedImports()[$file] ?? [], $this->imports($nodes), $file . ' imports');
        self::assertSame([], $declaration->attrGroups, $file . ' class attributes');
        self::assertCount(0, new NodeFinder()->findInstanceOf($declaration, Closure::class), $file . ' closures');
        self::assertCount(
            0,
            new NodeFinder()->findInstanceOf($declaration, ArrowFunction::class),
            $file . ' arrow functions',
        );
        foreach ([
            Echo_::class,
            Print_::class,
            Include_::class,
            Eval_::class,
            ShellExec::class,
            NullsafeMethodCall::class,
        ] as $unsupported) {
            self::assertCount(
                0,
                new NodeFinder()->findInstanceOf($declaration, $unsupported),
                $file . ' unsupported ' . $unsupported,
            );
        }
        self::assertTrue($declaration instanceof Class_ || $declaration instanceof Enum_, $file . ' declaration kind');
        if ($declaration instanceof Class_) {
            self::assertTrue($declaration->isFinal(), $file . ' final declaration');
            self::assertFalse($declaration->isAbstract(), $file . ' abstract declaration');
            self::assertFalse($declaration->isReadonly(), $file . ' readonly declaration');
            self::assertSame([], $declaration->implements, $file . ' interfaces');
            $expectedParent = $expectedType === AuditArtifactException::class ? 'RuntimeException' : null;
            self::assertSame($expectedParent, $declaration->extends?->toString(), $file . ' parent');
            $expectedProperties = $expectedType === AuditArtifactException::class ? ['failure'] : [];
            $properties = $declaration->getProperties();
            $propertyNames = [];
            foreach ($properties as $property) {
                foreach ($property->props as $propertyProperty) {
                    $propertyNames[] = $propertyProperty->name->toString();
                }
            }
            self::assertSame($expectedProperties, $propertyNames, $file . ' properties');
            foreach ($properties as $property) {
                self::assertTrue($property->isPrivate(), $file . ' property visibility');
                self::assertFalse($property->isStatic(), $file . ' property static');
                self::assertSame(
                    $expectedType === AuditArtifactException::class,
                    $property->isReadonly(),
                    $file . ' property readonly',
                );
                self::assertSame([], $property->attrGroups, $file . ' property attributes');
                self::assertSame(
                    $expectedType === AuditArtifactException::class ? AuditArtifactFailure::class : null,
                    $this->astTypeName($property->type, $file . ' property'),
                    $file . ' property type',
                );
            }
            self::assertSame([], $declaration->getConstants(), $file . ' class constants');
        } else {
            self::assertSame(AuditArtifactFailure::class, $expectedType);
            self::assertSame('string', $declaration->scalarType?->toString());
            $cases = array_values(array_filter(
                $declaration->stmts,
                static fn(Node $statement): bool => $statement instanceof EnumCase,
            ));
            self::assertSame(
                ['ArtifactInvalid', 'TrustInvalid', 'SignatureInvalid', 'SigningUnavailable'],
                array_map(static fn(EnumCase $case): string => $case->name->toString(), $cases),
            );
            self::assertSame([], $declaration->getMethods(), $file . ' enum methods');
        }

        $expectedMethods = $this->expectedSignatures()[$expectedType] ?? [];
        $methods = [];
        foreach ($declaration->getMethods() as $method) {
            $methods[$method->name->toString()] = $method;
        }
        self::assertSame(array_keys($expectedMethods), array_keys($methods), $file . ' methods');
        foreach ($expectedMethods as $name => $signature) {
            $method = $methods[$name];
            self::assertSame(
                $signature['visibility'],
                $method->isPublic() ? 'public' : ($method->isPrivate() ? 'private' : 'protected'),
                $file . '::' . $name . ' visibility',
            );
            self::assertSame($signature['static'], $method->isStatic(), $file . '::' . $name . ' static');
            self::assertFalse($method->returnsByRef(), $file . '::' . $name . ' reference return');
            self::assertSame([], $method->attrGroups, $file . '::' . $name . ' attributes');
            $this->assertAstSignature($method, $signature, $expectedType . '::' . $name);
            $operations = $this->operations($method, $expectedType);
            $expectedOperations = $this->expectedOperations()[$expectedType . '::' . $name] ?? [];
            sort($operations);
            sort($expectedOperations);
            self::assertSame($expectedOperations, $operations, $expectedType . '::' . $name . ' operations');
            $this->assertFixedArguments($method, $expectedType, $expectedType . '::' . $name);
            $this->assertNoSecretReturnOrLogging($method, $expectedType . '::' . $name);
        }
        $this->assertOwnerOrdering($declaration, $expectedType);
    }

    /** @param array{return:array{type:?string,allowsNull:bool},visibility:string,static:bool,params:list<array{name:string,type:?string,allowsNull:bool}>} $expected */
    private function assertAstSignature(ClassMethod $method, array $expected, string $label): void
    {
        $return = $method->returnType;
        self::assertSame($expected['return']['type'], $this->astTypeName($return, $label), $label . ' return type');
        self::assertSame(
            $expected['return']['allowsNull'],
            $this->astAllowsNull($return),
            $label . ' return nullability',
        );
        self::assertCount(count($expected['params']), $method->params, $label . ' parameters');
        foreach ($expected['params'] as $index => $parameterExpected) {
            $parameter = $method->params[$index];
            self::assertSame($parameterExpected['name'], $parameter->var->name, $label . ' parameter name');
            self::assertSame(
                $parameterExpected['type'],
                $this->astTypeName($parameter->type, $label),
                $label . ' parameter type',
            );
            self::assertSame(
                $parameterExpected['allowsNull'],
                $this->astAllowsNull($parameter->type),
                $label . ' parameter nullability',
            );
            self::assertFalse($parameter->variadic, $label . ' variadic');
            self::assertFalse($parameter->byRef, $label . ' reference parameter');
            $attributes = [];
            foreach ($parameter->attrGroups as $group) {
                foreach ($group->attrs as $attribute) {
                    $attributes[] = $attribute->name->getLast();
                }
            }
            $expectedAttributes = $label === AuditArtifactException::class . '::__construct'
                ? []
                : ['SensitiveParameter'];
            self::assertSame($expectedAttributes, $attributes, $label . ' parameter attributes');
        }
    }

    private function astAllowsNull(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return true;
        }
        return $type instanceof Identifier && strtolower($type->toString()) === 'mixed';
    }

    private function astTypeName(?Node $type, string $owner): ?string
    {
        if ($type === null) {
            return null;
        }
        if ($type instanceof NullableType) {
            return $this->astTypeName($type->type, $owner);
        }
        if ($type instanceof Name) {
            return $this->resolvedName($type, $owner);
        }
        if ($type instanceof Identifier) {
            return $type->toString();
        }
        return $type->getType();
    }

    /** @return list<string> */
    private function imports(array $nodes): array
    {
        $imports = [];
        $importNodes = array_merge(
            new NodeFinder()->findInstanceOf($nodes, GroupUse::class),
            new NodeFinder()->findInstanceOf($nodes, Use_::class),
        );
        foreach ($importNodes as $node) {
            if ($node instanceof GroupUse) {
                foreach ($node->uses as $use) {
                    $name = Name::concat($node->prefix, $use->name);
                    $imports[] = $this->importName($name, $use->getAlias()->toString(), $node->type | $use->type);
                }
            } elseif ($node instanceof Use_) {
                foreach ($node->uses as $use) {
                    $imports[] = $this->importName($use->name, $use->getAlias()->toString(), $node->type);
                }
            }
        }
        sort($imports);
        return $imports;
    }

    private function importName(Name $name, string $alias, int $type): string
    {
        $prefix = match ($type) {
            Use_::TYPE_FUNCTION => 'function ',
            Use_::TYPE_CONSTANT => 'const ',
            default => '',
        };
        $value = ltrim($name->toString(), '\\');
        $defaultAlias = $name->getLast();
        if ($alias !== $defaultAlias) {
            $value .= ' as ' . $alias;
        }
        return $prefix . $value;
    }

    /** @return list<string> */
    private function operations(ClassMethod $method, string $owner): array
    {
        $operations = [];
        foreach (new NodeFinder()->findInstanceOf($method, StaticCall::class) as $call) {
            if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                $operations[] = 'dynamic:static';
                continue;
            }
            $operations[] = $this->staticOperation($this->resolvedName($call->class, $owner), $call->name->toString());
        }
        foreach (new NodeFinder()->findInstanceOf($method, FuncCall::class) as $call) {
            if (!$call->name instanceof Name) {
                $operations[] = 'dynamic:function';
                continue;
            }
            $operations[] = 'free:' . strtolower(ltrim($call->name->toString(), '\\'));
        }
        foreach (new NodeFinder()->findInstanceOf($method, MethodCall::class) as $call) {
            if (!$call->name instanceof Identifier) {
                $operations[] = 'dynamic:method';
                continue;
            }
            $receiver = $call->var;
            if ($receiver instanceof New_ && $receiver->class instanceof Name) {
                $operations[] =
                    'method:new:' . $this->resolvedName($receiver->class, $owner) . '::' . $call->name->toString();
            } elseif ($receiver instanceof Variable && is_string($receiver->name)) {
                $operations[] = 'method:var:$' . $receiver->name . '::' . $call->name->toString();
            } else {
                $operations[] = 'dynamic:method';
            }
        }
        foreach (new NodeFinder()->findInstanceOf($method, New_::class) as $new) {
            $operations[] = $new->class instanceof Name
                ? 'new:' . $this->resolvedName($new->class, $owner)
                : 'dynamic:new';
        }
        foreach (new NodeFinder()->findInstanceOf($method, ClassConstFetch::class) as $constant) {
            if (!$constant->class instanceof Name || !$constant->name instanceof Identifier) {
                $operations[] = 'dynamic:constant';
                continue;
            }
            $operations[] =
                'const:' . $this->resolvedName($constant->class, $owner) . '::' . $constant->name->toString();
        }
        foreach (new NodeFinder()->findInstanceOf($method, ConstFetch::class) as $constant) {
            $name = ltrim($constant->name->toString(), '\\');
            if (!in_array(strtolower($name), ['true', 'false', 'null'], true)) {
                $operations[] = 'const:' . $name;
            }
        }
        return $operations;
    }

    private function staticOperation(string $class, string $method): string
    {
        return 'static:' . $class . '::' . $method;
    }

    private function assertFixedArguments(ClassMethod $method, string $type, string $owner): void
    {
        foreach ($this->fixedArguments()[$owner] ?? [] as $expected) {
            $actual = [];
            foreach ($this->orderedStaticCalls($method) as $call) {
                if (!$call->class instanceof Name || !$call->name instanceof Identifier) {
                    continue;
                }
                $target = $this->resolvedName($call->class, $type) . '::' . $call->name->toString();
                if ($target !== $expected['target']) {
                    continue;
                }
                $argument = $call->args[$expected['index']]->value ?? null;
                $actual[] = $argument instanceof String_ ? $argument->value : null;
            }
            self::assertSame($expected['values'], $actual, $owner . ' fixed argument ' . $expected['target']);
        }
    }

    private function assertNoSecretReturnOrLogging(ClassMethod $method, string $owner): void
    {
        foreach (new NodeFinder()->findInstanceOf($method, Return_::class) as $return) {
            if ($return->expr instanceof Variable && is_string($return->expr->name)) {
                self::assertNotContains(
                    $return->expr->name,
                    ['privateSeed', 'keyPair', 'secretKey'],
                    $owner . ' secret return',
                );
            }
            if ($return->expr instanceof Array_) {
                foreach (new NodeFinder()->findInstanceOf($return->expr, Variable::class) as $variable) {
                    if (is_string($variable->name)) {
                        self::assertNotContains(
                            $variable->name,
                            ['privateSeed', 'keyPair', 'secretKey'],
                            $owner . ' secret return',
                        );
                    }
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($method, FuncCall::class) as $call) {
            if ($call->name instanceof Name) {
                self::assertNotContains(
                    strtolower($call->name->toString()),
                    ['serialize', 'var_dump', 'print_r', 'error_log'],
                    $owner . ' secret/logging operation',
                );
            }
        }
    }

    private function assertOwnerOrdering(ClassLike $declaration, string $type): void
    {
        if ($type === AuditArtifactCanonical::class) {
            $method = $this->classMethod($declaration, 'decode');
            $preflight = $this->staticCalls($method, $type, AuditArtifactWirePreflight::class, 'assertFlatObject');
            $decode = $this->methodCalls($method, 'decode');
            self::assertCount(1, $preflight, $type . ' preflight count');
            self::assertCount(1, $decode, $type . ' decoder count');
            $statements = $method->stmts ?? [];
            $preflightStatements = array_values(array_filter(
                $statements,
                static fn(Node $statement): bool => (
                    $statement instanceof Expression
                    && $statement->expr === $preflight[0]
                ),
            ));
            self::assertCount(1, $preflightStatements, 'preflight must be an unconditional direct statement');
            $preflightIndex = array_search($preflightStatements[0], $statements, true);
            self::assertIsInt($preflightIndex);
            $decodeIndexes = [];
            foreach ($statements as $index => $statement) {
                if (in_array($decode[0], new NodeFinder()->findInstanceOf($statement, MethodCall::class), true)) {
                    $decodeIndexes[] = $index;
                }
            }
            self::assertCount(1, $decodeIndexes, 'decoder must have one direct control ancestor');
            self::assertLessThan(
                $decode[0]->getStartFilePos(),
                $preflight[0]->getStartFilePos(),
                'preflight precedes recursive decoder',
            );
            self::assertLessThan($decodeIndexes[0], $preflightIndex, 'preflight statement precedes decoder control');
        }
        if ($type === AuditArtifactSigner::class) {
            self::assertSame(
                [
                    AuditCheckpointCodec::class . '::signingInput',
                    AuditArtifactShape::class . '::checkpointUnsigned',
                    AuditTrustSnapshotCodec::class . '::key',
                    AuditTrustSnapshotCodec::class . '::assertSignedAt',
                    AuditArtifactSigner::class . '::assertSeedLength',
                    AuditArtifactSigner::class . '::sign',
                    AuditCheckpointCodec::class . '::complete',
                ],
                $this->orderedStaticTargets($this->classMethod($declaration, 'checkpoint'), $type),
            );
            self::assertSame(
                [
                    AuditAnchorReceiptCodec::class . '::signingInput',
                    AuditArtifactShape::class . '::anchorUnsigned',
                    AuditAnchorReceiptCodec::class . '::assertCheckpointBinding',
                    AuditArtifactVerifier::class . '::checkpoint',
                    AuditTrustSnapshotCodec::class . '::key',
                    AuditTrustSnapshotCodec::class . '::assertSignedAt',
                    AuditArtifactSigner::class . '::assertSeedLength',
                    AuditArtifactSigner::class . '::sign',
                    AuditAnchorReceiptCodec::class . '::complete',
                ],
                $this->orderedStaticTargets($this->classMethod($declaration, 'anchorReceipt'), $type),
            );
            $native = [];
            foreach ($this->orderedOperations($this->classMethod($declaration, 'sign'), $type) as $operation) {
                if (str_starts_with($operation, 'free:sodium_')) {
                    $native[] = substr($operation, strlen('free:'));
                }
            }
            self::assertSame(
                [
                    'sodium_base642bin',
                    'sodium_crypto_sign_seed_keypair',
                    'sodium_crypto_sign_secretkey',
                    'sodium_crypto_sign_publickey',
                    'sodium_crypto_sign_detached',
                    'sodium_memzero',
                    'sodium_memzero',
                ],
                $native,
                'signing native order and cleanup',
            );
            $this->assertSignerCleanup($this->classMethod($declaration, 'sign'));
        }
        if ($type === AuditArtifactScalar::class) {
            $this->assertScalarEarlyGuards($declaration);
        }
        if ($type === AuditArtifactVerifier::class) {
            self::assertSame(
                [
                    AuditCheckpointCodec::class . '::decode',
                    AuditTrustSnapshotCodec::class . '::key',
                    AuditArtifactVerifier::class . '::verifyDetached',
                    AuditTrustSnapshotCodec::class . '::assertSignedAt',
                ],
                $this->orderedStaticTargets($this->classMethod($declaration, 'checkpoint'), $type),
            );
            self::assertSame(
                [
                    AuditAnchorReceiptCodec::class . '::decode',
                    AuditCheckpointCodec::class . '::decode',
                    AuditAnchorReceiptCodec::class . '::assertCheckpointBinding',
                    AuditArtifactVerifier::class . '::checkpoint',
                    AuditTrustSnapshotCodec::class . '::key',
                    AuditArtifactVerifier::class . '::verifyDetached',
                    AuditTrustSnapshotCodec::class . '::assertSignedAt',
                ],
                $this->orderedStaticTargets($this->classMethod($declaration, 'anchorReceipt'), $type),
            );
        }
    }

    private function assertSignerCleanup(ClassMethod $method): void
    {
        $finally = new NodeFinder()->findInstanceOf($method, Finally_::class);
        self::assertCount(1, $finally, 'signing finally cleanup');
        self::assertCount(2, $finally[0]->stmts, 'signing finally owns two cleanup guards');
        foreach (['secretKey', 'keyPair'] as $index => $variableName) {
            $statement = $finally[0]->stmts[$index];
            self::assertInstanceOf(If_::class, $statement, 'cleanup guard control');
            self::assertSame([], $statement->elseifs, 'cleanup guard has no elseif');
            self::assertNull($statement->else, 'cleanup guard has no else');
            self::assertInstanceOf(NotIdentical::class, $statement->cond, 'cleanup guard condition');
            self::assertInstanceOf(Variable::class, $statement->cond->left);
            self::assertSame($variableName, $statement->cond->left->name, 'cleanup guard variable');
            self::assertInstanceOf(String_::class, $statement->cond->right);
            self::assertSame('', $statement->cond->right->value, 'cleanup guard empty sentinel');
            self::assertCount(1, $statement->stmts, 'cleanup guard body');
            self::assertInstanceOf(Expression::class, $statement->stmts[0]);
            $cleanup = $statement->stmts[0]->expr;
            self::assertInstanceOf(FuncCall::class, $cleanup, 'cleanup call must be direct');
            self::assertInstanceOf(Name::class, $cleanup->name);
            self::assertSame('sodium_memzero', strtolower($cleanup->name->toString()));
            self::assertCount(1, $cleanup->args);
            self::assertInstanceOf(Variable::class, $cleanup->args[0]->value);
            self::assertSame($variableName, $cleanup->args[0]->value->name, 'cleanup owns expected buffer');
        }
    }

    private function assertScalarEarlyGuards(ClassLike $declaration): void
    {
        $string = $this->classMethod($declaration, 'string');
        $stringStatements = $string->stmts ?? [];
        self::assertInstanceOf(If_::class, $stringStatements[0] ?? null, 'string byte guard is first');
        $stringGuard = $stringStatements[0];
        self::assertInstanceOf(BooleanOr::class, $stringGuard->cond, 'string byte guard boolean shape');
        self::assertCount(0, new NodeFinder()->findInstanceOf($stringGuard->cond, BooleanAnd::class));
        $stringCondition = $stringGuard->cond;
        self::assertInstanceOf(BooleanOr::class, $stringCondition->left, 'string guard connected prefix');
        $stringPrefix = $stringCondition->left;
        $this->assertGuardTypeLeaf($stringPrefix->left, 'is_string');
        self::assertInstanceOf(Smaller::class, $stringPrefix->right, 'string lower byte bound');
        $this->assertStringLengthComparison($stringPrefix->right, 1, 'string lower byte bound');
        self::assertInstanceOf(Greater::class, $stringCondition->right, 'string upper byte bound');
        $this->assertStringLengthComparison($stringCondition->right, 256, 'string upper byte bound');
        $this->assertDirectInvalidGuard($stringGuard, 'string byte guard');
        $validator = $this->staticCalls(
            $string,
            AuditArtifactScalar::class,
            'BlackOps\\Internal\\Audit\\AuditStringValidator',
            'assertSafe',
        );
        self::assertCount(1, $validator, 'string validator call');
        self::assertFalse(
            in_array($validator[0], new NodeFinder()->findInstanceOf($stringGuard->cond, StaticCall::class), true),
            'string validator follows byte guard',
        );
        $validatorStatementIndex = null;
        foreach ($stringStatements as $index => $statement) {
            if (in_array($validator[0], new NodeFinder()->findInstanceOf($statement, StaticCall::class), true)) {
                $validatorStatementIndex = $index;
                break;
            }
        }
        self::assertIsInt($validatorStatementIndex, 'string validator statement');
        self::assertGreaterThan(0, $validatorStatementIndex, 'string validator follows byte guard statement');

        $integer = $this->classMethod($declaration, 'boundedInteger');
        $integerStatements = $integer->stmts ?? [];
        self::assertInstanceOf(If_::class, $integerStatements[0] ?? null, 'integer guard is first');
        $integerGuard = $integerStatements[0];
        self::assertInstanceOf(BooleanOr::class, $integerGuard->cond, 'integer guard boolean shape');
        self::assertCount(0, new NodeFinder()->findInstanceOf($integerGuard->cond, BooleanAnd::class));
        $integerCondition = $integerGuard->cond;
        self::assertInstanceOf(BooleanOr::class, $integerCondition->left, 'integer guard connected prefix');
        $integerPrefix = $integerCondition->left;
        $this->assertGuardTypeLeaf($integerPrefix->left, 'is_int');
        self::assertInstanceOf(Smaller::class, $integerPrefix->right, 'integer lower bound');
        self::assertInstanceOf(Variable::class, $integerPrefix->right->left);
        self::assertSame('value', $integerPrefix->right->left->name);
        self::assertInstanceOf(Int_::class, $integerPrefix->right->right);
        self::assertSame(1, $integerPrefix->right->right->value);
        self::assertInstanceOf(Greater::class, $integerCondition->right, 'integer maximum bound');
        $integerMaximum = $integerCondition->right;
        self::assertInstanceOf(Variable::class, $integerMaximum->left);
        self::assertSame('value', $integerMaximum->left->name);
        self::assertInstanceOf(ClassConstFetch::class, $integerMaximum->right);
        self::assertInstanceOf(Name::class, $integerMaximum->right->class);
        self::assertInstanceOf(Identifier::class, $integerMaximum->right->name);
        self::assertSame('BlackOps\\Internal\\Audit\\AuditJcsCodec', $this->resolvedName(
            $integerMaximum->right->class,
            AuditArtifactScalar::class,
        ));
        self::assertSame('MAX_SAFE_INTEGER', $integerMaximum->right->name->toString());
        $this->assertDirectInvalidGuard($integerGuard, 'integer guard');
    }

    private function assertGuardTypeLeaf(Node $node, string $function): void
    {
        self::assertInstanceOf(BooleanNot::class, $node, $function . ' guard negation');
        $call = $node->expr;
        self::assertInstanceOf(FuncCall::class, $call, $function . ' guard call');
        self::assertInstanceOf(Name::class, $call->name);
        self::assertSame($function, strtolower($call->name->toString()));
        self::assertCount(1, $call->args);
        self::assertInstanceOf(Variable::class, $call->args[0]->value);
        self::assertSame('value', $call->args[0]->value->name);
    }

    private function assertStringLengthComparison(Smaller|Greater $comparison, int $expectedLimit, string $label): void
    {
        self::assertTrue($comparison instanceof Smaller || $comparison instanceof Greater, $label . ' comparison');
        self::assertInstanceOf(FuncCall::class, $comparison->left);
        self::assertInstanceOf(Name::class, $comparison->left->name);
        self::assertSame('strlen', strtolower($comparison->left->name->toString()));
        self::assertCount(1, $comparison->left->args);
        self::assertInstanceOf(Variable::class, $comparison->left->args[0]->value);
        self::assertSame('value', $comparison->left->args[0]->value->name);
        self::assertInstanceOf(Int_::class, $comparison->right);
        self::assertSame($expectedLimit, $comparison->right->value);
    }

    private function assertDirectInvalidGuard(If_ $guard, string $label): void
    {
        self::assertCount(1, $guard->stmts, $label . ' direct rejecting body');
        self::assertInstanceOf(Expression::class, $guard->stmts[0]);
        $expression = $guard->stmts[0]->expr;
        self::assertInstanceOf(StaticCall::class, $expression, $label . ' direct invalid call');
        self::assertInstanceOf(Name::class, $expression->class);
        self::assertSame(AuditArtifactScalar::class, $this->resolvedName(
            $expression->class,
            AuditArtifactScalar::class,
        ));
        self::assertInstanceOf(Identifier::class, $expression->name);
        self::assertSame('invalid', $expression->name->toString());
        self::assertCount(0, $expression->args);
    }

    /** @return list<string> */
    private function orderedOperations(ClassMethod $method, string $owner): array
    {
        $nodes = [];
        foreach ([
            StaticCall::class,
            FuncCall::class,
            MethodCall::class,
            New_::class,
            ClassConstFetch::class,
            ConstFetch::class,
        ] as $kind) {
            foreach (new NodeFinder()->findInstanceOf($method, $kind) as $node) {
                $nodes[] = $node;
            }
        }
        usort(
            $nodes,
            static fn(Node $left, Node $right): int => $left->getStartFilePos() <=> $right->getStartFilePos(),
        );
        $operations = [];
        foreach ($nodes as $node) {
            $operations[] = $this->operationForNode($node, $owner);
        }
        return $operations;
    }

    private function operationForNode(Node $node, string $owner): string
    {
        if ($node instanceof StaticCall) {
            if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
                return 'dynamic:static';
            }
            return $this->staticOperation($this->resolvedName($node->class, $owner), $node->name->toString());
        }
        if ($node instanceof FuncCall) {
            return $node->name instanceof Name
                ? 'free:' . strtolower(ltrim($node->name->toString(), '\\'))
                : 'dynamic:function';
        }
        if ($node instanceof MethodCall) {
            if (!$node->name instanceof Identifier) {
                return 'dynamic:method';
            }
            if ($node->var instanceof New_ && $node->var->class instanceof Name) {
                return 'method:new:' . $this->resolvedName($node->var->class, $owner) . '::' . $node->name->toString();
            }
            if ($node->var instanceof Variable && is_string($node->var->name)) {
                return 'method:var:$' . $node->var->name . '::' . $node->name->toString();
            }
            return 'dynamic:method';
        }
        if ($node instanceof New_) {
            return $node->class instanceof Name ? 'new:' . $this->resolvedName($node->class, $owner) : 'dynamic:new';
        }
        if ($node instanceof ClassConstFetch) {
            if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
                return 'dynamic:constant';
            }
            return 'const:' . $this->resolvedName($node->class, $owner) . '::' . $node->name->toString();
        }
        if ($node instanceof ConstFetch) {
            $name = ltrim($node->name->toString(), '\\');
            return in_array(strtolower($name), ['true', 'false', 'null'], true) ? 'language-literal' : 'const:' . $name;
        }
        self::fail('Unsupported operation node ' . $node::class);
    }

    /** @return list<StaticCall> */
    private function orderedStaticCalls(ClassMethod $method): array
    {
        $calls = new NodeFinder()->findInstanceOf($method, StaticCall::class);
        usort($calls, static function (StaticCall $left, StaticCall $right): int {
            if (
                $left->getStartFilePos() < $right->getStartFilePos()
                && $left->getEndFilePos() > $right->getEndFilePos()
            ) {
                return 1;
            }
            if (
                $right->getStartFilePos() < $left->getStartFilePos()
                && $right->getEndFilePos() > $left->getEndFilePos()
            ) {
                return -1;
            }
            return $left->getStartFilePos() <=> $right->getStartFilePos();
        });
        return $calls;
    }

    /** @return list<string> */
    private function orderedStaticTargets(ClassMethod $method, string $owner): array
    {
        $targets = [];
        foreach ($this->orderedStaticCalls($method) as $call) {
            if ($call->class instanceof Name && $call->name instanceof Identifier) {
                $targets[] = $this->resolvedName($call->class, $owner) . '::' . $call->name->toString();
            }
        }
        return $targets;
    }

    /** @return list<StaticCall> */
    private function staticCalls(ClassMethod $method, string $owner, string $class, string $name): array
    {
        return array_values(array_filter(
            $this->orderedStaticCalls($method),
            fn(StaticCall $call): bool => (
                $call->class instanceof Name
                && $call->name instanceof Identifier
                && $this->resolvedName($call->class, $owner) === $class
                && $call->name->toString() === $name
            ),
        ));
    }

    /** @return list<MethodCall> */
    private function methodCalls(ClassMethod $method, string $name): array
    {
        $calls = new NodeFinder()->findInstanceOf($method, MethodCall::class);
        usort(
            $calls,
            static fn(
                MethodCall $left,
                MethodCall $right,
            ): int => $left->getStartFilePos() <=> $right->getStartFilePos(),
        );
        return array_values(array_filter(
            $calls,
            static fn(MethodCall $call): bool => $call->name instanceof Identifier && $call->name->toString() === $name,
        ));
    }

    private function classMethod(ClassLike $declaration, string $name): ClassMethod
    {
        foreach ($declaration->getMethods() as $method) {
            if ($method->name->toString() === $name) {
                return $method;
            }
        }
        self::fail($this->declarationName($declaration) . '::' . $name . ' missing');
    }

    private function resolvedName(Name $name, string $owner): string
    {
        $raw = ltrim($name->toString(), '\\');
        if ($raw === 'self' || $raw === 'static') {
            return $owner;
        }
        if ($raw === 'parent') {
            return $owner === AuditArtifactException::class ? 'RuntimeException' : $raw;
        }
        $resolved = $name->getAttribute('resolvedName');
        if ($resolved instanceof Name) {
            return ltrim($resolved->toString(), '\\');
        }
        if ($name->isFullyQualified()) {
            return $raw;
        }
        $namespaced = $name->getAttribute('namespacedName');
        if ($namespaced instanceof Name) {
            return ltrim($namespaced->toString(), '\\');
        }
        return $raw;
    }

    private function declarationName(ClassLike $declaration): string
    {
        return ltrim($declaration->namespacedName?->toString() ?? $declaration->name?->toString() ?? '', '\\');
    }

    /** @return list<Node> */
    private function parseSource(string $source): array
    {
        $nodes = new ParserFactory()
            ->createForNewestSupportedVersion()
            ->parse($source);
        self::assertIsArray($nodes);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $resolved = $traverser->traverse($nodes);
        self::assertIsArray($resolved);
        return $resolved;
    }

    private function sourceConforms(string $file, string $source, string $type): bool
    {
        try {
            $this->assertSourceConforms($file, $source, $type);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasArtifactReference(string $source): bool
    {
        try {
            $nodes = $this->parseSource($source);
        } catch (\Throwable) {
            return true;
        }
        $artifactTypes = array_fill_keys($this->types(), true);
        $importNames = [];
        $importNodes = array_merge(
            new NodeFinder()->findInstanceOf($nodes, GroupUse::class),
            new NodeFinder()->findInstanceOf($nodes, Use_::class),
        );
        foreach ($importNodes as $node) {
            if ($node instanceof GroupUse) {
                foreach ($node->uses as $use) {
                    $importNames[spl_object_id($use->name)] = true;
                }
                $importNames[spl_object_id($node->prefix)] = true;
                continue;
            }
            if (!$node instanceof Use_) {
                continue;
            }
            foreach ($node->uses as $use) {
                $importNames[spl_object_id($use->name)] = true;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, Name::class) as $name) {
            if (isset($importNames[spl_object_id($name)])) {
                continue;
            }
            if (isset($artifactTypes[$this->resolvedName($name, '')])) {
                return true;
            }
        }
        $values = [];
        foreach (new NodeFinder()->findInstanceOf($nodes, Assign::class) as $assign) {
            if ($assign->var instanceof Variable && is_string($assign->var->name)) {
                $resolved = $this->artifactValue($assign->expr, $values, $artifactTypes);
                if ($resolved !== null) {
                    $values[$assign->var->name] = $resolved;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, FuncCall::class) as $call) {
            if ($call->name instanceof Variable && is_string($call->name->name) && isset($values[$call->name->name])) {
                return true;
            }
            $function = $call->name instanceof Name ? strtolower($call->name->toString()) : '';
            if (!in_array(
                $function,
                [
                    'class_exists',
                    'interface_exists',
                    'trait_exists',
                    'is_a',
                    'is_subclass_of',
                    'register_shutdown_function',
                    'register_tick_function',
                    'set_error_handler',
                    'set_exception_handler',
                    'spl_autoload_register',
                    'call_user_func',
                    'call_user_func_array',
                ],
                true,
            )) {
                continue;
            }
            foreach ($call->args as $argument) {
                if ($this->artifactValue($argument->value, $values, $artifactTypes) !== null) {
                    return true;
                }
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, New_::class) as $new) {
            if ($new->class instanceof Variable && is_string($new->class->name) && isset($values[$new->class->name])) {
                return true;
            }
        }
        foreach (new NodeFinder()->findInstanceOf($nodes, StaticCall::class) as $call) {
            if (
                $call->class instanceof Variable
                && is_string($call->class->name)
                && isset($values[$call->class->name])
            ) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,string> $values @param array<string,bool> $artifactTypes */
    private function artifactValue(?Node $node, array $values, array $artifactTypes): ?string
    {
        if ($node instanceof String_) {
            if (isset($artifactTypes[$node->value])) {
                return $node->value;
            }
            if (isset($this->artifactCallbackTargets()[$node->value])) {
                return $node->value;
            }
            return null;
        }
        if ($node instanceof Variable && is_string($node->name)) {
            return $values[$node->name] ?? null;
        }
        if ($node instanceof Array_ && count($node->items) === 2) {
            $class = $node->items[0] ?? null;
            $method = $node->items[1] ?? null;
            if (
                $class instanceof ArrayItem
                && $method instanceof ArrayItem
                && $class->key === null
                && $method->key === null
                && $method->value instanceof String_
            ) {
                $className = $this->artifactValue($class->value, $values, $artifactTypes);
                if ($className === null) {
                    return null;
                }
                $target = $className . '::' . $method->value->value;
                return isset($this->artifactCallbackTargets()[$target]) ? $target : null;
            }
        }
        if (
            $node instanceof ClassConstFetch
            && $node->class instanceof Name
            && $node->name instanceof Identifier
            && strtolower($node->name->toString()) === 'class'
        ) {
            $name = $this->resolvedName($node->class, '');
            return isset($artifactTypes[$name]) ? $name : null;
        }
        return null;
    }

    /** @return array<string,true> */
    private function artifactCallbackTargets(): array
    {
        $targets = [];
        foreach ($this->expectedSignatures() as $type => $methods) {
            foreach ($methods as $method => $signature) {
                if ($signature['visibility'] === 'public' && $signature['static'] === true) {
                    $targets[$type . '::' . $method] = true;
                }
            }
        }
        return $targets;
    }

    private function insertBeforeFinalBrace(string $source, string $addition): string
    {
        $position = strrpos($source, '}');
        self::assertIsInt($position);
        return substr($source, 0, $position) . $addition . substr($source, $position);
    }

    private function replaceFirst(string $source, string $search, string $replacement): string
    {
        $firstPosition = strpos($source, $search);
        $lastPosition = strrpos($source, $search);
        self::assertIsInt($firstPosition, 'mutation target exists');
        self::assertSame($firstPosition, $lastPosition, 'mutation target is uniquely anchored');
        $mutated = substr_replace($source, $replacement, $firstPosition, strlen($search));
        self::assertSame(
            substr($source, 0, $firstPosition),
            substr($mutated, 0, $firstPosition),
            'mutation changes only its selected occurrence',
        );
        self::assertSame(
            substr($source, $firstPosition + strlen($search)),
            substr($mutated, $firstPosition + strlen($replacement)),
            'mutation preserves all later occurrences',
        );
        return $mutated;
    }

    private function swapFirst(string $source, string $first, string $second): string
    {
        $firstPosition = strpos($source, $first);
        $lastFirstPosition = strrpos($source, $first);
        $secondPosition = strpos($source, $second);
        $lastSecondPosition = strrpos($source, $second);
        self::assertIsInt($firstPosition);
        self::assertSame($firstPosition, $lastFirstPosition, 'first swap anchor is unique');
        self::assertIsInt($secondPosition);
        self::assertSame($secondPosition, $lastSecondPosition, 'second swap anchor is unique');
        self::assertTrue($firstPosition < $secondPosition);
        return substr_replace(
            substr_replace($source, $second, $firstPosition, strlen($first)),
            $first,
            $secondPosition - strlen($first) + strlen($second),
            strlen($second),
        );
    }

    /** @return array<string,list<string>> */
    private function expectedImports(): array
    {
        $imports = array_fill_keys(array_keys($this->sources()), []);
        $imports['AuditArtifactCanonical.php'] = ['BlackOps\\Internal\\Audit\\AuditJcsCodec'];
        $imports['AuditArtifactException.php'] = ['RuntimeException'];
        $imports['AuditArtifactScalar.php'] = [
            'BlackOps\\Internal\\Audit\\AuditJcsCodec',
            'BlackOps\\Internal\\Audit\\AuditStringValidator',
        ];
        $imports['AuditArtifactShape.php'] = ['BlackOps\\Core\\Audit\\AuditRecordTime'];
        $imports['AuditTrustEntryRelation.php'] = ['BlackOps\\Core\\Audit\\AuditRecordTime'];
        $imports['AuditTrustLookup.php'] = ['BlackOps\\Core\\Audit\\AuditRecordTime'];
        $imports['AuditTrustScalar.php'] = ['BlackOps\\Internal\\Audit\\AuditStringValidator'];
        $imports['AuditTrustSnapshotShape.php'] = ['BlackOps\\Core\\Audit\\AuditRecordTime'];
        foreach ($imports as &$values) {
            sort($values);
        }
        unset($values);
        return $imports;
    }

    /** @return array<string,array{return:array{type:?string,allowsNull:bool},visibility:string,static:bool,params:list<array{name:string,type:?string,allowsNull:bool}>}> */
    private function expectedSignatures(): array
    {
        $array = static fn(string $name): array => ['name' => $name, 'type' => 'array', 'allowsNull' => false];
        $string = static fn(string $name): array => ['name' => $name, 'type' => 'string', 'allowsNull' => false];
        $mixed = static fn(string $name): array => ['name' => $name, 'type' => 'mixed', 'allowsNull' => true];
        $integer = static fn(string $name): array => ['name' => $name, 'type' => 'int', 'allowsNull' => false];
        $method = static fn(
            string $return,
            string $visibility,
            bool $static,
            array $params,
            bool $returnNullable = false,
        ): array => [
            'return' => ['type' => $return, 'allowsNull' => $returnNullable],
            'visibility' => $visibility,
            'static' => $static,
            'params' => $params,
        ];
        $never = static fn(): array => $method('never', 'private', true, []);
        $void = static fn(string $visibility, bool $static, array $params): array => $method(
            'void',
            $visibility,
            $static,
            $params,
        );
        return [
            AuditAnchorBinding::class => ['assert' => $void('public', true, [
                    $array('unsigned'),
                    $array('checkpoint'),
                    $string('checkpointBytes'),
                ]), 'invalid' => $never()],
            AuditAnchorReceiptCodec::class => ['signingInput' => $method('array', 'public', true, [$array(
                    'unsigned',
                )]), 'complete' => $method('string', 'public', true, [
                    $array('unsigned'),
                    $string('rawSignature'),
                ]), 'decode' => $method('array', 'public', true, [$string(
                    'completeBytes',
                )]), 'assertCheckpointBinding' => $void('public', true, [
                    $array('unsigned'),
                    $string('checkpointBytes'),
                ]), 'frame' => $method('string', 'private', true, [
                    $string('domain'),
                    $string('bytes'),
                ]), 'invalid' => $never()],
            AuditArtifactCanonical::class => ['artifact' => $method('string', 'public', true, [$array(
                    'value',
                )]), 'trustSnapshot' => $method('string', 'public', true, [$array(
                    'value',
                )]), 'decode' => $method('array', 'public', true, [$string(
                    'bytes',
                )]), 'invalid' => $never(), 'trustInvalid' => $never()],
            AuditArtifactException::class => [
                '__construct' => [
                    'return' => ['type' => null, 'allowsNull' => false],
                    'visibility' => 'public',
                    'static' => false,
                    'params' => [['name' => 'failure', 'type' => AuditArtifactFailure::class, 'allowsNull' => false]],
                ],
                'failure' => $method(AuditArtifactFailure::class, 'public', false, []),
            ],
            AuditCheckpointCodec::class => [
                'signingInput' => $method('array', 'public', true, [$array('unsigned')]),
                'complete' => $method('string', 'public', true, [$array('unsigned'), $string('rawSignature')]),
                'decode' => $method('array', 'public', true, [$string('completeBytes')]),
                'bytesHash' => $method('string', 'public', true, [$string('completeBytes')]),
                'frame' => $method('string', 'private', true, [$string('domain'), $string('bytes')]),
                'invalid' => $never(),
            ],
            AuditArtifactIntegrity::class => ['checkpoint' => $void('public', true, [
                    $array('value'),
                    $string('bytes'),
                ]), 'anchor' => $void('public', true, [
                    $array('value'),
                    $string('bytes'),
                ]), 'signature' => $void('private', true, [$string(
                    'encoded',
                )]), 'frame' => $method('string', 'private', true, [
                    $string('domain'),
                    $string('bytes'),
                ]), 'invalid' => $never()],
            AuditArtifactRelation::class => ['checkpoint' => $void('public', true, [$array(
                    'value',
                )]), 'anchor' => $void('public', true, [$array('value')]), 'invalid' => $never()],
            AuditArtifactScalar::class => ['closed' => $method('array', 'public', true, [
                    $array('value'),
                    $array('keys'),
                ]), 'string' => $method('string', 'public', true, [$mixed(
                    'value',
                )]), 'nullableString' => $method('string', 'public', true, [$mixed('value')], true), 'boundedInteger' => $method('int', 'public', true, [$mixed(
                    'value',
                )]), 'invalid' => $never()],
            AuditArtifactShape::class => ['checkpointUnsigned' => $method('array', 'public', true, [$array(
                    'value',
                )]), 'checkpointComplete' => $method('array', 'public', true, [$array(
                    'value',
                )]), 'anchorUnsigned' => $method('array', 'public', true, [$array(
                    'value',
                )]), 'anchorComplete' => $method('array', 'public', true, [$array(
                    'value',
                )]), 'checkpointFields' => $method('array', 'private', true, [$array(
                    'value',
                )]), 'anchorFields' => $method('array', 'private', true, [$array(
                    'value',
                )]), 'partition' => $method('string', 'private', true, [$mixed(
                    'value',
                )]), 'segment' => $method('string', 'private', true, [$mixed(
                    'value',
                )]), 'digest' => $method('string', 'private', true, [$mixed('value')]), 'invalid' => $never()],
            AuditArtifactSigner::class => ['checkpoint' => $method('string', 'public', true, [
                    $array('unsigned'),
                    $string('privateSeed'),
                    $array('trustedSnapshot'),
                    $string('trustedNow'),
                ]), 'anchorReceipt' => $method('string', 'public', true, [
                    $array('unsigned'),
                    $string('checkpointBytes'),
                    $string('privateSeed'),
                    $array('trustedSnapshot'),
                    $string('trustedNow'),
                ]), 'assertSeedLength' => $void('private', true, [$string(
                    'privateSeed',
                )]), 'sign' => $method('string', 'private', true, [
                    $string('preimage'),
                    $string('privateSeed'),
                    $array('entry'),
                ])],
            AuditArtifactVerifier::class => ['checkpoint' => $void('public', true, [
                    $string('checkpointBytes'),
                    $array('trustedSnapshot'),
                    $string('verifiedAt'),
                ]), 'anchorReceipt' => $void('public', true, [
                    $string('receiptBytes'),
                    $string('checkpointBytes'),
                    $array('trustedSnapshot'),
                    $string('verifiedAt'),
                ]), 'verifyDetached' => $void('private', true, [
                    $array('artifact'),
                    $array('entry'),
                    $string('domain'),
                    $string('hashField'),
                ])],
            AuditArtifactWirePreflight::class => ['assertFlatObject' => $void('public', true, [$string(
                    'bytes',
                )]), 'invalid' => $never()],
            AuditTrustEntryRelation::class => ['assert' => $void('public', true, [
                    $array('entry'),
                    $string('observedAt'),
                    $string('verifiedAt'),
                ]), 'invalid' => $never()],
            AuditTrustEntryShape::class => ['entry' => $method('array', 'public', true, [
                    $mixed('value'),
                    $string('observedAt'),
                    $string('verifiedAt'),
                ]), 'invalid' => $never()],
            AuditTrustLookup::class => ['key' => $method('array', 'public', true, [
                    $array('snapshot'),
                    $string('keyRef'),
                    $string('expectedRole'),
                    $string('verifiedAt'),
                ]), 'assertSignedAt' => $void('public', true, [
                    $array('entry'),
                    $string('signedAt'),
                    $string('verifiedAt'),
                ]), 'assertCurrent' => $void('private', true, [
                    $array('entry'),
                    $string('verifiedAt'),
                ]), 'invalid' => $never()],
            AuditTrustScalar::class => ['closed' => $method('array', 'public', true, [
                    $array('value'),
                    $array('keys'),
                ]), 'string' => $method('string', 'public', true, [
                    $mixed('value'),
                    $integer('maximum'),
                ]), 'publicKey' => $method('string', 'public', true, [$mixed('value')]), 'invalid' => $never()],
            AuditTrustSnapshotCodec::class => ['validate' => $void('public', true, [
                    $array('snapshot'),
                    $string('verifiedAt'),
                ]), 'key' => $method('array', 'public', true, [
                    $array('snapshot'),
                    $string('keyRef'),
                    $string('expectedRole'),
                    $string('verifiedAt'),
                ]), 'assertSignedAt' => $void('public', true, [
                    $array('entry'),
                    $string('signedAt'),
                    $string('verifiedAt'),
                ]), 'frame' => $method('string', 'private', true, [
                    $string('domain'),
                    $string('bytes'),
                ]), 'invalid' => $never()],
            AuditTrustSnapshotShape::class => ['snapshot' => $method('array', 'public', true, [
                    $array('value'),
                    $string('verifiedAt'),
                ]), 'invalid' => $never()],
        ];
    }

    /** @return array<string,list<string>> */
    private function expectedOperations(): array
    {
        $s = fn(string $type, string $method): string => $this->staticOperation($type, $method);
        $f = static fn(string $name): string => 'free:' . $name;
        $n = static fn(string $type): string => 'new:' . $type;
        $c = static fn(string $type, string $name): string => 'const:' . $type . '::' . $name;
        $g = static fn(string $name): string => 'const:' . $name;
        $m = static fn(string $receiver, string $name): string => 'method:' . $receiver . '::' . $name;
        $exception = $n(AuditArtifactException::class);
        $invalid = static fn(string $failure): array => [$exception, $failure];
        $artifactFailure = $c(AuditArtifactFailure::class, 'ArtifactInvalid');
        $trustFailure = $c(AuditArtifactFailure::class, 'TrustInvalid');
        $signatureFailure = $c(AuditArtifactFailure::class, 'SignatureInvalid');
        $signingFailure = $c(AuditArtifactFailure::class, 'SigningUnavailable');
        return [
            AuditAnchorBinding::class . '::assert' => [
                $f('hash_equals'),
                $f('hash_equals'),
                $s(AuditAnchorBinding::class, 'invalid'),
                $s(AuditCheckpointCodec::class, 'bytesHash'),
                $s(AuditAnchorBinding::class, 'invalid'),
            ],
            AuditAnchorBinding::class . '::invalid' => $invalid($artifactFailure),
            AuditCheckpointCodec::class . '::signingInput' => [
                $s(AuditArtifactShape::class, 'checkpointUnsigned'),
                $s(AuditArtifactRelation::class, 'checkpoint'),
                $s(AuditArtifactCanonical::class, 'artifact'),
                $f('hash'),
                $s(AuditCheckpointCodec::class, 'frame'),
                $f('sodium_hex2bin'),
                $s(AuditCheckpointCodec::class, 'frame'),
            ],
            AuditCheckpointCodec::class . '::complete' => [
                $s(AuditCheckpointCodec::class, 'signingInput'),
                $f('strlen'),
                $g('SODIUM_CRYPTO_SIGN_BYTES'),
                $s(AuditCheckpointCodec::class, 'invalid'),
                $s(AuditArtifactShape::class, 'checkpointUnsigned'),
                $f('sodium_bin2base64'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $s(AuditArtifactCanonical::class, 'artifact'),
                $s(AuditCheckpointCodec::class, 'decode'),
            ],
            AuditCheckpointCodec::class . '::decode' => [
                $s(AuditArtifactCanonical::class, 'decode'),
                $s(AuditArtifactShape::class, 'checkpointComplete'),
                $s(AuditArtifactRelation::class, 'checkpoint'),
                $s(AuditArtifactIntegrity::class, 'checkpoint'),
            ],
            AuditCheckpointCodec::class . '::bytesHash' => [
                $s(AuditCheckpointCodec::class, 'decode'),
                $f('hash'),
                $s(AuditCheckpointCodec::class, 'frame'),
            ],
            AuditCheckpointCodec::class . '::frame' => [$f('strlen'), $f('strlen')],
            AuditCheckpointCodec::class . '::invalid' => $invalid($artifactFailure),
            AuditAnchorReceiptCodec::class . '::signingInput' => [
                $s(AuditArtifactShape::class, 'anchorUnsigned'),
                $s(AuditArtifactRelation::class, 'anchor'),
                $s(AuditArtifactCanonical::class, 'artifact'),
                $f('hash'),
                $s(AuditAnchorReceiptCodec::class, 'frame'),
                $f('sodium_hex2bin'),
                $s(AuditAnchorReceiptCodec::class, 'frame'),
            ],
            AuditAnchorReceiptCodec::class . '::complete' => [
                $s(AuditAnchorReceiptCodec::class, 'signingInput'),
                $f('strlen'),
                $g('SODIUM_CRYPTO_SIGN_BYTES'),
                $s(AuditAnchorReceiptCodec::class, 'invalid'),
                $s(AuditArtifactShape::class, 'anchorUnsigned'),
                $f('sodium_bin2base64'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $s(AuditArtifactCanonical::class, 'artifact'),
                $s(AuditAnchorReceiptCodec::class, 'decode'),
            ],
            AuditAnchorReceiptCodec::class . '::decode' => [
                $s(AuditArtifactCanonical::class, 'decode'),
                $s(AuditArtifactShape::class, 'anchorComplete'),
                $s(AuditArtifactRelation::class, 'anchor'),
                $s(AuditArtifactIntegrity::class, 'anchor'),
            ],
            AuditAnchorReceiptCodec::class . '::assertCheckpointBinding' => [
                $s(AuditArtifactShape::class, 'anchorUnsigned'),
                $s(AuditArtifactRelation::class, 'anchor'),
                $s(AuditCheckpointCodec::class, 'decode'),
                $s(AuditAnchorBinding::class, 'assert'),
            ],
            AuditAnchorReceiptCodec::class . '::frame' => [$f('strlen'), $f('strlen')],
            AuditAnchorReceiptCodec::class . '::invalid' => $invalid($artifactFailure),
            AuditArtifactCanonical::class . '::artifact' => [
                $n('BlackOps\\Internal\\Audit\\AuditJcsCodec'),
                $m('new:BlackOps\\Internal\\Audit\\AuditJcsCodec', 'canonicalJson'),
                $f('strlen'),
                $s(AuditArtifactCanonical::class, 'invalid'),
                $s(AuditArtifactCanonical::class, 'invalid'),
            ],
            AuditArtifactCanonical::class . '::trustSnapshot' => [
                $n('BlackOps\\Internal\\Audit\\AuditJcsCodec'),
                $m('new:BlackOps\\Internal\\Audit\\AuditJcsCodec', 'canonicalJson'),
                $f('strlen'),
                $s(AuditArtifactCanonical::class, 'trustInvalid'),
                $s(AuditArtifactCanonical::class, 'trustInvalid'),
            ],
            AuditArtifactCanonical::class . '::decode' => [
                $s(AuditArtifactWirePreflight::class, 'assertFlatObject'),
                $n('BlackOps\\Internal\\Audit\\AuditJcsCodec'),
                $m('var:$jcs', 'decode'),
                $m('var:$jcs', 'toNative'),
                $f('is_array'),
                $f('array_is_list'),
                $s(AuditArtifactCanonical::class, 'invalid'),
                $s(AuditArtifactCanonical::class, 'invalid'),
            ],
            AuditArtifactCanonical::class . '::invalid' => $invalid($artifactFailure),
            AuditArtifactCanonical::class . '::trustInvalid' => [$exception, $trustFailure],
            AuditArtifactException::class . '::__construct' => [$s('RuntimeException', '__construct')],
            AuditArtifactException::class . '::failure' => [],
            AuditArtifactIntegrity::class . '::checkpoint' => [
                $s(AuditArtifactCanonical::class, 'artifact'),
                $s(AuditArtifactCanonical::class, 'artifact'),
                $f('hash'),
                $s(AuditArtifactIntegrity::class, 'frame'),
                $f('hash_equals'),
                $s(AuditArtifactIntegrity::class, 'signature'),
                $s(AuditArtifactIntegrity::class, 'invalid'),
                $s(AuditArtifactIntegrity::class, 'invalid'),
            ],
            AuditArtifactIntegrity::class . '::anchor' => [
                $s(AuditArtifactCanonical::class, 'artifact'),
                $s(AuditArtifactCanonical::class, 'artifact'),
                $f('hash'),
                $s(AuditArtifactIntegrity::class, 'frame'),
                $f('hash_equals'),
                $s(AuditArtifactIntegrity::class, 'signature'),
                $s(AuditArtifactIntegrity::class, 'invalid'),
                $s(AuditArtifactIntegrity::class, 'invalid'),
            ],
            AuditArtifactIntegrity::class . '::signature' => [
                $f('sodium_base642bin'),
                $f('strlen'),
                $f('sodium_bin2base64'),
                $s(AuditArtifactIntegrity::class, 'invalid'),
                $s(AuditArtifactIntegrity::class, 'invalid'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $g('SODIUM_CRYPTO_SIGN_BYTES'),
            ],
            AuditArtifactIntegrity::class . '::frame' => [$f('strlen'), $f('strlen')],
            AuditArtifactIntegrity::class . '::invalid' => $invalid($artifactFailure),
            AuditArtifactRelation::class . '::checkpoint' => array_merge(
                [$f('hash_equals')],
                array_fill(0, 5, $s(AuditArtifactRelation::class, 'invalid')),
            ),
            AuditArtifactRelation::class . '::anchor' => array_fill(0, 2, $s(AuditArtifactRelation::class, 'invalid')),
            AuditArtifactRelation::class . '::invalid' => $invalid($artifactFailure),
            AuditArtifactScalar::class . '::closed' => [
                $f('array_is_list'),
                $f('count'),
                $f('count'),
                $f('array_keys'),
                $f('array_keys'),
                $f('array_keys'),
                $f('is_string'),
                $f('array_diff'),
                $f('array_diff'),
                $s(AuditArtifactScalar::class, 'invalid'),
                $s(AuditArtifactScalar::class, 'invalid'),
                $s(AuditArtifactScalar::class, 'invalid'),
            ],
            AuditArtifactScalar::class . '::string' => [
                $f('is_string'),
                $f('strlen'),
                $f('strlen'),
                $s(AuditArtifactScalar::class, 'invalid'),
                $s(AuditArtifactScalar::class, 'invalid'),
                $s('BlackOps\\Internal\\Audit\\AuditStringValidator', 'assertSafe'),
            ],
            AuditArtifactScalar::class . '::nullableString' => [$s(AuditArtifactScalar::class, 'string')],
            AuditArtifactScalar::class . '::boundedInteger' => [
                $f('is_int'),
                $s(AuditArtifactScalar::class, 'invalid'),
                $c('BlackOps\\Internal\\Audit\\AuditJcsCodec', 'MAX_SAFE_INTEGER'),
            ],
            AuditArtifactScalar::class . '::invalid' => $invalid($artifactFailure),
            AuditArtifactShape::class . '::checkpointUnsigned' => [
                $s(AuditArtifactScalar::class, 'closed'),
                $s(AuditArtifactShape::class, 'checkpointFields'),
                $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp'),
                $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp'),
                $s(AuditArtifactShape::class, 'invalid'),
                $s(AuditArtifactShape::class, 'invalid'),
                $s(AuditArtifactShape::class, 'invalid'),
            ],
            AuditArtifactShape::class . '::checkpointComplete' => [
                $s(AuditArtifactScalar::class, 'closed'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactScalar::class, 'string'),
                $s(AuditArtifactShape::class, 'checkpointUnsigned'),
            ],
            AuditArtifactShape::class . '::anchorUnsigned' => [
                $s(AuditArtifactScalar::class, 'closed'),
                $s(AuditArtifactShape::class, 'anchorFields'),
                $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp'),
                $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp'),
                $s(AuditArtifactShape::class, 'invalid'),
                $s(AuditArtifactShape::class, 'invalid'),
                $s(AuditArtifactShape::class, 'invalid'),
            ],
            AuditArtifactShape::class . '::anchorComplete' => [
                $s(AuditArtifactScalar::class, 'closed'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactScalar::class, 'string'),
                $s(AuditArtifactShape::class, 'anchorUnsigned'),
            ],
            AuditArtifactShape::class . '::checkpointFields' => [
                $s(AuditArtifactScalar::class, 'nullableString'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactShape::class, 'partition'),
                $s(AuditArtifactShape::class, 'segment'),
                ...array_fill(0, 4, $s(AuditArtifactScalar::class, 'boundedInteger')),
                ...array_fill(0, 7, $s(AuditArtifactScalar::class, 'string')),
            ],
            AuditArtifactShape::class . '::anchorFields' => [
                $s(AuditArtifactScalar::class, 'nullableString'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactShape::class, 'digest'),
                $s(AuditArtifactShape::class, 'partition'),
                $s(AuditArtifactShape::class, 'segment'),
                ...array_fill(0, 2, $s(AuditArtifactScalar::class, 'boundedInteger')),
                ...array_fill(0, 9, $s(AuditArtifactScalar::class, 'string')),
            ],
            AuditArtifactShape::class . '::partition' => [
                $s(AuditArtifactScalar::class, 'string'),
                $f('preg_match'),
                $s(AuditArtifactShape::class, 'invalid'),
            ],
            AuditArtifactShape::class . '::segment' => [
                $s(AuditArtifactScalar::class, 'string'),
                $f('preg_match'),
                $s(AuditArtifactShape::class, 'invalid'),
            ],
            AuditArtifactShape::class . '::digest' => [
                $s(AuditArtifactScalar::class, 'string'),
                $f('preg_match'),
                $s(AuditArtifactShape::class, 'invalid'),
            ],
            AuditArtifactShape::class . '::invalid' => $invalid($artifactFailure),
            AuditArtifactSigner::class . '::checkpoint' => [
                $s(AuditCheckpointCodec::class, 'signingInput'),
                $s(AuditArtifactShape::class, 'checkpointUnsigned'),
                $s(AuditTrustSnapshotCodec::class, 'key'),
                $s(AuditTrustSnapshotCodec::class, 'assertSignedAt'),
                $s(AuditArtifactSigner::class, 'assertSeedLength'),
                $s(AuditArtifactSigner::class, 'sign'),
                $s(AuditCheckpointCodec::class, 'complete'),
            ],
            AuditArtifactSigner::class . '::anchorReceipt' => [
                $s(AuditAnchorReceiptCodec::class, 'signingInput'),
                $s(AuditArtifactShape::class, 'anchorUnsigned'),
                $s(AuditAnchorReceiptCodec::class, 'assertCheckpointBinding'),
                $s(AuditArtifactVerifier::class, 'checkpoint'),
                $s(AuditTrustSnapshotCodec::class, 'key'),
                $s(AuditTrustSnapshotCodec::class, 'assertSignedAt'),
                $s(AuditArtifactSigner::class, 'assertSeedLength'),
                $s(AuditArtifactSigner::class, 'sign'),
                $s(AuditAnchorReceiptCodec::class, 'complete'),
            ],
            AuditArtifactSigner::class . '::assertSeedLength' => [
                $f('strlen'),
                $g('SODIUM_CRYPTO_SIGN_SEEDBYTES'),
                $exception,
                $signingFailure,
            ],
            AuditArtifactSigner::class . '::sign' => [
                $f('sodium_base642bin'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $f('sodium_crypto_sign_seed_keypair'),
                $f('sodium_crypto_sign_secretkey'),
                $f('sodium_crypto_sign_publickey'),
                $f('hash_equals'),
                $f('sodium_crypto_sign_detached'),
                $f('strlen'),
                $g('SODIUM_CRYPTO_SIGN_BYTES'),
                ...array_fill(0, 2, $f('sodium_memzero')),
                ...array_fill(0, 3, $exception),
                ...array_fill(0, 3, $signingFailure),
            ],
            AuditArtifactVerifier::class . '::checkpoint' => [
                $s(AuditCheckpointCodec::class, 'decode'),
                $s(AuditTrustSnapshotCodec::class, 'key'),
                $s(AuditArtifactVerifier::class, 'verifyDetached'),
                $s(AuditTrustSnapshotCodec::class, 'assertSignedAt'),
            ],
            AuditArtifactVerifier::class . '::anchorReceipt' => [
                $s(AuditAnchorReceiptCodec::class, 'decode'),
                $s(AuditCheckpointCodec::class, 'decode'),
                $s(AuditAnchorReceiptCodec::class, 'assertCheckpointBinding'),
                $s(AuditArtifactVerifier::class, 'checkpoint'),
                $s(AuditTrustSnapshotCodec::class, 'key'),
                $s(AuditArtifactVerifier::class, 'verifyDetached'),
                $s(AuditTrustSnapshotCodec::class, 'assertSignedAt'),
            ],
            AuditArtifactVerifier::class . '::verifyDetached' => [
                $f('is_string'),
                $f('is_string'),
                $n('RuntimeException'),
                $n('RuntimeException'),
                $f('hex2bin'),
                $f('sodium_base642bin'),
                $f('sodium_base642bin'),
                $f('strlen'),
                $f('strlen'),
                $f('strlen'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING'),
                $g('SODIUM_CRYPTO_SIGN_BYTES'),
                $g('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES'),
                $f('sodium_crypto_sign_verify_detached'),
                $n(AuditArtifactException::class),
                $signatureFailure,
                $n('RuntimeException'),
                $f('strlen'),
                $f('strlen'),
            ],
            AuditArtifactWirePreflight::class . '::assertFlatObject' => [
                $f('strlen'),
                ...array_fill(0, 4, $s(AuditArtifactWirePreflight::class, 'invalid')),
            ],
            AuditArtifactWirePreflight::class . '::invalid' => $invalid($artifactFailure),
            AuditTrustEntryRelation::class . '::assert' => [
                ...array_fill(0, 5, $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp')),
                ...array_fill(0, 2, $s(AuditTrustEntryRelation::class, 'invalid')),
            ],
            AuditTrustEntryRelation::class . '::invalid' => $invalid($trustFailure),
            AuditTrustEntryShape::class . '::entry' => [
                $f('is_array'),
                $s(AuditTrustEntryShape::class, 'invalid'),
                $s(AuditTrustScalar::class, 'closed'),
                ...array_fill(0, 8, $s(AuditTrustScalar::class, 'string')),
                $s(AuditTrustScalar::class, 'publicKey'),
                $f('in_array'),
                $f('in_array'),
                $f('preg_match'),
                $f('hash'),
                $f('hash_equals'),
                $s(AuditTrustEntryShape::class, 'invalid'),
            ],
            AuditTrustEntryShape::class . '::invalid' => $invalid($trustFailure),
            AuditTrustLookup::class . '::key' => [
                $s(AuditTrustSnapshotCodec::class, 'validate'),
                $s(AuditTrustSnapshotShape::class, 'snapshot'),
                ...array_fill(0, 2, $s(AuditTrustScalar::class, 'string')),
                $f('in_array'),
                ...array_fill(0, 3, $s(AuditTrustLookup::class, 'invalid')),
                $s(AuditTrustLookup::class, 'assertCurrent'),
            ],
            AuditTrustLookup::class . '::assertSignedAt' => [
                ...array_fill(0, 2, $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp')),
                ...array_fill(0, 2, $s(AuditTrustLookup::class, 'invalid')),
                $s(AuditTrustEntryShape::class, 'entry'),
                $s(AuditTrustEntryRelation::class, 'assert'),
                $s(AuditTrustLookup::class, 'assertCurrent'),
            ],
            AuditTrustLookup::class . '::assertCurrent' => [$s(AuditTrustLookup::class, 'invalid')],
            AuditTrustLookup::class . '::invalid' => $invalid($trustFailure),
            AuditTrustScalar::class . '::closed' => [
                $f('array_is_list'),
                $f('count'),
                $f('count'),
                ...array_fill(0, 3, $f('array_keys')),
                $f('is_string'),
                $f('array_diff'),
                $f('array_diff'),
                ...array_fill(0, 3, $s(AuditTrustScalar::class, 'invalid')),
            ],
            AuditTrustScalar::class . '::string' => [
                $f('is_string'),
                $f('strlen'),
                $f('strlen'),
                ...array_fill(0, 2, $s(AuditTrustScalar::class, 'invalid')),
                $s('BlackOps\\Internal\\Audit\\AuditStringValidator', 'assertSafe'),
            ],
            AuditTrustScalar::class . '::publicKey' => [
                $f('is_string'),
                $f('strlen'),
                ...array_fill(0, 3, $s(AuditTrustScalar::class, 'invalid')),
                $f('sodium_base642bin'),
                $f('strlen'),
                $f('sodium_bin2base64'),
                ...array_fill(0, 2, $g('SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING')),
                $g('SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES'),
            ],
            AuditTrustScalar::class . '::invalid' => $invalid($trustFailure),
            AuditTrustSnapshotCodec::class . '::validate' => [
                $s(AuditTrustSnapshotShape::class, 'snapshot'),
                $s(AuditTrustSnapshotCodec::class, 'invalid'),
                $f('hash'),
                $s(AuditTrustSnapshotCodec::class, 'frame'),
                $s(AuditArtifactCanonical::class, 'trustSnapshot'),
                $f('hash_equals'),
                $s(AuditTrustSnapshotCodec::class, 'invalid'),
            ],
            AuditTrustSnapshotCodec::class . '::key' => [$s(AuditTrustLookup::class, 'key')],
            AuditTrustSnapshotCodec::class . '::assertSignedAt' => [$s(AuditTrustLookup::class, 'assertSignedAt')],
            AuditTrustSnapshotCodec::class . '::frame' => [$f('strlen'), $f('strlen')],
            AuditTrustSnapshotCodec::class . '::invalid' => $invalid($trustFailure),
            AuditTrustSnapshotShape::class . '::snapshot' => [
                $s(AuditTrustScalar::class, 'closed'),
                ...array_fill(0, 4, $s(AuditTrustScalar::class, 'string')),
                $f('is_array'),
                $f('array_is_list'),
                $f('count'),
                $f('count'),
                $s(AuditTrustSnapshotShape::class, 'invalid'),
                ...array_fill(0, 3, $s('BlackOps\\Core\\Audit\\AuditRecordTime', 'assertTimestamp')),
                $s(AuditTrustSnapshotShape::class, 'invalid'),
                $f('preg_match'),
                $s(AuditTrustEntryShape::class, 'entry'),
                $s(AuditTrustEntryRelation::class, 'assert'),
                $f('strcmp'),
                $f('array_key_exists'),
                $f('array_key_exists'),
                $s(AuditTrustSnapshotShape::class, 'invalid'),
                $s(AuditTrustSnapshotShape::class, 'invalid'),
            ],
            AuditTrustSnapshotShape::class . '::invalid' => $invalid($trustFailure),
        ];
    }

    /** @return array<string,list<array{target:string,index:int,values:list<string>}>> */
    private function fixedArguments(): array
    {
        $frame = static fn(string $owner, array $values): array => [
            'target' => $owner . '::frame',
            'index' => 0,
            'values' => $values,
        ];
        return [
            AuditCheckpointCodec::class . '::signingInput' => [$frame(AuditCheckpointCodec::class, [
                'blackops.audit.checkpoint.hash.v1',
                'blackops.audit.checkpoint.signature.v1',
            ])],
            AuditCheckpointCodec::class . '::bytesHash' => [$frame(AuditCheckpointCodec::class, [
                'blackops.audit.anchor.checkpoint-bytes.v1',
            ])],
            AuditAnchorReceiptCodec::class . '::signingInput' => [$frame(AuditAnchorReceiptCodec::class, [
                'blackops.audit.anchor.hash.v1',
                'blackops.audit.anchor.signature.v1',
            ])],
            AuditArtifactIntegrity::class . '::checkpoint' => [$frame(AuditArtifactIntegrity::class, [
                'blackops.audit.checkpoint.hash.v1',
            ])],
            AuditArtifactIntegrity::class . '::anchor' => [$frame(AuditArtifactIntegrity::class, [
                'blackops.audit.anchor.hash.v1',
            ])],
            AuditTrustSnapshotCodec::class . '::validate' => [$frame(AuditTrustSnapshotCodec::class, [
                'blackops.audit.trust-store-snapshot.v1',
            ])],
            AuditArtifactSigner::class . '::checkpoint' => [[
                'target' => AuditTrustSnapshotCodec::class . '::key',
                'index' => 2,
                'values' => ['checkpoint'],
            ]],
            AuditArtifactSigner::class . '::anchorReceipt' => [[
                'target' => AuditTrustSnapshotCodec::class . '::key',
                'index' => 2,
                'values' => ['anchor'],
            ]],
            AuditArtifactVerifier::class . '::checkpoint' => [
                ['target' => AuditTrustSnapshotCodec::class . '::key', 'index' => 2, 'values' => ['checkpoint']],
                [
                    'target' => AuditArtifactVerifier::class . '::verifyDetached',
                    'index' => 2,
                    'values' => ['blackops.audit.checkpoint.signature.v1'],
                ],
                [
                    'target' => AuditArtifactVerifier::class . '::verifyDetached',
                    'index' => 3,
                    'values' => ['checkpointHash'],
                ],
            ],
            AuditArtifactVerifier::class . '::anchorReceipt' => [
                ['target' => AuditTrustSnapshotCodec::class . '::key', 'index' => 2, 'values' => ['anchor']],
                [
                    'target' => AuditArtifactVerifier::class . '::verifyDetached',
                    'index' => 2,
                    'values' => ['blackops.audit.anchor.signature.v1'],
                ],
                [
                    'target' => AuditArtifactVerifier::class . '::verifyDetached',
                    'index' => 3,
                    'values' => ['anchorReceiptHash'],
                ],
            ],
        ];
    }

    /** @return list<class-string> */
    private function types(): array
    {
        return [
            AuditArtifactFailure::class,
            AuditArtifactException::class,
            AuditCheckpointCodec::class,
            AuditAnchorReceiptCodec::class,
            AuditTrustSnapshotCodec::class,
            AuditArtifactSigner::class,
            AuditArtifactVerifier::class,
            AuditAnchorBinding::class,
            AuditArtifactCanonical::class,
            AuditArtifactIntegrity::class,
            AuditArtifactRelation::class,
            AuditArtifactScalar::class,
            AuditArtifactShape::class,
            AuditArtifactWirePreflight::class,
            AuditTrustEntryRelation::class,
            AuditTrustEntryShape::class,
            AuditTrustLookup::class,
            AuditTrustScalar::class,
            AuditTrustSnapshotShape::class,
        ];
    }

    /** @return array<string,string> */
    private function sources(): array
    {
        $sources = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SOURCE_ROOT));
        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            $relative = substr($path, strlen(self::SOURCE_ROOT) + 1);
            self::assertIsString($relative);
            $source = file_get_contents($path);
            self::assertIsString($source);
            $sources[$relative] = $source;
        }
        ksort($sources);
        return $sources;
    }

    /** @return array<string,string> */
    private function typeForFileMap(): array
    {
        $map = [];
        foreach ($this->types() as $type) {
            $map[substr($type, strrpos($type, '\\') + 1) . '.php'] = $type;
        }
        return $map;
    }

    private function typeForFile(string $file): string
    {
        $map = $this->typeForFileMap();
        self::assertArrayHasKey($file, $map);
        return $map[$file];
    }
}
