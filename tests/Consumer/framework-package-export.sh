#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
temporary_root="$(mktemp -d)"
git_package="${temporary_root}/git-package"
composer_output="${temporary_root}/composer-output"
composer_package="${temporary_root}/composer-package"
composer_home="${temporary_root}/composer-home"
source_before="$(git -C "${repository_root}" status --short)"

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

fail() {
    printf 'Framework package export validation failed: %s\n' "$1" >&2
    exit 1
}

container=(
    timeout --signal=TERM 120
    docker run --rm
    --user "$(id -u):$(id -g)"
    --volume "${repository_root}:/repository:ro"
    --volume "${temporary_root}:/package-test"
    --env COMPOSER_HOME=/package-test/composer-home
)

mkdir -p "${git_package}" "${composer_output}" "${composer_package}" "${composer_home}"

attribute_excludes="$({
    awk '$2 == "export-ignore" { print $1 }' "${repository_root}/.gitattributes"
} | sort)"
composer_excludes="$(
    "${container[@]}" --workdir /repository blackops/framework:dev php -r '
$composer = json_decode(file_get_contents("composer.json"), true, 512, JSON_THROW_ON_ERROR);
$excludes = $composer["archive"]["exclude"] ?? [];
sort($excludes, SORT_STRING);
foreach ($excludes as $exclude) {
    echo $exclude, "\n";
}
'
)"
if [[ "${attribute_excludes}" != "${composer_excludes}" ]]; then
    printf 'gitattributes export-ignore paths:\n%s\n' "${attribute_excludes}" >&2
    printf 'Composer archive.exclude paths:\n%s\n' "${composer_excludes}" >&2
    fail '.gitattributes and composer.json archive exclusions differ'
fi

git -C "${repository_root}" archive --worktree-attributes --format=tar HEAD \
    | tar -xf - -C "${git_package}"

"${container[@]}" --workdir /repository blackops/framework:dev composer archive \
    --format=tar \
    --dir=/package-test/composer-output \
    --file=framework-composer >/dev/null
composer_archive="${composer_output}/framework-composer.tar"
test -f "${composer_archive}" || fail 'Composer archive was not created'
tar -xf "${composer_archive}" -C "${composer_package}"

validate_package() {
    local package_name="$1"
    local package_root="$2"
    local required_path
    local excluded_path
    local actual_roots
    local allowed_roots

    for required_path in \
        composer.json \
        LICENSE \
        README.md \
        CHANGELOG.md \
        UPGRADE.md \
        src/Core/Exception/ConfigurationFailure.php \
        src/Audit/AuditOpaqueIdKeyProvider.php \
        src/Audit/AuditProtectionKeyProvider.php \
        src/Audit/AuditCatalogDefinition.php \
        src/Audit/AuditCatalogProvider.php \
        src/Internal/Audit/Authoring/ApplicationAuditCatalogRegistry.php \
        src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php \
        src/Internal/Audit/Artifact/AuditArtifactFailure.php \
        src/Internal/Audit/Artifact/AuditArtifactException.php \
        src/Internal/Audit/Artifact/AuditCheckpointCodec.php \
        src/Internal/Audit/Artifact/AuditAnchorReceiptCodec.php \
        src/Internal/Audit/Artifact/AuditTrustSnapshotCodec.php \
        src/Internal/Audit/Artifact/AuditArtifactSigner.php \
        src/Internal/Audit/Artifact/AuditArtifactVerifier.php \
        src/Internal/Audit/Orchestration/AuditDeliveryFailure.php \
        src/Internal/Audit/Orchestration/AuditDeliveryException.php \
        src/Internal/Audit/Orchestration/AuditDeliveryPlan.php \
        src/Internal/Audit/Orchestration/AuditDeliveryRequest.php \
        src/Internal/Audit/Orchestration/AuditDeliverySelector.php \
        src/Internal/Audit/Orchestration/AuditDeliveryCoordinator.php \
        src/Internal/Audit/Orchestration/AuditMutationTransactionCoordinator.php \
        src/Internal/Audit/Orchestration/AuditProtectedReadGate.php \
        src/Internal/Audit/Read/AuditReadEventWriter.php \
        src/Internal/Audit/Read/OperationReadNonceSource.php \
        src/Internal/Audit/Read/RandomOperationReadNonceSource.php \
        src/Internal/Audit/Read/OperationReadExecutionSource.php \
        src/Internal/Audit/Read/OperationReadAuditSessionFactory.php \
        src/Internal/Audit/Read/OperationReadAuditSession.php \
        src/Internal/Status/AuditedOperationStatusQuery.php \
        src/Internal/Status/OperationStatusAuditTenantAuthorizer.php \
        src/Internal/Status/OperationStatusReadExecutionSource.php \
        src/Internal/OperationData/AuditedOperationJournalQuery.php \
        src/Internal/OperationData/AuditedOperationOutcomeQuery.php \
        src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php \
        src/Internal/OperationData/OperationJournalReadExecutionSource.php \
        src/Internal/OperationData/OperationOutcomeReadExecutionSource.php \
        src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php \
        src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php \
        src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php \
        src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php \
        src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php \
        src/Internal/Audit/Authorization/AuthorizationAuditPolicyBinding.php \
        src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php \
        src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php \
        src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php \
        src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php \
        src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php \
        src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php \
        src/Internal/Audit/Retention/RetentionHoldAuditWriter.php \
        src/Internal/Audit/Retention/RetentionPurgeAuditWriter.php \
        src/Internal/Audit/Rotation/StorageRotationAuditHook.php \
        src/Internal/Audit/Rotation/StorageRotationAuditWriter.php \
        src/Internal/Audit/Replay/ObserverReplayAuditHook.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriter.php \
        src/Internal/Audit/Replay/ObserverReplayAuditFailure.php \
        src/Internal/Audit/Replay/ObserverReplayAuditHookGuard.php \
        src/Internal/Audit/Replay/ObserverReplayAuditHookLineage.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriterBinding.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriterEmission.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriterPolicy.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriterCatalog.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriterDefinition.php \
        src/Internal/Audit/Replay/ObserverReplayAuditWriterRecord.php \
        src/Transport/PostgreSql/PostgreSqlObserverReplayAdvanceRoot.php \
        src/Transport/PostgreSql/PostgreSqlObserverReplayBeginRoot.php \
        src/Transport/PostgreSql/PostgreSqlObserverReplayBeginTransaction.php \
        src/Internal/Audit/Relay/AuditRelayOutcome.php \
        src/Internal/Audit/Relay/AuditRelayFailure.php \
        src/Internal/Audit/Relay/AuditRelayException.php \
        src/Internal/Audit/Relay/AuditRelayPersistence.php \
        src/Internal/Audit/Relay/PostgreSqlAuditRelayPersistence.php \
        src/Internal/Audit/Relay/AuditIngressCanonicalizer.php \
        src/Internal/Audit/Relay/AuditOutboxRelayRuntime.php \
        src/Internal/Audit/Key/ApplicationAuditProtectionKeySource.php \
        src/Internal/Application/ApplicationAuditConfiguration.php \
        src/Internal/Application/ApplicationAuditRuntime.php \
        src/Internal/Application/ApplicationAuditRuntimeComposer.php \
        src/Internal/Diagnostics/OperationDiagnosticsAuthorizationRequest.php \
        src/Internal/Diagnostics/OperationDiagnosticsAuthorizer.php \
        src/Internal/Diagnostics/UnavailableOperationDiagnosticsAuthorizer.php \
        src/Internal/Status/TenantScopedOperationStatusSource.php \
        src/Internal/Status/TenantBoundOperationStatusSource.php \
        src/Internal/Retention/AuditedRetentionHoldPort.php \
        src/Internal/Retention/AuditedRetentionPurgeAuditPort.php \
        src/Internal/Retention/AuditedRetentionPurgeService.php \
        src/Transport/PostgreSql/PostgreSqlStorageProtectionRotationPhaseHook.php \
        src/Transport/PostgreSql/PostgreSqlObserverReplayPhaseHook.php \
        src/Internal/Audit/Checkpoint/AuditCheckpointPreparation.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSchema.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentReader.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStore.php \
        src/Internal/Audit/Checkpoint/AuditCheckpointPreparationRowShape.php \
        src/Internal/Audit/Checkpoint/AuditCheckpointPreparationBody.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentRowParser.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentQueries.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentGraph.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecordProof.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransaction.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointRows.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPredecessor.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBinding.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTrustWorkflow.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointPreparationWorkflow.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointTransactionPolicy.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointInteger.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentTextFields.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditSegmentLifecycle.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditPartitionStateParser.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRelations.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentNeighbors.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentEndpoint.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointBytes.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointStoredBinding.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditCheckpointSegmentBinding.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentRecord.php \
        src/Internal/Audit/Checkpoint/PostgreSqlAuditClosedSegmentPages.php \
        src/Internal/Audit/Storage/AuditStoreFailure.php \
        src/Internal/Audit/Storage/AuditStoreException.php \
        src/Internal/Audit/Storage/AuditAppendResult.php \
        src/Internal/Audit/Storage/AuditLookupResult.php \
        src/Internal/Audit/Storage/AuditSegmentCloseResult.php \
        src/Internal/Audit/Storage/PostgreSqlAuditSchema.php \
        src/Internal/Audit/Storage/PostgreSqlAuditStoredRecordHydrator.php \
        src/Internal/Audit/Storage/PostgreSqlAuditRecordStore.php \
        src/Internal/Audit/Delivery/AuditOutboxState.php \
        src/Internal/Audit/Delivery/AuditOutboxFailure.php \
        src/Internal/Audit/Delivery/AuditOutboxException.php \
        src/Internal/Audit/Delivery/AuditOutboxConfiguration.php \
        src/Internal/Audit/Delivery/AuditOutboxStateSnapshot.php \
        src/Internal/Audit/Delivery/AuditOutboxTransition.php \
        src/Internal/Audit/Outbox/AuditOutboxStoreFailure.php \
        src/Internal/Audit/Outbox/AuditOutboxStoreException.php \
        src/Internal/Audit/Outbox/AuditOutboxRecord.php \
        src/Internal/Audit/Outbox/AuditOutboxAppendResult.php \
        src/Internal/Audit/Outbox/AuditOutboxLookupResult.php \
        src/Internal/Audit/Outbox/AuditOutboxIdempotencyKeyHasher.php \
        src/Internal/Audit/Outbox/PostgreSqlAuditProtectedIngressHydrator.php \
        src/Internal/Audit/Outbox/PostgreSqlAuditOutboxRecordHydrator.php \
        src/Internal/Audit/Outbox/PostgreSqlAuditOutboxSchema.php \
        src/Internal/Audit/Outbox/PostgreSqlAuditOutboxStore.php \
        migrations/postgresql/Version20260712000000.php \
        migrations/postgresql/Version20260712010000.php \
        migrations/postgresql/Version20260724000000.php \
        migrations/postgresql/Version20260724010000.php \
        migrations/postgresql/Version20260724100000.php \
        migrations/postgresql/Version20260724110000.php \
        migrations/postgresql/Version20260728133000.php \
        migrations/postgresql/Version20260803000000.php \
        migrations/postgresql/Version20260808000000.php \
        migrations/postgresql/Version20260808010000.php \
        migrations/postgresql/Version20260808100000.php \
        migrations/postgresql/Version20260829000000.php \
        migrations/postgresql/Version20260901000000.php \
        migrations/postgresql/Version20261001000000.php \
        resources/stubs/operation.php.stub \
        resources/stubs/operation-value.php.stub \
        resources/stubs/operation-outcome.php.stub \
        resources/stubs/migration.php.stub \
        resources/stubs/auth-config.php.stub \
        resources/stubs/auth-service-provider.php.stub \
        resources/stubs/auth-register.php.stub \
        resources/stubs/auth-login.php.stub \
        resources/stubs/auth-logout.php.stub \
        resources/stubs/auth-user-migration.php.stub \
        resources/stubs/auth-session-migration.php.stub \
        src/Transport/PostgreSql/PostgreSqlOutboxPhaseHook.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditHook.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditWriter.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditFailure.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditHookGuard.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditRow.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditSnapshot.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditIdentity.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditCatalog.php \
        src/Internal/Audit/OperationalOutbox/GenericOutboxAuditProjection.php \
        src/Internal/Audit/Anchor/AuditAnchorState.php \
        src/Internal/Audit/Anchor/AuditAnchorFailure.php \
        src/Internal/Audit/Anchor/AuditAnchorException.php \
        src/Internal/Audit/Anchor/AuditAnchorConfiguration.php \
        src/Internal/Audit/Anchor/AuditAnchorIntent.php \
        src/Internal/Audit/Anchor/AuditAnchorIntentShape.php \
        src/Internal/Audit/Anchor/AuditAnchorScalar.php \
        src/Internal/Audit/Anchor/AuditAnchorPreparedShape.php \
        src/Internal/Audit/Anchor/AuditAnchorReceiptShape.php \
        src/Internal/Audit/Anchor/AuditAnchorCanonical.php \
        src/Internal/Audit/Anchor/AuditAnchorTime.php \
        src/Internal/Audit/Anchor/AuditAnchorBinding.php \
        src/Internal/Audit/Anchor/AuditAnchorExpectation.php \
        src/Internal/Audit/Anchor/AuditAnchorLeaseOrigin.php \
        src/Internal/Audit/Anchor/AuditAnchorFrame.php \
        src/Internal/Audit/Anchor/AuditAnchorTransitionGuard.php \
        src/Internal/Audit/Anchor/AuditAnchorClaims.php \
        src/Internal/Audit/Anchor/AuditAnchorPreCallTransition.php \
        src/Internal/Audit/Anchor/AuditAnchorPostCallTransition.php \
        src/Internal/Audit/Anchor/AuditAnchorReceiptTransition.php \
        src/Internal/Audit/Anchor/AuditAnchorExternalOutcome.php \
        src/Internal/Audit/Anchor/AuditAnchorReconciliationShape.php \
        src/Internal/Audit/Anchor/AuditAnchorReceiptBody.php \
        src/Internal/Audit/Artifact/AuditArtifactWirePreflight.php \
        src/Internal/Audit/Artifact/AuditArtifactScalar.php \
        src/Internal/Audit/Artifact/AuditArtifactCanonical.php \
        src/Internal/Audit/Artifact/AuditArtifactShape.php \
        src/Internal/Audit/Artifact/AuditArtifactRelation.php \
        src/Internal/Audit/Artifact/AuditArtifactIntegrity.php \
        src/Internal/Audit/Artifact/AuditAnchorBinding.php \
        src/Internal/Audit/Artifact/AuditTrustScalar.php \
        src/Internal/Audit/Artifact/AuditTrustSnapshotShape.php \
        src/Internal/Audit/Artifact/AuditTrustEntryShape.php \
        src/Internal/Audit/Artifact/AuditTrustEntryRelation.php \
        src/Internal/Audit/Artifact/AuditTrustLookup.php; do
        test -f "${package_root}/${required_path}" \
            || fail "${package_name} archive is missing required path: ${required_path}"
    done

    required_inventory_count="$({
        awk '
            $0 == "    for required_path in \\" { active = 1; next }
            active && $0 ~ /^        test -f / { exit }
            active { count++ }
            END { print count + 0 }
        ' "${BASH_SOURCE[0]}"
    })"
    required_inventory_unique="$({
        awk '
            $0 == "    for required_path in \\" { active = 1; next }
            active && $0 ~ /^        test -f / { exit }
            active {
                line = $0
                sub(/^        /, "", line)
                sub(/ \\\\$/, "", line)
                print line
            }
        ' "${BASH_SOURCE[0]}" | sort -u | wc -l
    })"
    test "${required_inventory_count}" -eq 215
    test "${required_inventory_unique}" -eq 215

    test -d "${package_root}/src" || fail "${package_name} archive is missing src/"
    test -n "$(find "${package_root}/src" -type f -name '*.php' -print -quit)" \
        || fail "${package_name} archive has no PHP source"

    for excluded_path in \
        .gitattributes \
        .agents \
        .codex \
        .claude \
        .github \
        develop \
        docs \
        examples \
        tests \
        .deptrac.cache \
        .dockerignore \
        .env \
        .env.example \
        .gitignore \
        .phpunit.cache \
        AGENTS.md \
        Dockerfile \
        Dockerfile.frankenphp \
        compose.yaml \
        composer.lock \
        deptrac.yaml \
        mago.toml \
        mago-lint-baseline.toml \
        mise.toml \
        phpunit.xml \
        runtime \
        vendor; do
        test ! -e "${package_root}/${excluded_path}" \
            || fail "${package_name} archive contains excluded path: ${excluded_path}"
    done

    test -z "$(find "${package_root}" -type l -print -quit)" \
        || fail "${package_name} archive contains a symbolic link"

    allowed_roots=$'CHANGELOG.md\nLICENSE\nREADME.md\nUPGRADE.md\ncomposer.json\nmigrations\nresources\nsrc'
    actual_roots="$(find "${package_root}" -mindepth 1 -maxdepth 1 -printf '%f\n' | sort)"
    if [[ "${actual_roots}" != "${allowed_roots}" ]]; then
        printf '%s archive root inventory:\n%s\n' "${package_name}" "${actual_roots}" >&2
        fail "${package_name} archive contains an unexpected root path"
    fi

    "${container[@]}" --workdir "/package-test/${package_name}-package" \
        blackops/framework:dev composer validate --strict >/dev/null
    "${container[@]}" --workdir "/package-test/${package_name}-package" \
        blackops/framework:dev \
        composer dump-autoload --no-dev --classmap-authoritative --no-interaction >/dev/null
    test -f "${package_root}/vendor/autoload.php" \
        || fail "${package_name} archive did not generate a production autoloader"
}

git_inventory="$(cd "${git_package}" && find . -type f -printf '%P\n' | sort)"
composer_inventory="$(cd "${composer_package}" && find . -type f -printf '%P\n' | sort)"
if [[ "${git_inventory}" != "${composer_inventory}" ]]; then
    printf 'Framework package archive file inventory mismatch (regular-file paths only).\n' >&2
    fail 'Git and Composer archives must contain identical file inventories; rerun after the candidate is committed'
fi

validate_package git "${git_package}"
validate_package composer "${composer_package}"

test "$(git -C "${repository_root}" status --short)" = "${source_before}" \
    || fail 'main working tree changed during package validation'

cleanup
trap - EXIT
test ! -e "${temporary_root}" || fail 'temporary package tree was not removed'

echo 'Framework Git and Composer package export contract passed.'
