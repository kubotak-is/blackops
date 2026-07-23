# Architecture

BlackOps is an operation-driven PHP framework. HTTP is one adapter into a typed Operation model; Inline and Deferred strategies share identifiers, metadata, handlers, outcomes, and Canonical Journal records.

## System Overview

```mermaid
flowchart LR
    Build[Build commands] --> OM[Versioned Operation Manifest]
    Build --> HM[Versioned HTTP Manifest<br/>FastRoute data]
    Build --> DI[Compiled Symfony DI Container]
    OM --> Runtime[Production Artifact Loader]
    HM --> Runtime
    DI --> Runtime

    HTTP[PSR-7 Request] --> FC[PSR-15 / FrankenPHP boundary]
    FC --> Routes[Compiled Route Registry]
    Routes --> Inline[Inline Dispatcher]
    Routes --> Accept[Deferred Acceptor]
    Inline --> Idempotency[(PostgreSQL Idempotency Records)]
    Accept --> Idempotency
    Inline --> Handler[Typed Handler]
    Inline --> Journal[(PostgreSQL Canonical Journal)]
    Accept --> Operations[(PostgreSQL Operations)]
    Accept --> Journal
    Operations --> Worker[Deferred Worker Loop]
    Worker --> Handler
    Worker --> Journal
    Worker --> Outcomes[(Typed Outcomes)]
    Worker --> Dead[(Dead Letters)]

    Journal --> Project[Observed Projection<br/>Sensitive Filter]
    Project --> JSONL[JSONL Observer]
    Retention[Retention CLI / Scheduler] --> Operations
    Retention --> Journal
    Retention --> Outcomes
    Retention --> Dead
    Retention --> Idempotency
    Retention --> Audit[(Purge Audit)]
    Retention --> SystemLog[Fail-closed System Log]
```

Build-time discovery and compilation are separated from runtime loading. Production startup fails on missing, malformed, unsupported, or cross-build artifacts and does not fall back to source scanning or container compilation.

Core and public contracts do not depend on `BlackOps\Internal`. Deptrac checks namespace dependency direction, while the Public API architecture test rejects Internal types in marked public signatures.

## Inline Sequence

```mermaid
sequenceDiagram
    participant Client
    participant HTTP as PSR-15 Request Handler
    participant Route as Compiled FastRoute Registry
    participant Inline as Inline Dispatcher
    participant Journal as Canonical Journal
    participant Observer as Safe Observation Pipeline
    participant Handler

    Client->>HTTP: GET /welcome
    HTTP->>Route: match and bind request
    Route-->>HTTP: Inline operation metadata
    HTTP->>Inline: dispatch definition + typed value
    Inline->>Journal: operation.received
    Inline->>Observer: filtered projection
    Inline->>Journal: attempt.started
    Inline->>Observer: filtered projection
    Inline->>Handler: handle(typed value, optional context)
    Handler-->>Inline: typed outcome
    Inline->>Journal: attempt.succeeded
    Inline->>Journal: operation.completed + canonical outcome
    Inline-->>HTTP: OperationResult
    HTTP-->>Client: 200 JSON
```

Canonical append succeeds before its observation is dispatched. Observer behavior follows its configured delivery policy. Canonical Received data may preserve sensitive input for reproducibility; the Observed projection is filtered before JSONL output.

## Deferred Sequence and Transaction Boundaries

```mermaid
sequenceDiagram
    participant Client
    participant HTTP as Deferred HTTP Acceptor
    participant DB as PostgreSQL Operations
    participant Journal as Canonical Journal
    participant Worker as Worker Loop
    participant Handler
    participant Outcome as Outcome Store

    Client->>HTTP: POST /reports
    rect rgb(235, 245, 255)
        Note over HTTP,Journal: Acceptance transaction
        HTTP->>DB: insert accepted operation
        HTTP->>Journal: operation.received
        HTTP->>Journal: operation.accepted
    end
    HTTP-->>Client: 202 + Operation ID

    Worker->>DB: recover expired attempt, then claim eligible operation
    rect rgb(235, 245, 255)
        Note over Worker,Journal: Attempt-start transaction
        Worker->>DB: reserve attempt + fencing token
        Worker->>Journal: attempt.started
    end
    Worker->>Handler: execute outside DB transaction
    Handler-->>Worker: Completed(typed outcome)
    rect rgb(235, 245, 255)
        Note over Worker,Outcome: Completion transaction
        Worker->>DB: terminal state with fencing check
        Worker->>Journal: attempt.succeeded
        Worker->>Journal: operation.completed
        Worker->>Outcome: save typed outcome
    end
    Worker->>DB: acknowledge claim
```

The handler does not hold the lifecycle transaction open. A dedicated heartbeat DBAL connection extends the lease during handler execution; the lifecycle connection owns state, journal, outcome, and settlement work.

## Handler Failure and Recovery

```mermaid
sequenceDiagram
    participant Loop as Worker Loop
    participant DB as Operations / Lease
    participant Journal as Canonical Journal
    participant Handler
    participant Heartbeat as Dedicated Heartbeat Connection

    Loop->>DB: claim
    Loop->>DB: commit attempt start
    Loop->>Handler: execute with signal heartbeat guard
    Heartbeat->>DB: extend lease with fencing token
    alt Retryable handler exception
        Handler--xLoop: exception
        rect rgb(255, 242, 230)
            Note over Loop,Journal: Supervision transaction
            Loop->>Journal: attempt.failed
            Loop->>Journal: attempt.retry_scheduled
            Loop->>DB: retry_scheduled + available_at
        end
        Note over Loop: supervised failure may continue loop
    else Retry exhausted / dead-letter policy
        Handler--xLoop: exception
        rect rgb(255, 242, 230)
            Loop->>Journal: attempt.failed
            Loop->>Journal: operation.dead_lettered
            Loop->>DB: terminal dead_lettered + dead-letter index
        end
    else Heartbeat loss, crash, or grace timeout
        Heartbeat--xLoop: interrupt / process disappears
        Note over Loop: no supervision, acknowledge, or release
        Loop->>DB: lease remains until expiry
        Note over DB: another worker iteration
        DB->>DB: fence and recover expired attempt
        DB->>Journal: attempt.failed (lease expired)
        DB->>DB: retry schedule or terminal decision
    end
```

Only supervised handler failures are eligible for loop continuation. Claim, metadata, transaction, recovery, and settlement failures terminate the worker. Stale workers cannot commit completion after losing the fencing token.

## Idempotency Boundary

Keyed mutation requests claim a scope only after authentication and
authorization. The idempotency store owns the unique scope boundary, typed
result projection, safe HTTP snapshot, and processing-to-terminal transition.
Duplicate decisions never invoke the handler again. A complete canonical
journal can be used by the internal recovery service to reconstruct a terminal
result or deferred acceptance. Insufficient but valid evidence leaves a record
processing for later inspection; corrupt or contradictory evidence becomes a
safe internal failure.

## Retention Boundary

Retention planning is read-only. Confirmed purge rechecks Active Hold and target freshness before changing data. Each deletion/tombstone and Database Purge Audit are in one transaction. The audit decorator calls the Database Audit first and the payload-free PSR-3 System Log second; logger failure rolls the database transaction back.

Retention holds and purge audits retain typed Operation IDs without a foreign key to Operations so Inline operations and idempotency records can be protected and audited. Retention services do not delete Operations rows. Idempotency records are a fifth independent target and are deleted only after terminal eligibility and hold rechecks.

## Extension and Ownership Boundaries

- Applications own DB connections, credentials, provider config, console registration, environment loading, and deployment.
- Framework adapters expose PostgreSQL, Monolog JSONL, FastRoute, Symfony DI, Nyholm PSR-7, and FrankenPHP reference integrations.
- Production migrations are explicit deployment commands; HTTP and workers never run DDL automatically.
- Authentication, authorization, encryption, remote observability, queue adapters, and outbox relay are post-MVP extension work.
