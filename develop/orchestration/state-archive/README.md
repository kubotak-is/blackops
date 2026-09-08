# STATE archive

`state.mjs` stores each replaced `develop/STATE.md` byte-for-byte in a
month directory named `YYYY-MM`. Archive files use the form
`timestamp-full-sha256.md` and are created exclusively. Existing archive files
are immutable and are never pruned or rewritten.

The current STATE contains a generated link to the archive created for its
immediately preceding bytes. Use `node develop/orchestration/state.mjs
--history` to list all discoverable archive files.

Archive files are operational history, not a place to edit the current
checkpoint. If a replacement fails, the current STATE remains in place and
the temporary files used for the attempted write are removed.
