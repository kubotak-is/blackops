# D106: Community Board Domain Layering

Status: Decided

## Context

P17-005の初期実装では、Repository Port／Doctrine実装／Read Model／Clock／ID Generator／Owner確認を`app/Board`へまとめ、各OperationがRepositoryを直接呼ぶ構成を検討した。

この構成ではDomain知識とInfrastructure実装の境界がDirectoryから読み取れず、Operationへ次の業務判断が分散する。

- Postの存在確認とOwner判定
- Update／Delete前のRow Lock
- DeleteとAdd Commentの競合制御
- ID／時刻を用いたPost／Comment生成
- Domain Failureの分類

Reference ApplicationはFramework APIの例だけでなく、利用者がRepository層やDomain Logicを置く実用的な構成例でもある。Userは`app/Domain/Board`と`app/Infrastructure`を分離し、OperationへDomain Logicを持たせずDomainServiceへ置く方針を指定した。

## Decision

[DECISION]

1. BoardのDomain Model、Read Model、Repository Port、Clock／ID Generator Port、Domain Exception、DomainServiceは`app/Domain/Board/**`へ置く。
2. Doctrine DBAL Repository、System Clock、Symfony UUIDv7 Generator等の技術Adapterは`app/Infrastructure/**`へ置く。
3. `app/Feature/**`のOperationはBlackOps／HTTP境界として、Value Binding、Actor ID取得、DomainService呼出、Domain ResultからOutcomeへの変換、Domain ExceptionからPublic Rejectionへの変換だけを担当する。
4. Postの存在確認、Owner判定、Row Lockを伴う更新／削除判断、ID／時刻生成、Repository操作順序はDomainServiceが所有する。
5. Domain LayerはBlackOps Attribute、Operation、Outcome、`OperationRejectedException`へ依存しない。Domain FailureはDomain Exceptionで表し、Operation境界がSafe `board.post.not_found`へ変換する。
6. `#[Transactional]`はFrameworkとのApplication BoundaryであるMutation Operationに維持し、DomainServiceをBlackOps AOPへ依存させない。
7. `app/Board`のFlat Directoryは作成しない。

[/DECISION]

## Canonical Layout

```text
app/
├── Domain/
│   └── Board/
│       ├── BoardService.php
│       ├── BoardRepository.php
│       ├── BoardClock.php
│       ├── BoardIdGenerator.php
│       ├── Exception/
│       └── Model/
├── Infrastructure/
│   ├── Persistence/
│   │   └── DoctrineBoardRepository.php
│   ├── Clock/
│   │   └── SystemBoardClock.php
│   └── Identifier/
│       └── SymfonyBoardIdGenerator.php
└── Feature/
    ├── Post/
    └── Comment/
```

細かなSubdirectoryは責務が同じなら調整できるが、`Domain`と`Infrastructure`の依存方向は変更しない。

## Consequences

[CONSEQUENCES]

- Operation TestはRoute／Value／Outcome／Domain Exception Mappingを中心にし、Owner／存在／Delete／Comment競合等の業務規則はDomainService Testで固定する。
- Doctrine固有SQL、DBAL型変換、PostgreSQL Lock／Cascade EvidenceはInfrastructure Integration Testで固定する。
- Outcome／OutcomeDataはBlackOps公開契約なのでDomain Modelへ混ぜず、Feature側のResponse Typeとして保持する。
- DomainServiceはTransactionの開始／Commitを行わない。Operation Transaction内で呼ばれることで同じConnection Scopeを使用する。
- 将来Console／Deferred等の別入口を追加しても同じDomainServiceを再利用できる。

[/CONSEQUENCES]

## Traceability

- Application Decision: [D103 Full-stack Reference Application](103-full-stack-reference-application.md)
- Deletion Policy: [D105 Community Board Deletion Policy](105-community-board-deletion-policy.md)
- Application Contract: [Full-stack Reference Application](../spec/71-full-stack-reference-application.md)
- Current Task: [P17-005 Post and Comment Operations](../orchestration/tasks/P17-005-post-and-comment-operations.md)
