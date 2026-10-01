#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
EXPORT_SCRIPT="${SCRIPT_DIR}/framework-package-export.sh"
TEMP_ROOT="$(mktemp -d)"
trap 'rm -rf -- "${TEMP_ROOT}"' EXIT

reviewed_export_sha='57ccab5fe22c80c7686cc0dac35eaa0efa92a2d629306bd7e276c9601c05fe47'

expected_inventory="${TEMP_ROOT}/expected-inventory"
printf '%s\n' \
    composer.json \
    LICENSE \
    README.md \
    CHANGELOG.md \
    UPGRADE.md \
    src/Core/Exception/ConfigurationFailure.php \
    src/Audit/AuditOpaqueIdKeyProvider.php \
    src/Audit/AuditProtectionKeyProvider.php \
    src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php \
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
    >"${expected_inventory}"

run_bounded() {
    if command -v timeout >/dev/null 2>&1; then
        timeout --signal=TERM --kill-after=1s 5 "$@"
    else
        "$@"
    fi
}

extract_function() {
    local candidate="$1"
    local output="$2"

    if ! run_bounded awk '
        BEGIN {
            declarations = 0
            endings = 0
            in_function = 0
            invalid = 0
        }
        {
            if ($0 == "validate_package() {") {
                declarations++
                if (declarations != 1 || in_function) {
                    invalid = 1
                    next
                }
                in_function = 1
                print
                next
            }

            if (in_function) {
                print
                if ($0 == "}") {
                    endings++
                    in_function = 0
                }
            }
        }
        END {
            if (declarations != 1 || endings != 1 || in_function || invalid) {
                exit 2
            }
        }
    ' "${candidate}" >"${output}"; then
        return 1
    fi

    test -s "${output}"
}

extract_inventory() {
    local function_source="$1"
    local output="$2"

    if ! run_bounded awk -v expected_file="${expected_inventory}" '
        BEGIN {
            expected_count = 0
            while ((getline line < expected_file) > 0) {
                expected[++expected_count] = line
            }
            close(expected_file)
            backslash = sprintf("%c", 92)
            loop_marker = "    for required_path in " backslash
            path_index = 0
            loop_count = 0
            state = 0
            invalid = 0
        }
        {
            if (state == 0) {
                if ($0 == loop_marker) {
                    loop_count++
                    if (loop_count != 1) {
                        invalid = 1
                    }
                    state = 1
                }
                next
            }

            if (state == 1) {
                path_index++
                if (path_index < expected_count) {
                    expected_line = "        " expected[path_index] " " backslash
                } else {
                    expected_line = "        " expected[path_index] "; do"
                }
                if (path_index > expected_count || $0 != expected_line) {
                    invalid = 1
                } else {
                    print expected[path_index]
                    if (path_index == expected_count) {
                        state = 2
                    }
                }
                next
            }

            if (state == 2) {
                if ($0 == "        test -f \"${package_root}/${required_path}\" " backslash) {
                    state = 3
                } else {
                    invalid = 1
                }
                next
            }

            if (state == 3) {
                if ($0 == "            || fail \"${package_name} archive is missing required path: ${required_path}\"") {
                    state = 4
                } else {
                    invalid = 1
                }
                next
            }

            if (state == 4) {
                if ($0 == "    done") {
                    state = 5
                } else {
                    invalid = 1
                }
                next
            }

            if (state == 5 && $0 == loop_marker) {
                loop_count++
                invalid = 1
            }
        }
        END {
            if (state != 5 || path_index != expected_count || loop_count != 1 || invalid) {
                exit 2
            }
        }
    ' "${function_source}" >"${output}"; then
        return 1
    fi

    test -s "${output}"
}

assert_call_contract() {
    local candidate="$1"
    local phase_file="${2:-}"

    if [[ -n "${phase_file}" ]]; then
        printf 'call-contract\n' >>"${phase_file}"
    fi

    run_bounded awk '
        function trim(value) {
            sub(/^[[:space:]]+/, "", value)
            sub(/[[:space:]]+$/, "", value)
            return value
        }

        function is_identifier_character(value) {
            return value ~ /^[A-Za-z0-9_]$/
        }

        function scan_shell_line(line,    i, character, length_value, escaped) {
            masked = ""
            validate_active = 0
            heredoc_start = 0
            line_continuation = 0
            length_value = length(line)
            escaped = 0
            for (i = 1; i <= length_value; i++) {
                character = substr(line, i, 1)
                if (quote == "single") {
                    masked = masked " "
                    if (character == "\047") {
                        quote = ""
                    }
                    continue
                }
                if (quote == "double") {
                    masked = masked " "
                    if (double_escaped) {
                        double_escaped = 0
                        continue
                    }
                    if (character == "\140") {
                        if (backtick_depth == 0) {
                            backtick_depth = 1
                        } else {
                            backtick_depth = 0
                        }
                        continue
                    }
                    if (character == "$" && substr(line, i + 1, 1) == "(") {
                        command_substitution_depth++
                    } else if (character == ")" && command_substitution_depth > 0) {
                        command_substitution_depth--
                    }
                    if (character == "\\") {
                        double_escaped = 1
                        if (i == length_value) {
                            line_continuation = 1
                        }
                        continue
                    }
                    if (character == "\042") {
                        quote = ""
                    }
                    continue
                }
                if (character == "#") {
                    while (i <= length_value) {
                        masked = masked " "
                        i++
                    }
                    break
                }
                if (character == "\140") {
                    if (backtick_depth == 0) {
                        backtick_depth = 1
                    } else {
                        backtick_depth = 0
                    }
                    masked = masked character
                    continue
                }
                if (character == "$" && substr(line, i + 1, 1) == "(") {
                    command_substitution_depth++
                } else if (character == ")" && command_substitution_depth > 0) {
                    command_substitution_depth--
                }
                if (character == "\\") {
                    masked = masked " "
                    if (i == length_value) {
                        line_continuation = 1
                    } else {
                        i++
                        masked = masked " "
                    }
                    continue
                }
                if (character == "\047") {
                    quote = "single"
                    masked = masked " "
                    continue
                }
                if (character == "\042") {
                    quote = "double"
                    double_escaped = 0
                    masked = masked " "
                    continue
                }
                if (character == "<" && substr(line, i + 1, 1) == "<" && heredoc_start == 0) {
                    heredoc_start = i
                }
                if (substr(line, i, 16) == "validate_package" && (i == 1 || !is_identifier_character(substr(line, i - 1, 1))) && (i + 16 > length_value || !is_identifier_character(substr(line, i + 16, 1)))) {
                    validate_active = 1
                }
                masked = masked character
            }
        }

        function set_heredoc(line, start,    rest, marker, marker_end, marker_quote, marker_length) {
            if (start == 0) {
                return
            }
            rest = substr(line, start + 2)
            sub(/^[[:space:]]*/, "", rest)
            heredoc_strip_tabs = 0
            if (substr(rest, 1, 1) == "-") {
                heredoc_strip_tabs = 1
                rest = substr(rest, 2)
                sub(/^[[:space:]]*/, "", rest)
            }
            marker = substr(rest, 1, 1)
            if (marker == "\047" || marker == "\042") {
                marker_quote = marker
                marker_end = index(substr(rest, 2), marker_quote)
                if (marker_end == 0) {
                    return
                }
                heredoc_delimiter = substr(rest, 2, marker_end - 1)
                return
            }
            marker_length = 0
            while (marker_length < length(rest)) {
                marker = substr(rest, marker_length + 1, 1)
                if (marker ~ /[[:space:];|&]/) {
                    break
                }
                marker_length++
            }
            if (marker_length > 0) {
                heredoc_delimiter = substr(rest, 1, marker_length)
            }
        }

        function top_level() {
            return function_depth == 0 && if_depth == 0 && loop_depth == 0 \
                && case_depth == 0 && group_depth == 0 && paren_depth == 0 \
                && command_substitution_depth == 0 && backtick_depth == 0
        }

        function shell_command_continued(code) {
            return code ~ /(&&|\|\||\|[&]?)[[:space:]]*$/
        }

        function termination_command(code) {
            return code ~ /(^|[;]|&&|\|\|)[[:space:]]*(then[[:space:]]+)?(return|exit|exec|false)([[:space:];&]|$)/
        }

        function resolution_override(code, raw) {
            if (code ~ /(^|[;&|])[[:space:]]*shopt[[:space:]]+-s[[:space:]]+expand_aliases([[:space:];&|]|$)/) {
                return 1
            }
            if (code ~ /(^|[;&|])[[:space:]]*alias[[:space:]]+/ &&
                raw ~ /(^|[;&|])[[:space:]]*alias[[:space:]]+([\047\042])?validate_package[[:space:]]*=/) {
                return 1
            }
            return 0
        }

        function update_control_depth(code,    trimmed) {
            trimmed = trim(code)
            if (trimmed ~ /^\}[[:space:]]*$/) {
                if (function_depth > 0) {
                    function_depth--
                } else if (group_depth > 0) {
                    group_depth--
                }
                return
            }
            if (trimmed ~ /^\)[[:space:]]*$/) {
                if (paren_depth > 0) {
                    paren_depth--
                }
                return
            }
            if (trimmed == "fi") {
                if (if_depth > 0) {
                    if_depth--
                }
                return
            }
            if (trimmed == "done") {
                if (loop_depth > 0) {
                    loop_depth--
                }
                return
            }
            if (trimmed == "esac") {
                if (case_depth > 0) {
                    case_depth--
                }
                return
            }
            if (trimmed == "{") {
                group_depth++
                return
            }
            if (trimmed == "(") {
                paren_depth++
                return
            }
            if (trimmed ~ /^[A-Za-z_][A-Za-z0-9_]*[[:space:]]*\(\)[[:space:]]*\{[[:space:]]*$/) {
                function_depth++
                return
            }
            if (trimmed ~ /^function[[:space:]]+[A-Za-z_][A-Za-z0-9_]*(\(\))?[[:space:]]*\{[[:space:]]*$/) {
                function_depth++
                return
            }
            if (trimmed ~ /^if([[:space:]]|\[\[|\()/) {
                if_depth++
                if (trimmed ~ /(^|[;[:space:]])fi([;[:space:]]|$)/) {
                    if_depth--
                }
                return
            }
            if (trimmed ~ /^(for|while|until|select)([[:space:]]|$)/) {
                loop_depth++
                return
            }
            if (trimmed ~ /^case([[:space:]]|$)/) {
                case_depth++
            }
        }

        BEGIN {
            in_validate_function = 0
            function_closed = 0
            git_calls = 0
            composer_calls = 0
            call_count = 0
            invalid = 0
            quote = ""
            double_escaped = 0
            heredoc_delimiter = ""
            heredoc_strip_tabs = 0
            continued = 0
            function_depth = 0
            if_depth = 0
            loop_depth = 0
            case_depth = 0
            group_depth = 0
            paren_depth = 0
            command_substitution_depth = 0
            backtick_depth = 0
            logical_continued = 0
        }
        {
            if (heredoc_delimiter != "") {
                heredoc_line = $0
                if (heredoc_strip_tabs) {
                    sub(/^\t+/, "", heredoc_line)
                }
                if (heredoc_line == heredoc_delimiter) {
                    heredoc_delimiter = ""
                    heredoc_strip_tabs = 0
                }
                next
            }

            prior_continued = continued
            prior_logical_continued = logical_continued
            prior_command_substitution = command_substitution_depth > 0
            prior_backtick_substitution = backtick_depth > 0
            scan_shell_line($0)
            code = trim(masked)
            raw = trim($0)
            if (resolution_override(code, raw)) {
                invalid = 1
            }
            if (!function_closed) {
                if ($0 == "validate_package() {") {
                    if (in_validate_function) {
                        invalid = 1
                    }
                    in_validate_function = 1
                } else if (in_validate_function && $0 == "}") {
                    in_validate_function = 0
                    function_closed = 1
                }
                next
            }
            if (validate_active) {
                if (!top_level() || prior_continued || prior_logical_continued ||
                    prior_command_substitution || prior_backtick_substitution ||
                    (raw != "validate_package git \"${git_package}\"" && raw != "validate_package composer \"${composer_package}\"")) {
                    invalid = 1
                } else if (raw == "validate_package git \"${git_package}\"") {
                    git_calls++
                    call_count++
                    if (git_calls != 1 || composer_calls != 0) {
                        invalid = 1
                    }
                } else {
                    composer_calls++
                    call_count++
                    if (composer_calls != 1 || git_calls != 1) {
                        invalid = 1
                    }
                }
            }
            if (call_count < 2 && function_depth == 0 && termination_command(code)) {
                invalid = 1
            }
            update_control_depth(code)
            continued = line_continuation
            logical_continued = shell_command_continued(code)
            if (heredoc_start != 0) {
                set_heredoc($0, heredoc_start)
            }
        }
        END {
            if (!function_closed || in_validate_function || git_calls != 1 || composer_calls != 1 || invalid) {
                exit 2
            }
        }
    ' "${candidate}"
}

prepare_runtime_package() {
    local package_root="$1"
    local missing_path="${2:-}"
    local required_path
    local parent

    mkdir -p "${package_root}"
    while IFS= read -r required_path; do
        [[ "${required_path}" == "${missing_path}" ]] && continue
        parent="${required_path%/*}"
        if [[ "${parent}" != "${required_path}" ]]; then
            mkdir -p "${package_root}/${parent}"
        fi
        : >"${package_root}/${required_path}"
    done <"${expected_inventory}"
}

assert_runtime_function() {
    local function_source="$1"
    local complete_root="$2"
    local missing_root="$3"

    if ! run_bounded bash -c '
        set -euo pipefail
        fail() { exit 1; }
        runtime_container() {
            mkdir -p "${RUNTIME_PACKAGE_ROOT}/vendor"
            : >"${RUNTIME_PACKAGE_ROOT}/vendor/autoload.php"
        }
        source "$1"
        container=(runtime_container)
        RUNTIME_PACKAGE_ROOT="$3"
        validate_package "$2" "$3"
    ' _ "${function_source}" runtime "${complete_root}"; then
        return 1
    fi

    if run_bounded bash -c '
        set -euo pipefail
        fail() { exit 1; }
        runtime_container() {
            mkdir -p "${RUNTIME_PACKAGE_ROOT}/vendor"
            : >"${RUNTIME_PACKAGE_ROOT}/vendor/autoload.php"
        }
        source "$1"
        container=(runtime_container)
        RUNTIME_PACKAGE_ROOT="$3"
        validate_package "$2" "$3"
    ' _ "${function_source}" runtime "${missing_root}"; then
        return 1
    fi
}

assert_execution_contract() {
    local candidate="$1"
    local function_source="$2"
    local reviewed_function_source="${TEMP_ROOT}/reviewed-function"
    local runtime_case
    local complete_root
    local missing_root
    local missing_path='src/Internal/Audit/Storage/AuditStoreFailure.php'

    if ! extract_function "${EXPORT_SCRIPT}" "${reviewed_function_source}"; then
        return 1
    fi
    if ! assert_call_contract "${candidate}"; then
        return 1
    fi

    runtime_case=$((runtime_case_count + 1))
    runtime_case_count="${runtime_case}"
    complete_root="${TEMP_ROOT}/runtime-${runtime_case}-complete"
    missing_root="${TEMP_ROOT}/runtime-${runtime_case}-missing"
    prepare_runtime_package "${complete_root}"
    prepare_runtime_package "${missing_root}" "${missing_path}"
    if ! assert_runtime_function "${function_source}" "${complete_root}" "${missing_root}"; then
        return 1
    fi

    if ! cmp -s "${reviewed_function_source}" "${function_source}"; then
        return 1
    fi
    if ! test "$(sha256sum "${candidate}" | cut -d' ' -f1)" = "${reviewed_export_sha}"; then
        return 1
    fi
}

assert_inventory() {
    local candidate="$1"
    local function_source="${TEMP_ROOT}/candidate-function"
    local actual_inventory="${TEMP_ROOT}/actual-inventory"

    if ! run_bounded bash -n "${candidate}"; then
        return 1
    fi
    if ! extract_function "${candidate}" "${function_source}"; then
        return 1
    fi
    if ! extract_inventory "${function_source}" "${actual_inventory}"; then
        return 1
    fi
    if ! cmp -s "${expected_inventory}" "${actual_inventory}"; then
        return 1
    fi
    if ! assert_execution_contract "${candidate}" "${function_source}"; then
        return 1
    fi
}

test_mutation_rejected() {
    local name="$1"
    local mutation="$2"
    local candidate="${TEMP_ROOT}/${name}.sh"

    cp "${EXPORT_SCRIPT}" "${candidate}"
    case "${mutation}" in
        comment)
            run_bounded sed -i '\|src/Internal/Audit/Storage/AuditStoreFailure.php|d' "${candidate}"
            printf '\n# removed inventory path: src/Internal/Audit/Storage/AuditStoreFailure.php\n' >>"${candidate}"
            ;;
        existing-removal)
            run_bounded sed -i '\|src/Internal/Audit/Storage/AuditStoreFailure.php|d' "${candidate}"
            ;;
        added-entry)
            run_bounded awk '
                !inserted && $0 ~ /src\/Internal\/Audit\/Relay\/AuditRelayOutcome\.php/ {
                    print "        src/Internal/Audit/Relay/Unexpected.php \\"
                    inserted = 1
                }
                { print }
                END {
                    if (!inserted) exit 2
                }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        duplicate-entry)
            run_bounded awk '
                !inserted && $0 ~ /src\/Internal\/Audit\/Relay\/AuditRelayOutcome\.php/ {
                    print
                    print
                    inserted = 1
                    next
                }
                { print }
                END {
                    if (!inserted) exit 2
                }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        new-omission)
            run_bounded sed -i '\|src/Internal/Audit/Relay/AuditRelayOutcome.php|d' "${candidate}"
            ;;
        d1-omission)
            run_bounded sed -i '\|src/Internal/Audit/Catalog/FrameworkOperationalAuditEventCatalog.php|d' "${candidate}"
            ;;
        d2-omission)
            run_bounded sed -i '\|src/Internal/Audit/Read/AuditReadEventWriter.php|d' "${candidate}"
            ;;
        d2s1-interface-omission)
            run_bounded sed -i '\|src/Internal/Status/TenantScopedOperationStatusSource.php|d' "${candidate}"
            ;;
        d2s1-view-omission)
            run_bounded sed -i '\|src/Internal/Status/TenantBoundOperationStatusSource.php|d' "${candidate}"
            ;;
        d2s1-order-swap)
            run_bounded awk '
                !swapped && $0 ~ /src\/Internal\/Status\/TenantScopedOperationStatusSource\.php/ {
                    first = $0
                    if (getline second > 0 && second ~ /src\/Internal\/Status\/TenantBoundOperationStatusSource\.php/) {
                        print second
                        print first
                        swapped = 1
                        next
                    }
                    print first
                    if (second != "") print second
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        d2s2-query-omission)
            run_bounded sed -i '\|src/Internal/Status/AuditedOperationStatusQuery.php|d' "${candidate}"
            ;;
        d2s2-authorizer-omission)
            run_bounded sed -i '\|src/Internal/Status/OperationStatusAuditTenantAuthorizer.php|d' "${candidate}"
            ;;
        d2s2-execution-omission)
            run_bounded sed -i '\|src/Internal/Status/OperationStatusReadExecutionSource.php|d' "${candidate}"
            ;;
        d2s2-order-swap)
            run_bounded awk '
                !swapped && $0 ~ /src\/Internal\/Status\/AuditedOperationStatusQuery\.php/ {
                    first = $0
                    if (getline second > 0 && second ~ /src\/Internal\/Status\/OperationStatusAuditTenantAuthorizer\.php/ &&
                        getline third > 0 && third ~ /src\/Internal\/Status\/OperationStatusReadExecutionSource\.php/) {
                        print third
                        print second
                        print first
                        swapped = 1
                        next
                    }
                    print first
                    if (second != "") print second
                    if (third != "") print third
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        d2j-query-omission)
            run_bounded sed -i '\|src/Internal/OperationData/AuditedOperationJournalQuery.php|d' "${candidate}"
            ;;
        d2j-outcome-omission)
            run_bounded sed -i '\|src/Internal/OperationData/AuditedOperationOutcomeQuery.php|d' "${candidate}"
            ;;
        d2j-authorizer-omission)
            run_bounded sed -i '\|src/Internal/OperationData/OperationDataAuditTenantAuthorizer.php|d' "${candidate}"
            ;;
        d2j-journal-execution-omission)
            run_bounded sed -i '\|src/Internal/OperationData/OperationJournalReadExecutionSource.php|d' "${candidate}"
            ;;
        d2j-outcome-execution-omission)
            run_bounded sed -i '\|src/Internal/OperationData/OperationOutcomeReadExecutionSource.php|d' "${candidate}"
            ;;
        d2j-order-swap)
            run_bounded awk '
                !swapped && $0 ~ /src\/Internal\/OperationData\/AuditedOperationJournalQuery\.php/ {
                    first = $0
                    if (getline second > 0 && second ~ /src\/Internal\/OperationData\/AuditedOperationOutcomeQuery\.php/ &&
                        getline third > 0 && third ~ /src\/Internal\/OperationData\/OperationDataAuditTenantAuthorizer\.php/ &&
                        getline fourth > 0 && fourth ~ /src\/Internal\/OperationData\/OperationJournalReadExecutionSource\.php/ &&
                        getline fifth > 0 && fifth ~ /src\/Internal\/OperationData\/OperationOutcomeReadExecutionSource\.php/) {
                        print fifth
                        print fourth
                        print third
                        print second
                        print first
                        swapped = 1
                        next
                    }
                    print first
                    if (second != "") print second
                    if (third != "") print third
                    if (fourth != "") print fourth
                    if (fifth != "") print fifth
                    next
                }
                { print }
                END {
                    if (!swapped) exit 2
                }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        d2x-query-omission)
            run_bounded sed -i '\|src/Internal/Diagnostics/AuditedOperationDiagnosticsQuery.php|d' "${candidate}"
            ;;
        d2x-execution-omission)
            run_bounded sed -i '\|src/Internal/Diagnostics/OperationDiagnosticsReadExecutionSource.php|d' "${candidate}"
            ;;
        d2x-order-swap)
            run_bounded awk '
                !swapped && $0 ~ /src\/Internal\/Diagnostics\/AuditedOperationDiagnosticsQuery\.php/ {
                    first = $0
                    if (getline second > 0 && second ~ /src\/Internal\/Diagnostics\/OperationDiagnosticsReadExecutionSource\.php/) {
                        print second
                        print first
                        swapped = 1
                        next
                    }
                    print first
                    if (second != "") print second
                    next
                }
                { print }
                END {
                    if (!swapped) exit 2
                }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        d3-interface-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authentication/AuthenticationAuditEventWriter.php|d' "${candidate}"
            ;;
        d3-writer-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authentication/DurableAuthenticationAuditEventWriter.php|d' "${candidate}"
            ;;
        d3-adapter-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authentication/AuditedHttpAuthenticator.php|d' "${candidate}"
            ;;
        d4a-binding-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditPolicyBinding.php|d' "${candidate}"
            ;;
        d4a-registry-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditPolicyRegistry.php|d' "${candidate}"
            ;;
        d4a-evaluation-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditEvaluation.php|d' "${candidate}"
            ;;
        d4a-evaluator-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditEvaluator.php|d' "${candidate}"
            ;;
        d4b-factory-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditEventFactory.php|d' "${candidate}"
            ;;
        d4b-coordinator-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditCoordinator.php|d' "${candidate}"
            ;;
        d4b-attempt-omission)
            run_bounded sed -i '\|src/Internal/Audit/Authorization/AuthorizationAuditAttempt.php|d' "${candidate}"
            ;;
        d5h-writer-omission)
            run_bounded sed -i '\|src/Internal/Audit/Retention/RetentionHoldAuditWriter.php|d' "${candidate}"
            ;;
        d5h-port-omission)
            run_bounded sed -i '\|src/Internal/Retention/AuditedRetentionHoldPort.php|d' "${candidate}"
            ;;
        d2s1-duplicate-entry)
            run_bounded awk '
                !inserted && $0 ~ /src\/Internal\/Status\/TenantBoundOperationStatusSource\.php/ {
                    print
                    print
                    inserted = 1
                    next
                }
                { print }
                END {
                    if (!inserted) exit 2
                }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        order-swap)
            run_bounded awk '
                !swapped && $0 ~ /src\/Internal\/Audit\/Storage\/AuditStoreFailure\.php/ {
                    first = $0
                    if (getline second > 0 && second ~ /src\/Internal\/Audit\/Storage\/AuditStoreException\.php/) {
                        print second
                        print first
                        swapped = 1
                        next
                    }
                    print first
                    if (second != "") print second
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        duplicate-loop)
            run_bounded awk '
                $0 == "validate_package() {" {
                    in_function = 1
                }
                in_function && !inserted && $0 == "}" {
                    print "    for required_path in \\"
                    print "        composer.json; do"
                    print "        :"
                    print "    done"
                    inserted = 1
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        heredoc-decoy)
            run_bounded awk -v expected="${expected_inventory}" '
                skip {
                    if ($0 == "    done") {
                        skip = 0
                    }
                    next
                }
                $0 == "    for required_path in \\" {
                    print "    cat <<INVENTORY"
                    print "    for required_path in \\"
                    while ((getline line < expected) > 0) {
                        print "    " line " \\"
                    }
                    close(expected)
                    print "    ; do"
                    print "INVENTORY"
                    skip = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        quoted-decoy)
            run_bounded awk -v expected="${expected_inventory}" '
                skip {
                    if ($0 == "    done") {
                        skip = 0
                    }
                    next
                }
                $0 == "    for required_path in \\" {
                    print "    " sprintf("%c", 39)
                    print "    for required_path in \\"
                    while ((getline line < expected) > 0) {
                        print "    " line " \\"
                    }
                    close(expected)
                    print "    ; do"
                    print "    " sprintf("%c", 39)
                    skip = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        nonexecuted-decoy)
            run_bounded awk -v expected="${expected_inventory}" '
                skip {
                    if ($0 == "    done") {
                        skip = 0
                    }
                    next
                }
                $0 == "    for required_path in \\" {
                    print "    if false; then"
                    print "    for required_path in \\"
                    while ((getline line < expected) > 0) {
                        print "    " line " \\"
                    }
                    close(expected)
                    print "    ; do"
                    print "        test -f \"${package_root}/${required_path}\" \\"
                    print "            || fail \"${package_name} archive is missing required path: ${required_path}\""
                    print "    done"
                    print "    fi"
                    skip = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        wrapper-before-local)
            run_bounded awk '
                !inserted && $0 == "    local package_name=\"$1\"" {
                    print "    if true; then"
                    print
                    inserted = 1
                    next
                }
                inserted && !closed && $0 == "    done" {
                    print
                    print "    fi"
                    closed = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        early-return)
            run_bounded awk '
                !inserted && $0 == "    for required_path in \\" {
                    print "    return 0"
                    print
                    inserted = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        call-removal)
            run_bounded awk '
                !removed && $0 == "validate_package git \"${git_package}\"" {
                    removed = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        later-redefinition)
            run_bounded awk '
                !inserted && $0 == "validate_package git \"${git_package}\"" {
                    print "validate_package() {"
                    print "    :"
                    print "}"
                    inserted = 1
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        wrapped-call)
            run_bounded awk '
                !inserted && $0 == "validate_package git \"${git_package}\"" {
                    print "wrapped_validate_package() {"
                    print "    if false; then"
                    print "        for wrapper_iteration in 1; do"
                    print "            " $0
                    print "        done"
                    print "    fi"
                    print "}"
                    inserted = 1
                    next
                }
                { print }
            ' "${candidate}" >"${candidate}.tmp"
            mv "${candidate}.tmp" "${candidate}"
            ;;
        *)
            printf 'unknown inventory mutation: %s\n' "${mutation}" >&2
            return 1
            ;;
    esac

    if cmp -s "${EXPORT_SCRIPT}" "${candidate}"; then
        printf 'inventory mutation did not change candidate bytes: %s\n' "${name}" >&2
        return 1
    fi

    if assert_inventory "${candidate}"; then
        printf 'inventory mutation unexpectedly accepted: %s\n' "${name}" >&2
        return 1
    fi
}

test_call_contract_mutation_rejected() {
    local name="$1"
    local mutation="$2"
    local candidate="${TEMP_ROOT}/${name}.sh"
    local function_source="${TEMP_ROOT}/${name}-function"
    local actual_inventory="${TEMP_ROOT}/${name}-inventory"
    local phase_file="${TEMP_ROOT}/${name}-phases"
    local runtime_cases_before="${runtime_case_count}"
    local original_reviewed_export_sha="${reviewed_export_sha}"

    cp "${EXPORT_SCRIPT}" "${candidate}"
    run_bounded awk -v mutation="${mutation}" '
        BEGIN {
            call_line = "validate_package git \"${git_package}\""
            if (mutation == "logical-or-composer-continuation" || mutation == "pipeline-composer-continuation") {
                call_line = "validate_package composer \"${composer_package}\""
            }
            single_quote = sprintf("%c", 39)
            inserted = 0
        }
        {
            if (mutation == "resolution-override" && !inserted && $0 == "validate_package() {") {
                print "shopt -s expand_aliases; alias 'validate_package=:'"
                print ""
                inserted = 1
            }
            if (!inserted && $0 == call_line) {
                inserted = 1
                if (mutation == "if-wrapped-call") {
                    print "if true; then"
                    print "    " $0
                    print "fi"
                    next
                }
                if (mutation == "loop-wrapped-call") {
                    print "for call_contract_iteration in 1; do"
                    print "    " $0
                    print "done"
                    next
                }
                if (mutation == "nested-function-call") {
                    print "nested_call_contract() {"
                    print "    " $0
                    print "}"
                    next
                }
                if (mutation == "heredoc-call") {
                    print "cat <<" single_quote "CALL_CONTRACT_DECOY" single_quote
                    print "validate_package git \"${git_package}\""
                    print "CALL_CONTRACT_DECOY"
                    next
                }
                if (mutation == "quoted-call") {
                    print single_quote "validate_package git \"${git_package}\"" single_quote
                    next
                }
                if (mutation == "pre-call-return") {
                    print "return 0"
                    print $0
                    next
                }
                if (mutation == "pre-call-exit") {
                    print "exit 0"
                    print $0
                    next
                }
                if (mutation == "pre-call-if-exit") {
                    print "if true; then exit 0; fi"
                    print $0
                    next
                }
                if (mutation == "pre-call-if-return") {
                    print "if true; then"
                    print "    return 0"
                    print "fi"
                    print $0
                    next
                }
                if (mutation == "logical-or-composer-continuation") {
                    print "true ||"
                    print "    " $0
                    next
                }
                if (mutation == "logical-and-git-continuation") {
                    print "true &&"
                    print "    " $0
                    next
                }
                if (mutation == "pipeline-composer-continuation") {
                    print "true |"
                    print "    " $0
                    next
                }
                if (mutation == "command-substitution-git-continuation") {
                    print "call_contract_result=$("
                    print "    " $0
                    print ")"
                    next
                }
                print $0
                next
            }
            print
        }
        END {
            if (!inserted) {
                exit 2
            }
        }
    ' "${candidate}" >"${candidate}.tmp"
    mv "${candidate}.tmp" "${candidate}"

    if [[ "${mutation}" == 'resolution-override' ]]; then
        reviewed_export_sha="$(sha256sum "${candidate}" | cut -d' ' -f1)"
    fi

    if cmp -s "${EXPORT_SCRIPT}" "${candidate}"; then
        printf 'call-contract mutation did not change candidate bytes: %s\n' "${name}" >&2
        return 1
    fi
    if ! run_bounded bash -n "${candidate}"; then
        printf 'call-contract mutation is not valid Bash: %s\n' "${name}" >&2
        return 1
    fi

    printf 'candidate-function\n' >"${phase_file}"
    if ! extract_function "${candidate}" "${function_source}"; then
        printf 'call-contract mutation failed function extraction: %s\n' "${name}" >&2
        return 1
    fi
    printf 'inventory\n' >>"${phase_file}"
    if ! extract_inventory "${function_source}" "${actual_inventory}"; then
        printf 'call-contract mutation failed inventory extraction: %s\n' "${name}" >&2
        return 1
    fi
    if ! cmp -s "${expected_inventory}" "${actual_inventory}"; then
        printf 'call-contract mutation changed inventory: %s\n' "${name}" >&2
        return 1
    fi

    if assert_call_contract "${candidate}" "${phase_file}"; then
        printf 'call-contract mutation unexpectedly accepted: %s\n' "${name}" >&2
        return 1
    fi
    if ! cmp -s <(printf 'candidate-function\ninventory\ncall-contract\n') "${phase_file}"; then
        printf 'call-contract mutation reached an unexpected phase: %s\n' "${name}" >&2
        return 1
    fi
    if ! test "${runtime_case_count}" = "${runtime_cases_before}"; then
        printf 'call-contract mutation reached runtime validation: %s\n' "${name}" >&2
        return 1
    fi
    reviewed_export_sha="${original_reviewed_export_sha}"
}

test -f "${EXPORT_SCRIPT}"
test "$(sha256sum "${EXPORT_SCRIPT}" | cut -d' ' -f1)" = "${reviewed_export_sha}"
runtime_case_count=0
assert_inventory "${EXPORT_SCRIPT}"
test "$(wc -l < "${expected_inventory}")" -eq 109
test "$(sort -u "${expected_inventory}" | wc -l)" -eq 109
printf 'required inventory: PASS (109 ordered paths, 109 unique)\n'

test_mutation_rejected comment-only comment
test_mutation_rejected existing-entry-removal existing-removal
test_mutation_rejected added-entry added-entry
test_mutation_rejected duplicate-entry duplicate-entry
test_mutation_rejected new-entry-omission new-omission
test_mutation_rejected d1-entry-omission d1-omission
test_mutation_rejected d2-entry-omission d2-omission
test_mutation_rejected d2s1-interface-entry-omission d2s1-interface-omission
test_mutation_rejected d2s1-view-entry-omission d2s1-view-omission
test_mutation_rejected d2s1-reverse-new-block d2s1-order-swap
test_mutation_rejected d2s1-duplicate-new-entry d2s1-duplicate-entry
test_mutation_rejected d2s2-query-entry-omission d2s2-query-omission
test_mutation_rejected d2s2-authorizer-entry-omission d2s2-authorizer-omission
test_mutation_rejected d2s2-execution-entry-omission d2s2-execution-omission
test_mutation_rejected d2s2-reverse-new-block d2s2-order-swap
test_mutation_rejected d2j-query-entry-omission d2j-query-omission
test_mutation_rejected d2j-outcome-entry-omission d2j-outcome-omission
test_mutation_rejected d2j-authorizer-entry-omission d2j-authorizer-omission
test_mutation_rejected d2j-journal-execution-entry-omission d2j-journal-execution-omission
test_mutation_rejected d2j-outcome-execution-entry-omission d2j-outcome-execution-omission
test_mutation_rejected d2j-reverse-new-block d2j-order-swap
test_mutation_rejected d2x-query-entry-omission d2x-query-omission
test_mutation_rejected d2x-execution-entry-omission d2x-execution-omission
test_mutation_rejected d2x-reverse-new-block d2x-order-swap
test_mutation_rejected d3-interface-entry-omission d3-interface-omission
test_mutation_rejected d3-writer-entry-omission d3-writer-omission
test_mutation_rejected d3-adapter-entry-omission d3-adapter-omission
test_mutation_rejected d4a-binding-entry-omission d4a-binding-omission
test_mutation_rejected d4a-registry-entry-omission d4a-registry-omission
test_mutation_rejected d4a-evaluation-entry-omission d4a-evaluation-omission
test_mutation_rejected d4a-evaluator-entry-omission d4a-evaluator-omission
test_mutation_rejected d4b-factory-entry-omission d4b-factory-omission
test_mutation_rejected d4b-coordinator-entry-omission d4b-coordinator-omission
test_mutation_rejected d4b-attempt-entry-omission d4b-attempt-omission
test_mutation_rejected d5h-writer-entry-omission d5h-writer-omission
test_mutation_rejected d5h-port-entry-omission d5h-port-omission
test_mutation_rejected order-swap order-swap
test_mutation_rejected duplicate-loop duplicate-loop
test_mutation_rejected heredoc-decoy heredoc-decoy
test_mutation_rejected quoted-decoy quoted-decoy
test_mutation_rejected nonexecuted-decoy nonexecuted-decoy
test_mutation_rejected wrapper-before-local wrapper-before-local
test_mutation_rejected early-return early-return
test_mutation_rejected call-removal call-removal
test_mutation_rejected later-redefinition later-redefinition
test_mutation_rejected wrapped-call wrapped-call
test_call_contract_mutation_rejected if-wrapped-call if-wrapped-call
test_call_contract_mutation_rejected loop-wrapped-call loop-wrapped-call
test_call_contract_mutation_rejected nested-function-call nested-function-call
test_call_contract_mutation_rejected heredoc-call heredoc-call
test_call_contract_mutation_rejected quoted-call quoted-call
test_call_contract_mutation_rejected pre-call-return pre-call-return
test_call_contract_mutation_rejected pre-call-exit pre-call-exit
test_call_contract_mutation_rejected pre-call-if-exit pre-call-if-exit
test_call_contract_mutation_rejected pre-call-if-return pre-call-if-return
test_call_contract_mutation_rejected logical-or-composer-continuation logical-or-composer-continuation
test_call_contract_mutation_rejected logical-and-git-continuation logical-and-git-continuation
test_call_contract_mutation_rejected pipeline-composer-continuation pipeline-composer-continuation
test_call_contract_mutation_rejected command-substitution-git-continuation command-substitution-git-continuation
test_call_contract_mutation_rejected resolution-override resolution-override
printf 'required inventory call-contract mutations: PASS (14 rejected before runtime/SHA)\n'
printf 'required inventory mutations: PASS (60 rejected: 46 inventory, 14 call-contract)\n'
