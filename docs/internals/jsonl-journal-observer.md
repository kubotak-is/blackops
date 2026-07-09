# JSONL Journal Observer

JsonlJournalObserver writes observed journal records as line-delimited JSON.

The observer accepts only ObservedJournalRecord. Canonical JournalRecord and raw JournalData are not part of the adapter contract.

Each line uses a structured envelope:

```json
{"schemaVersion":1,"kind":"journal","event":"operation.received"}
```

Timestamps are emitted in UTC with six fractional digits and a trailing `Z`.

The observer writes to a caller-provided stream resource. File path configuration and runtime composition are added separately, so the current adapter can be tested with memory streams and embedded into future runtime wiring.
