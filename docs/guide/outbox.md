# Outbox

Transactional Outboxは、業務MutationとDeferred child Operationの発行を同じNamed ConnectionのTransactionへ記録する公開済みExperimental Stable `1.2.0`のSurfaceです。External BrokerやExactly Onceは提供しません。[Releases](mvp-status.md)を確認してから利用します。

:::warning[Experimental capability]
Outboxは公開済みExperimental Stable `1.2.0`のExperimental Surfaceです。External Broker、Exactly Once、child Handler完了の自動保証は提供しないため、Applicationで重複耐性を設計してください。
:::

## DispatchからCommitまで

Root OperationのHandler内で`Operations::dispatch()`を呼び、childを登録します。業務変更とOutbox Rowは同じTransactionでCommitされ、Commit前にchild Handlerは実行されません。既存のCommunity Boardで動作する最小Recipeを、ApplicationのProject Rootへ次の配置で作ります。`BoardService`、Repository実装、認証PolicyはApplication所有であり、Frameworkが生成するものではありません。

```text
app/Feature/Comment/AddComment/AddComment.php
app/Feature/Comment/AddComment/AddCommentValue.php
app/Feature/Comment/AddComment/CommentAdded.php
app/Feature/Notification/NotifyPostOwner/NotifyPostOwner.php
app/Feature/Notification/NotifyPostOwner/NotifyPostOwnerValue.php
app/Feature/Notification/NotifyPostOwner/NotificationDelivered.php
app/Domain/Board/BoardRepository.php
app/Infrastructure/Persistence/DoctrineBoardRepository.php
```

`AddCommentValue.php`はHTTP入力、`CommentAdded.php`はRoot Outcomeです。

```php
<?php
declare(strict_types=1);

namespace App\Feature\Comment\AddComment;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\Length;
use BlackOps\Core\Validation\Attribute\NotBlank;
use BlackOps\Http\Attribute\FromBody;
use BlackOps\Http\Attribute\FromPath;

final readonly class AddCommentValue implements OperationValue
{
    public function __construct(
        #[FromPath]
        public string $postId,
        #[FromBody]
        #[NotBlank]
        #[Length(min: 1, max: 2000)]
        public string $body,
    ) {}
}
```

```php
<?php
declare(strict_types=1);

namespace App\Feature\Comment\AddComment;

use BlackOps\Core\Outcome;

final readonly class CommentAdded implements Outcome
{
    public function __construct(
        public string $commentId,
        public string $postId,
        public string $createdAt,
    ) {}
}
```

`BoardRepository.php`はDomainが必要とするPersistence境界です。SQLをOperationへ書かず、`DoctrineBoardRepository.php`でこのInterfaceを実装し、Application Service Providerで`BoardRepository`へBindingします。

```php
<?php
declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;

interface BoardRepository
{
    public function lockPostAuthorId(string $postId): ?string;

    public function createComment(
        string $commentId,
        string $postId,
        string $authorId,
        string $body,
        DateTimeImmutable $createdAt,
    ): void;
}
```

`AddComment.php`でMutationとDispatchを同じTransactionへ置きます。`BoardService`がRepositoryを呼び、childのValueへ必要なIDだけを渡します。

```php
<?php
declare(strict_types=1);

namespace App\Feature\Comment\AddComment;

use App\Domain\Board\BoardService;
use App\Domain\Board\PostNotFound;
use App\Feature\BoardTime;
use App\Feature\Notification\NotifyPostOwner\NotifyPostOwner;
use App\Feature\Notification\NotifyPostOwner\NotifyPostOwnerValue;
use App\Security\AuthenticatedUser;
use App\Security\AuthenticatedUserPolicy;
use BlackOps\Core\Attribute\Authorize;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\Exception\OperationRejectedException;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;
use BlackOps\Execution\Operations;
use BlackOps\Http\Attribute\Route;

#[Route(method: 'POST', path: '/posts/{postId}/comments')]
#[OperationType('board.comment.add')]
#[Authorize(AuthenticatedUserPolicy::class)]
readonly class AddComment implements Operation
{
    public function __construct(
        private BoardService $board,
        private Operations $operations,
    ) {}

    #[Transactional]
    public function handle(AddCommentValue $value, ExecutionContext $context): CommentAdded
    {
        try {
            $comment = $this->board->addComment($value->postId, AuthenticatedUser::id($context), $value->body);
        } catch (PostNotFound) {
            throw OperationRejectedException::notFound('board.post.not_found');
        }

        if ($comment->postOwnerId !== $comment->authorId) {
            $this->operations->dispatch(
                NotifyPostOwner::class,
                new NotifyPostOwnerValue($comment->postOwnerId, $comment->postId, $comment->commentId),
            );
        }

        return new CommentAdded($comment->commentId, $comment->postId, BoardTime::http($comment->createdAt));
    }
}
```

`NotifyPostOwnerValue.php`、`NotificationDelivered.php`、`NotifyPostOwner.php`はDeferred childのValue／Outcome／Handlerです。`NotifyPostOwner`へ`#[OperationType]`と`#[Deferred]`を付け、Application-owned `NotificationService`へ副作用を閉じ込めます。

`NotifyPostOwnerValue.php`:

```php
<?php
declare(strict_types=1);

namespace App\Feature\Notification\NotifyPostOwner;

use BlackOps\Core\OperationValue;
use BlackOps\Core\Validation\Attribute\NotBlank;

final readonly class NotifyPostOwnerValue implements OperationValue
{
    public function __construct(
        #[NotBlank] public string $recipientUserId,
        #[NotBlank] public string $postId,
        #[NotBlank] public string $commentId,
    ) {}
}
```

`NotificationDelivered.php`:

```php
<?php
declare(strict_types=1);

namespace App\Feature\Notification\NotifyPostOwner;

use BlackOps\Core\Outcome;

final readonly class NotificationDelivered implements Outcome
{
    public function __construct(public bool $created) {}
}
```

`NotifyPostOwner.php`:

```php
<?php
declare(strict_types=1);

namespace App\Feature\Notification\NotifyPostOwner;

use App\Domain\Notification\NotificationService;
use BlackOps\Core\Attribute\Deferred;
use BlackOps\Core\Attribute\OperationType;
use BlackOps\Core\ExecutionContext;
use BlackOps\Core\Operation;
use BlackOps\Database\Attribute\Transactional;

#[OperationType('board.notification.notify')]
#[Deferred]
readonly class NotifyPostOwner implements Operation
{
    public function __construct(private NotificationService $notifications) {}

    #[Transactional]
    public function handle(NotifyPostOwnerValue $value, ExecutionContext $context): NotificationDelivered
    {
        return new NotificationDelivered($this->notifications->notifyPostOwner(
            $value->recipientUserId,
            $value->postId,
            $value->commentId,
            $context->operationId()->toString(),
        ));
    }
}
```

`Operations::dispatch()`の戻りはDispatch Receiptです。Root Operation IDと混同せず、必要な場合だけ`$receipt->operationId()->toString()`をApplication Outcomeまたは安全なStatus参照へ渡します。Canonical Payload、Outbox Record ID、Credentialは公開しません。Build、Migration、Service Binding後に`php blackops build:compile`を実行します。

Commit失敗時は業務変更もOutbox Rowも残りません。Commit後にRelayが停止してもPending Rowは再開可能な状態で残ります。TransactionのNamed Connection設定は[Transaction](database-and-transactions.md)を参照してください。

## RelayとWorkerを分けて実行する

Project Rootで、まずRelayを起動します。

```bash
php blackops outbox:relay:run --until-empty
```

有限Batchだけを処理する場合は`--batches=1`、常駐監督は次を使います。

```bash
php blackops outbox:relay:daemon --interval-milliseconds=1000
```

Relayの出力は`claimed`、`sent`、`retried`、`dead-lettered`、`stale`の件数です。`sent`はTransportへ渡した結果であり、child Handlerの完了やOutcomeの生成を意味しません。続けてDeferred Workerを別Processで実行します。

```bash
php blackops worker:run --iterations=1 --idle-sleep-milliseconds=1
```

常駐Workerでは`--iterations`を省略し、`--idle-sleep-milliseconds`（既定`1000`）を設定します。Workerの出力は`Worker stopped. Processed claims: N`です。WorkerがchildをClaimし、Attempt、Journal、Status／Outcomeを進めます。

## 確認とFailure Journey

1. Root OperationのResponseまたはDiagnosticsからOperation IDを記録します。
2. Relayの件数でOutbox Rowが配送対象になったことだけを確認します。
3. Workerを実行し、childの`operation.accepted`、`attempt.started`、Terminal EventをJournalで確認します。
4. `php blackops operation:inspect <operation-id> --json`でSafe Status／Outcomeを確認します。
5. Retry Scheduled、Failed、Dead LetterをCompletedと混同しない。

Relay／Workerはat-least-onceです。Lease、Fencing、Retryにより同じchild Identityが複数回配送される可能性があるため、外部副作用はIdempotency Keyまたは重複耐性をApplicationで設計します。`outbox:relay:run`の終了だけでHandler成功と判断しません。

Dead Letterを再開する場合は、対象Recordと監査理由を確認してからActorとReasonを明示します。

```bash
php blackops outbox:dead-letter:retry <record-id> \
  --actor=operations-admin \
  --reason='approved after provider recovery'
```

成功時は`dead-letter retry scheduled`だけを表示します。Payload、Context、SQL、Credential、Throwableは表示されません。再開後もRelayとWorkerを順に実行し、Status／Journal／Outcomeを再確認します。

External Message Broker、Exactly Once、Canonical Journalの置換は現行Capabilityではありません。Scheduled Application Operationは別のone-shot入口であり、Outbox Relayの完了とは別に扱います。[Scheduled Operation](scheduled-operation.md)、[Deployment](deployment.md)のプロセス一覧、[Deferred HTTPが202だがOutcomeがない](troubleshooting.md#deferred-httpが202だがoutcomeがない)も併読してください。

## 次にJournalの正本を読む

DispatchからWorkerまでの事実をどのRecordで追うかは、[Journal](journal.md)でCanonicalとObservedを分けて確認します。
