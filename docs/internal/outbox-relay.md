# Transactional Outbox Relay

`PostgreSqlOutboxStore` owns the relay state machine for `outbox_records`. A
relay claims due `pending` or `retry_scheduled` rows (and expired leases) in a
short transaction using `FOR UPDATE SKIP LOCKED`. Each claim advances the
attempt count and fencing token and carries a lease owned by the configured
relay id.

Settlement always checks record id, relay id, fencing token, and `leased`
state. A stale worker therefore cannot overwrite a newer owner. Successful
transport acceptance clears lease metadata and marks the row `sent`.

Delivery is at-least-once. A transport failure stores only a versioned hash of
the exception class and schedules bounded exponential backoff. The final
attempt moves the row to `dead_lettered`. The dead-letter command inserts a
minimal actor/reason audit row, then returns the same record to
`retry_scheduled`; it never creates a new child operation.

The daemon requires PCNTL signal support. SIGTERM and SIGINT stop new claims
after the current batch, while the one-shot command supports one batch,
`--batches=<positive-int>`, or `--until-empty`.
