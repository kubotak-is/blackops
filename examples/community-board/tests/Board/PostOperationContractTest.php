<?php

declare(strict_types=1);

namespace App\Tests\Board;

use App\Domain\Board\BoardService;
use App\Feature\Comment\AddComment\AddComment;
use App\Feature\Comment\AddComment\AddCommentValue;
use App\Feature\Comment\CommentDetail;
use App\Feature\Post\CreatePost\CreatePost;
use App\Feature\Post\CreatePost\CreatePostValue;
use App\Feature\Post\DeletePost\DeletePost;
use App\Feature\Post\DeletePost\DeletePostValue;
use App\Feature\Post\ListPosts\ListPostsOutcome;
use App\Feature\Post\ListPosts\ListPostsValue;
use App\Feature\Post\PostDetail;
use App\Feature\Post\PostSummary;
use App\Feature\Post\ShowPost\ShowPost;
use App\Feature\Post\ShowPost\ShowPostOutcome;
use App\Feature\Post\ShowPost\ShowPostValue;
use App\Feature\Post\UpdatePost\UpdatePost;
use App\Feature\Post\UpdatePost\UpdatePostValue;
use App\Tests\Support\FrozenBoardClock;
use App\Tests\Support\InMemoryBoardRepository;
use App\Tests\Support\SequenceBoardIdGenerator;
use BlackOps\Core\ActorContext;
use BlackOps\Core\ActorRef;
use BlackOps\Core\Attribute\ListOf;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Identifier\CorrelationId;
use BlackOps\Core\Identifier\OperationId;
use BlackOps\Core\Operation;
use BlackOps\Core\OperationValue;
use BlackOps\Core\OutcomeData;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Core\Validation\Attribute\Range;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromPath;
use BlackOps\Http\Attribute\FromQuery;
use BlackOps\Execution\DispatchReceipt;
use BlackOps\Execution\Operations;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class PostOperationContractTest extends TestCase
{
    public function testValidationBindingDefaultsAndLimitsMatchThePublicContract(): void
    {
        $page = new ReflectionProperty(ListPostsValue::class, 'page');
        $perPage = new ReflectionProperty(ListPostsValue::class, 'perPage');
        self::assertCount(1, $page->getAttributes(FromQuery::class));
        self::assertEquals(new Range(min: 1, max: 10000), $page->getAttributes(Range::class)[0]->newInstance());
        self::assertCount(1, $perPage->getAttributes(FromQuery::class));
        self::assertEquals(new Range(min: 1, max: 50), $perPage->getAttributes(Range::class)[0]->newInstance());
        self::assertSame(1, new ListPostsValue()->page);
        self::assertSame(20, new ListPostsValue()->perPage);

        foreach ([
            [CreatePostValue::class, 'title', 120,   FromBody::class],
            [CreatePostValue::class, 'body',  10000, FromBody::class],
            [UpdatePostValue::class, 'title', 120,   FromBody::class],
            [UpdatePostValue::class, 'body',  10000, FromBody::class],
            [AddCommentValue::class, 'body',  2000,  FromBody::class],
        ] as [$class, $property, $maximum, $source]) {
            $reflection = new ReflectionProperty($class, $property);
            self::assertCount(1, $reflection->getAttributes($source));
            self::assertCount(1, $reflection->getAttributes(NotBlank::class));
            self::assertEquals(
                new Length(min: 1, max: $maximum),
                $reflection->getAttributes(Length::class)[0]->newInstance(),
            );
        }

        foreach ([
            [ShowPostValue::class,   'postId'],
            [UpdatePostValue::class, 'postId'],
            [DeletePostValue::class, 'postId'],
            [AddCommentValue::class, 'postId'],
        ] as [$class, $property]) {
            self::assertCount(1, new ReflectionProperty($class, $property)->getAttributes(FromPath::class));
        }
    }

    public function testStructuredOutcomeUsesFinalReadonlyBoundaryDtoAndTypedLists(): void
    {
        foreach ([PostSummary::class, PostDetail::class, CommentDetail::class] as $class) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            self::assertTrue($reflection->implementsInterface(OutcomeData::class));
        }

        $posts = new ReflectionProperty(ListPostsOutcome::class, 'posts');
        $comments = new ReflectionProperty(ShowPostOutcome::class, 'comments');
        self::assertSame(PostSummary::class, $posts->getAttributes(ListOf::class)[0]->newInstance()->type);
        self::assertSame(CommentDetail::class, $comments->getAttributes(ListOf::class)[0]->newInstance()->type);
    }

    public function testOperationsAreThinBoardServiceBoundariesAndMutationsRemainTransactional(): void
    {
        foreach ([CreatePost::class, UpdatePost::class, DeletePost::class, AddComment::class] as $class) {
            $reflection = new ReflectionClass($class);
            self::assertFalse($reflection->isFinal());
            self::assertSame(
                BoardService::class,
                $reflection->getConstructor()?->getParameters()[0]->getType()?->__toString(),
            );
            self::assertCount(1, $reflection->getMethod('handle')->getAttributes(Transactional::class));
        }
    }

    public function testCommentRegistersCanonicalNotifyPostOwnerDefinition(): void
    {
        $owner = '019b1000-0000-7000-8000-000000000001';
        $author = '019b1000-0000-0000-0000-000000000002';
        $postId = '019b2000-0000-7000-8000-000000000001';
        $repository = new InMemoryBoardRepository();
        $repository->createPost($postId, $owner, 'Post', 'Body', new DateTimeImmutable('2026-07-24T00:00:00Z'));
        $operations = new RecordingOperations();
        $operation = new AddComment(
            new BoardService(
                $repository,
                new FrozenBoardClock(new DateTimeImmutable('2026-07-24T00:00:00Z')),
                new SequenceBoardIdGenerator(['019b3000-0000-7000-8000-000000000002']),
            ),
            $operations,
        );

        $operation->handle(new AddCommentValue($postId, 'Comment'), $this->context($author));

        self::assertSame(\App\Feature\Notification\NotifyPostOwner\NotifyPostOwner::class, $operations->definition);
    }

    public function testOperationBoundaryMapsDomainNotFoundToSafeRejection(): void
    {
        $service = new BoardService(
            new InMemoryBoardRepository(),
            new FrozenBoardClock(new DateTimeImmutable('2026-07-20T00:00:00Z')),
            new SequenceBoardIdGenerator([]),
        );

        try {
            new ShowPost($service)->handle(new ShowPostValue('not-a-uuid'));
            self::fail('Expected a safe operation rejection.');
        } catch (OperationRejectedException $exception) {
            self::assertSame('not_found', $exception->reason()->category()->value);
            self::assertSame('board.post.not_found', $exception->reason()->code());
        }
    }

    public function testCreateOperationMapsActorAndDomainResultToOutcome(): void
    {
        $service = new BoardService(
            $repository = new InMemoryBoardRepository(),
            new FrozenBoardClock(new DateTimeImmutable('2026-07-20T12:34:56.123456+09:00')),
            new SequenceBoardIdGenerator(['019b2000-0000-7000-8000-000000000001']),
        );

        $created = new CreatePost($service)->handle(
            new CreatePostValue('First post', 'Hello board'),
            $this->context('019b1000-0000-7000-8000-000000000001'),
        );

        self::assertSame('019b2000-0000-7000-8000-000000000001', $created->postId);
        self::assertSame('2026-07-20T03:34:56.123456Z', $created->createdAt);
        self::assertSame('019b1000-0000-7000-8000-000000000001', $repository->posts[$created->postId]['authorId']);
    }

    private function context(string $userId): ExecutionContext
    {
        $actor = new ActorRef($userId, 'user');

        return new ExecutionContext(
            OperationId::fromString('019b4000-0000-7000-8000-000000000001'),
            new DateTimeImmutable('2026-07-20T00:00:00Z'),
            CorrelationId::fromString('019b4000-0000-7000-8000-000000000001'),
            actorContext: new ActorContext($actor, $actor, $actor),
        );
    }
}

final class RecordingOperations implements Operations
{
    public ?string $definition = null;

    public function dispatch(
        string $definition,
        OperationValue $value,
        ?DateTimeImmutable $availableAt = null,
        ?ActorRef $executionActor = null,
    ): DispatchReceipt {
        $this->definition = $definition;

        return new DispatchReceipt(
            OperationId::fromString('019b4000-0000-7000-8000-000000000002'),
            new DateTimeImmutable('2026-07-24T00:00:00Z'),
        );
    }
}
