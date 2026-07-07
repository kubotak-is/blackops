# MVP Database Transport

## Reference Transport

MVPのReference Database Execution TransportはPostgreSQLとする。ORMは使用しない。

公式開発環境ではDocker ComposeでPostgreSQLを起動する。

PostgreSQLによって次を検証する。

- 複数Workerによる並行Claim
- 行LockとTransaction
- LeaseとHeartbeat
- Fencing Token
- Retry予定時刻
- Process Crash後のRecovery

## Table方針

一つのOperation State Tableを中心とし、Claim対象検索と原子的なState更新を行う。

Canonical Journal、Outcome、Dead Letterは責務が異なるため、必要に応じて別Tableへ分離する。

## Testと将来Adapter

Unit TestにはDatabase非依存のInMemory Transportを提供する。

SQLite AdapterはMVPへ含めず、MVP後のZero-setup Adapter候補として残す。
