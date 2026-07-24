<?php

declare(strict_types=1);

namespace App\Tests\Board;

use BlackOps\Core\Execution\Deferred;
use BlackOps\Core\Execution\Inline;
use PHPUnit\Framework\TestCase;

final class BoardBuildArtifactTest extends TestCase
{
    /** @var list<string> */
    private const array TYPES = [
        'board.comment.add',
        'board.digest.show',
        'board.digest.weekly.generate',
        'board.notification.list',
        'board.post.create',
        'board.post.delete',
        'board.post.list',
        'board.post.show',
        'board.post.update',
    ];

    public function testOperationHttpAndFrontendArtifactsContainTheCanonicalOperations(): void
    {
        $build = dirname(__DIR__, 2) . '/var/build';
        $operations = $this->artifact($build . '/operations.php');
        $http = $this->artifact($build . '/http.php');
        $frontend = $this->artifact($build . '/frontend.php');

        $operationMetadata = $this->operationsByType($operations);
        $frontendMetadata = $this->operationsByType($frontend);
        self::assertSame(self::TYPES, array_values(array_intersect(array_keys($operationMetadata), self::TYPES)));
        self::assertSame(self::TYPES, array_values(array_intersect(array_keys($frontendMetadata), self::TYPES)));

        foreach (array_diff(self::TYPES, ['board.digest.weekly.generate']) as $type) {
            self::assertSame(Inline::class, $operationMetadata[$type]['strategy']);
            self::assertSame('inline', $frontendMetadata[$type]['strategy']);
        }
        self::assertSame(Deferred::class, $operationMetadata['board.digest.weekly.generate']['strategy']);
        self::assertSame('deferred', $frontendMetadata['board.digest.weekly.generate']['strategy']);
        self::assertArrayHasKey('board.notification.notify', $operationMetadata);
        self::assertSame(Deferred::class, $operationMetadata['board.notification.notify']['strategy']);
        foreach (['board.comment.add', 'board.post.create', 'board.post.delete', 'board.post.update'] as $type) {
            self::assertSame('app', $operationMetadata[$type]['transactionConnection']);
            self::assertTrue($operationMetadata[$type]['typedSelfHandledContext']);
        }

        self::assertSame('board.post.list', $http['payload']['routes']['GET']['/posts']);
        self::assertSame('board.post.show', $http['payload']['routes']['GET']['/posts/{postId}']);
        self::assertSame('board.post.create', $http['payload']['routes']['POST']['/posts']);
        self::assertSame('board.comment.add', $http['payload']['routes']['POST']['/posts/{postId}/comments']);
        self::assertSame('board.post.update', $http['payload']['routes']['PUT']['/posts/{postId}']);
        self::assertSame('board.post.delete', $http['payload']['routes']['DELETE']['/posts/{postId}']);
        self::assertSame('board.digest.show', $http['payload']['routes']['GET']['/digests/{digestId}']);
        self::assertSame('board.digest.weekly.generate', $http['payload']['routes']['POST']['/digests']);
        self::assertSame('board.notification.list', $http['payload']['routes']['GET']['/notifications']);

        self::assertSame('app', $operationMetadata['board.digest.weekly.generate']['transactionConnection']);
        self::assertTrue($operationMetadata['board.digest.weekly.generate']['typedSelfHandledContext']);

        $listFields = $this->fieldsByName($frontendMetadata['board.post.list']['outcome']['fields']);
        self::assertSame('list', $listFields['posts']['type']['kind']);
        self::assertSame('App\\Feature\\Post\\PostSummary', $listFields['posts']['type']['class']);
        $showFields = $this->fieldsByName($frontendMetadata['board.post.show']['outcome']['fields']);
        self::assertSame('dto', $showFields['post']['type']['kind']);
        self::assertSame('list', $showFields['comments']['type']['kind']);
    }

    /** @return array<string, mixed> */
    private function artifact(string $path): array
    {
        self::assertFileExists($path);
        $artifact = require $path;
        self::assertIsArray($artifact);

        return $artifact;
    }

    /**
     * @param array<string, mixed> $artifact
     * @return array<string, array<string, mixed>>
     */
    private function operationsByType(array $artifact): array
    {
        $operations = $artifact['payload']['operations'] ?? null;
        self::assertIsArray($operations);
        $indexed = [];
        foreach ($operations as $operation) {
            self::assertIsArray($operation);
            $type = $operation['typeId'] ?? null;
            self::assertIsString($type);
            $indexed[$type] = $operation;
        }
        ksort($indexed);

        return $indexed;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    private function fieldsByName(array $fields): array
    {
        $indexed = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? null;
            self::assertIsString($name);
            $indexed[$name] = $field;
        }

        return $indexed;
    }
}
