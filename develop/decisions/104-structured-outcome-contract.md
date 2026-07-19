# D104: Structured Outcome Contract

Status: Decided

## Context

P17-003でCommunity BoardのIdentity／Session／BFF境界を完成した。次のPost／Comment Taskでは、投稿一覧と投稿詳細を少なくとも次のようなTyped Outcomeで返す必要がある。

```text
ListPostsOutcome
  posts: list<PostSummary>
  page: int
  perPage: int
  total: int

ShowPostOutcome
  post: PostDetail
  comments: list<CommentDetail>
```

しかし現在のPublic Contractは次の制約を持つ。

- Frontend ContractはOutcomeのNative ScalarとNullable Scalarだけを許可し、Array／Objectを`build:compile`で拒否する
- PostgreSQL Outcome CodecはOutcomeのPublic PropertyをScalar／nullだけに限定する
- Generated TypeScript DecoderはScalar FieldだけをStrict Decodeする
- Sensitive ProjectionのArray処理はString Keyだけを残すため、ListのNumeric Keyをそのまま扱えない
- HTTP `json_encode()`自体はObject／Arrayを出力できるが、Build／Persistence／Generated Typeの保証が一致しない

JSON文字列へ手動EncodeすればScalar制約を回避できるが、PHP／TypeScript双方で型を失い、Frontend Bridgeを採用した目的に反する。HTTP OperationをFrontend生成対象から外す方法も、D100の「全HTTP Operationを生成する」という確定方針に反する。

このため、Post／Comment実装前にStructured OutcomeをPublic Contractへ追加するか判断する。

## Decision Drivers

- Community BoardのFeed／DetailをTyped Generated Operationとして扱える
- PHP TypeとGenerated TypeScript TypeのShapeをBuild時に一致させる
- PostgreSQL Outcome保存／復元、Inline HTTP、Deferred Status Outcomeで同じShapeを使う
- `any`、`unknown`、JSON String、自由なMapへ逃がさない
- Sensitive FieldをNested DTO／ListでもFail-closedに扱う
- OperationValueのHTTP Binding Scopeを無断で広げない
- 既存Scalar Outcomeと保存済みSchema Versionの互換性を維持する

## Question 1: Structured OutcomeをPhase 17へ追加するか

### Options

- A: Post／Commentより先に、Typed Nested DTOとTyped ListをOutcome Public Contractへ追加する
- B: Community BoardだけOutcomeをJSON StringへEncodeし、SvelteKit側でApplication-owned Decoderを書く
- C: List／Detail HTTP OperationをFrontend生成対象外にするOpt-out Attributeを追加する
- D: Post／Commentを延期し、Scalar OutcomeだけでPhase 17を縮小する

### Recommendation

Aを推奨する。

BはBackendとFrontendのSchemaを手書きで二重管理する。Cは全HTTP Operation生成というD100を崩し、認証や機密情報を理由に生成対象を選ぶ誤った境界を作る。DはReference Applicationの中心Journeyを失う。Structured Outcomeは掲示板固有ではなく、一覧APIを持つ通常のApplicationに必要なFramework Capabilityである。

[ANSWER]

A

[/ANSWER]

## Question 2: Initial Structured Typeの範囲

### Options

- A: Outcome Outputだけに、Native Scalar／Nullable Scalar、Readonly DTO／Nullable DTO、Typed `list<DTO>`を追加する。Map、Scalar List、Union、Enum、DateTime、Array Inputは後続とする
- B: Outputに加えてOperationValueのNested Object／Array HTTP Bindingも同時に追加する
- C: PHPの任意Object／Arrayを再帰的に許可し、Generated TypeScriptは`unknown`で表す

### Recommendation

Aを推奨する。

Community Boardに必要なのはPost／CommentのOutput Shapeであり、Inputは既存のScalar Fieldで表現できる。BはHTTP Binder、Validation、Sensitive Input、Transport Codecまで別問題を広げる。CはStrict Decodeと型安全を失う。

[ANSWER]

A

[/ANSWER]

## Question 3: PHPでList Element Typeをどう宣言するか

### Options

- A: Framework Public Attribute `#[ListOf(PostSummary::class)]`と、Nested Response DTO用Marker Interfaceを追加する。Array PropertyはAttribute必須とし、Element DTOをBuild時に検証する
- B: `@var list<PostSummary>`／Constructor `@param` PHPDocを解析する
- C: Property名や実行時の最初の要素からElement Typeを推論する

### Recommendation

Aを推奨する。

Native PHP ReflectionはArray Element Typeを持たない。Aは既存Attribute中心のContractと一致し、PHPDoc Parser依存や表記揺れを増やさない。空Listでも型が確定し、Runtime DataからSchemaを推測しない。Marker Interfaceにより任意Service ObjectをResponse DTOとして誤登録することも防げる。

概念形は次とする。最終Namespace／NameはSpecificationで確定する。

```php
final readonly class ListPostsOutcome implements Outcome
{
    /** @param list<PostSummary> $posts */
    public function __construct(
        #[ListOf(PostSummary::class)]
        public array $posts,
        public int $page,
        public int $perPage,
        public int $total,
    ) {}
}

final readonly class PostSummary implements OutcomeData
{
    public function __construct(
        public string $id,
        public string $authorDisplayName,
        public string $title,
        public string $createdAt,
    ) {}
}
```

[ANSWER]

A

[/ANSWER]

## Question 4: Persistence Compatibility

### Options

- A: PostgreSQL Outcome CodecをSchema Version 2へ上げ、Version 1 Scalar OutcomeのDecode互換を維持する。新規EncodeはVersion 2だけを使う
- B: Schema Version 1の意味をIn-placeで拡張し、同じVersionのPayload Shapeを変える
- C: Structured OutcomeはPostgreSQLへ保存せず、Inline HTTP Responseだけで使用する
- D: PostgreSQL Outcome CodecをSchema Version 2へ上げ、Version 1は非対応にする。既存Local Dataは再作成する

### Recommendation

Dを推奨する。

BlackOpsはPublic Readiness前のExperimental Projectであり、D094は1.x Minor間のBackward Compatibilityを保証していない。外部利用者と維持対象のProduction Dataがない現時点でVersion 1 Decodeを実装・保守する実益はない。Version 1の意味は変えず、新しいPayloadだけをVersion 2として扱い、Local Development DataはDatabase再作成で移行する。CはInlineとDeferredでOutcome Contractを分岐し、将来のDeferred DigestやStatus APIを壊す。

[ANSWER]

互換性は維持する必要ある？ユーザーはまだいないが

[/ANSWER]

[RESOLUTION]

D。現時点では維持不要とする。Version 1 Rowを自動Decode／Migrationせず、安全なUnsupported Schema Errorにする。

[/RESOLUTION]

## Question 5: Phase 17 Delivery Order

### Options

- A: 新P17-004をStructured Outcome Contractとし、未着手のPost／Comment以降をP17-005からP17-009へ一つずつ繰り下げる
- B: P17-004 Post／Comment Task内でFramework CapabilityとApplication Domainを同時実装する
- C: `P17-003A`として追加し、既存Task番号を維持する

### Recommendation

Aを推奨する。

Framework Production CodeとReference Application Domainを別Task Packet／Commit／Reviewに分けられる。失敗時の責任範囲が明確で、Structured Outcome自体をFramework Testで先に固定してからConsumerへ使える。

[ANSWER]

A

[/ANSWER]

## Decision

[DECISION]

1. Phase 17へStructured Outcomeを追加し、Post／Commentより先にFramework Capabilityとして実装する。
2. Initial ScopeはOutcome Outputだけとし、OperationValueのNested Object／Array HTTP Bindingを変更しない。
3. Public `#[ListOf]` AttributeとNested Response DTO用`OutcomeData` Marker Interfaceで`list<DTO>`を宣言する。
4. PostgreSQL Outcome CodecはSchema Version 2だけをEncode／Decodeし、Version 1互換や自動Migrationを提供しない。
5. 新P17-004をStructured Outcome Contractとし、従来のP17-004からP17-008をP17-005からP17-009へ繰り下げる。

[/DECISION]

## Acceptance Boundary for A / A / A / D / A

- Public Marker InterfaceとTyped List Attributeを追加する
- Outcome Root／Nested DTOのFieldはScalar、Nullable Scalar、DTO、Nullable DTO、`#[ListOf] array`だけを許可する
- DTOとListの再帰Cycle、Static／Non-public／Uninitialized、Unsupported Type、Sensitive Outcome FieldをBuild時またはEncode時に拒否する
- Listは連続した0-based Listだけを許可し、Map／Sparse Array／Wrong ElementをFail-fastする
- Frontend Manifestに再帰Schemaを決定的に保存し、Artifact Schema Versionを上げる
- Generated TypeScriptへReadonly Nested Type／ReadonlyArrayとStrict Recursive Decoderを生成する
- Inline Response、Status Response、PostgreSQL Outcome Codec、Sensitive Projectionで同じShapeを扱う
- PostgreSQL Outcome Codec Version 2をRound-tripし、Version 1／未知Versionを安全に拒否する
- Existing Scalar Generated API／Artifact／Outcomeの回帰を防ぐ
- OperationValue HTTP Bindingは変更しない
- Community BoardのPost／Comment Production Codeは次Taskまで変更しない

## Consequences

[CONSEQUENCES]

- Existing Local DatabaseにVersion 1 Outcomeがある場合はDatabaseを再作成する。Data Migration Toolは提供しない。
- Frontend Contract ArtifactとGenerated Treeは新Schemaで再生成し、旧ArtifactをFreshとして扱わない。
- Structured OutcomeをInline／Deferredで共通利用でき、Community BoardのFeed／DetailをJSON StringやFrontend Opt-outなしで型付けできる。
- Input Binder、Map、Scalar List、Enum、DateTime、Unionは後続DecisionまでUnsupportedのままとする。

[/CONSEQUENCES]

## Traceability

- Full-stack Application: [Full-stack Reference Application](../spec/71-full-stack-reference-application.md)
- Phase 17 Plan: [Phase 17 Delivery Plan](../spec/72-phase-17-delivery-plan.md)
- Frontend Contract: [Operation Frontend Bridge](../spec/67-operation-frontend-bridge.md)
- Decision: [D100 Phase 15 Operation Frontend Bridge](100-phase-15-operation-frontend-bridge.md)
- Compatibility Policy: [D094 Stable 1.1 Release Contract](094-stable-1-1-release-contract.md)
