# Control cleanup caller inventory — finite correction

Status: Root candidate for the ongoing affected authority review r3.

This supplement preserves the frozen HT correction7794b535/inputs c0c25bfa and
removes only its stale ordinary publication workflow rootRevision cleanup edge.
Installation publication65c2e485 section3 had already removed that method from
`src/Internal/Audit/Control/AuditControlPublicationWorkflow.php`.

The ordinary workflow has exactly9 declared methods and only these three failure
fallback callers of CapturedControlHistory.close: segmentAdvance,refresh,recoverExact.
It has no rootRevision method or cleanup edge. Its four installation-workflow
counterparts remain bootstrap,rootRevision,staged,pinned on the distinct
`src/Internal/Audit/Control/AuditControlInstallationPublicationWorkflow.php`.
Thus the combined control workflow fallback list has7 entries,not8; the complete
CapturedControlHistory.close external caller list has12,not13. The ordinary
workflow count in the HT ledger is9,not10. The companion gives the exact corrected
lists so the worker does not infer them from subtraction or a namespace allowance.

`PostgreSqlAuditControlPublicationWriter::rootRevision` and its endpoint disposal
remain unchanged,as does InstallationPublicationWorkflow::rootRevision. No method,
owner,permission,phase,capture,SQL,helper,file or test is added. All other HT
transport and failure-ownership rows,sticky disposal latches,original error/graph
rules and39 admitted source/14 admitted test paths remain unchanged. The original
installation removal is retained; this supplement does not redesign that protocol.

This is an explicit correction to HT section2.6's caller table/workflow row and
section3's ordinary workflow count,and to the matching companion cleanupCallers,
callerCounts and rootOwnership.controlSameCapsuleFailureFallbacks fields.
No Product,PHP/native/DB/CI execution or Acceptance is claimed.
