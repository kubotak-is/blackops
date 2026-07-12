# JSONL Journal Observer

JsonlJournalObserver writes observed journal records as line-delimited JSON.

Application HTTP composition validates public `journal.jsonl` configuration and constructs the internal projector, sensitive filter, observer binding, aggregator, and pipeline. Disabled configuration contributes no observer. Enabled configuration opens the configured file in append-binary mode and never creates its parent directory.

`best_effort` maps to non-blocking observer delivery; `required` propagates the existing observation failure contract. Only projected records reach the observer, so Sensitive metadata and reserved key filtering apply before encoding.

The observer accepts only ObservedJournalRecord. Canonical JournalRecord and raw JournalData are not part of the adapter contract.

Each line uses a structured envelope:

```json
{"schemaVersion":1,"kind":"journal","event":"operation.received"}
```

Timestamps are emitted in UTC with six fractional digits and a trailing `Z`.

The observer writes to a caller-provided stream resource. File path configuration and runtime composition are added separately, so the current adapter can be tested with memory streams and embedded into future runtime wiring.
