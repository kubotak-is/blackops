#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

fail() {
    printf 'Version baseline guard failed: %s\n' "$1" >&2
    exit 1
}

contains() {
    local file="$1"
    local pattern="$2"
    grep -Fq -- "$pattern" "${repository_root}/${file}" \
        || fail "${file} does not contain expected version contract: ${pattern}"
}

absent() {
    local file="$1"
    local pattern="$2"
    ! grep -Fq -- "$pattern" "${repository_root}/${file}" \
        || fail "${file} contains a forbidden version claim: ${pattern}"
}

assert_storage_key_contract() {
    local file="$1"

    contains "${file}" 'set -euo pipefail'
    contains "${file}" 'test -n "${storage_key}"'
    contains "${file}" 'test "${decoded_storage_key_length}" -eq 32'
    contains "${file}" 'test "$(grep -c '\''^BLACKOPS_STORAGE_KEY='\'' "${CONSUMER}/.env")" -eq 1'
    contains "${file}" 'test "$(grep -c '\''^BLACKOPS_STORAGE_KEY=$'\'' "${CONSUMER}/.env")" -eq 0'
    contains "${file}" 'test "$(stat -c '\''%a'\'' "${CONSUMER}/.env")" = 600'
    contains "${file}" 'unset storage_key decoded_storage_key_length'

    awk '
        /^[[:space:]]*umask[[:space:]]+077[[:space:]]*$/ {
            umask_count++
            umask_line = NR
        }
        index($0, "cp \"${CONSUMER}/.env.example\" \"${CONSUMER}/.env\"") {
            env_copy_count++
            env_copy_line = NR
        }
        index($0, "storage_key=\"$(head -c 32 /dev/urandom | base64 -w 0)\"") {
            generation_count++
            generation_line = NR
        }
        index($0, "test -n \"${storage_key}\"") {
            nonempty_count++
            nonempty_line = NR
        }
        index($0, "decoded_storage_key_length=\"$(printf '\''%s'\'' \"${storage_key}\" | base64 --decode | wc -c)\"") {
            decoded_assignment_count++
            decoded_assignment_line = NR
        }
        index($0, "test \"${decoded_storage_key_length}\" -eq 32") {
            decoded_test_count++
            decoded_test_line = NR
        }
        index($0, "sed -i \"s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|\" \"${CONSUMER}/.env\"") {
            write_count++
            write_line = NR
        }
        index($0, "test \"$(grep -c '\''^BLACKOPS_STORAGE_KEY='\'' \"${CONSUMER}/.env\")\" -eq 1") {
            assignment_count_count++
            assignment_count_line = NR
        }
        index($0, "test \"$(grep -c '\''^BLACKOPS_STORAGE_KEY=$'\'' \"${CONSUMER}/.env\")\" -eq 0") {
            empty_count_count++
            empty_count_line = NR
        }
        index($0, "test \"$(stat -c '\''%a'\'' \"${CONSUMER}/.env\")\" = 600") {
            mode_count++
            mode_line = NR
        }
        /^[[:space:]]*unset[[:space:]]+storage_key[[:space:]]+decoded_storage_key_length[[:space:]]*$/ {
            unset_count++
            unset_line = NR
        }
        umask_line && !first_runtime_line {
            if (/docker[[:space:]]+(run|compose)/ ||
                /composer[[:space:]]/ ||
                /\$\{(COMPOSE|compose|INSTALL_COMPOSE|install_compose)\[@\]\}/) {
                first_runtime_line = NR
            }
        }
        END {
            if (umask_count != 1 || env_copy_count != 1 || generation_count != 1 ||
                nonempty_count != 1 || decoded_assignment_count != 1 || decoded_test_count != 1 ||
                write_count != 1 || assignment_count_count != 1 || empty_count_count != 1 ||
                mode_count != 1 || unset_count != 1 || !first_runtime_line ||
                !(umask_line < env_copy_line && env_copy_line < generation_line &&
                  generation_line < nonempty_line && nonempty_line < decoded_assignment_line &&
                  decoded_assignment_line < decoded_test_line && decoded_test_line < write_line &&
                  write_line < assignment_count_line && assignment_count_line < empty_count_line &&
                  empty_count_line < mode_line && mode_line < unset_line &&
                  unset_line < first_runtime_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail "${file} must preserve fail-closed Storage Key preparation order through its first Docker/Composer command"
}

assert_guide_storage_key_contract() {
    local file="$1"

    contains "${file}" 'set -euo pipefail'
    contains "${file}" 'chmod 600 .env'
    contains "${file}" 'test "$(stat -c '\''%a'\'' .env)" = 600'
    contains "${file}" 'storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"'
    contains "${file}" 'test "${decoded_storage_key_length}" -eq 32'
    contains "${file}" 'sed -i "s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|" .env'

    awk '
        index($0, "chmod 600 .env") { chmod_line = NR; chmod_count++ }
        index($0, "stat -c") && index($0, ".env") { mode_line = NR; mode_count++ }
        index($0, "sed -i") && index($0, "BLACKOPS_STORAGE_KEY") && index($0, ".env") { write_line = NR; write_count++ }
        END {
            if (chmod_count != 1 || mode_count != 1 || write_count != 1 ||
                !(chmod_line < mode_line && mode_line < write_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail "${file} must chmod and verify .env mode 600 before writing the Storage Key"
}

assert_installation_key_convergence() {
    local file='docs/guide/installation.md'

    awk '
        index($0, "composer create-project blackops/skeleton my-app 1.2.0") && !normal_line { normal_line = NR }
        index($0, "composer create-project --no-scripts blackops/skeleton my-app 1.2.0") { no_scripts_line = NR }
        index($0, "php bin/setup") { setup_line = NR }
        index($0, "normal／`--no-scripts`のどちらも") { convergence_line = NR }
        index($0, "chmod 600 .env") { key_line = NR }
        END {
            if (!normal_line || !no_scripts_line || !setup_line || !convergence_line || !key_line ||
                !(normal_line < no_scripts_line && no_scripts_line < setup_line &&
                  setup_line < convergence_line && convergence_line < key_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail "${file} normal and --no-scripts lanes must converge before the shared required key step"
}

assert_mvp_key_convergence() {
    local file='docs/guide/mvp-sample.md'

    awk '
        index($0, "composer create-project blackops/skeleton my-app 1.2.0") && !normal_create_line { normal_create_line = NR }
        index($0, "php bin/setup") && !normal_setup_line { normal_setup_line = NR }
        index($0, "composer create-project --no-scripts blackops/skeleton my-app 1.2.0") { no_scripts_create_line = NR }
        index($0, "php bin/setup") && normal_setup_line && NR > normal_setup_line && !no_scripts_setup_line { no_scripts_setup_line = NR }
        index($0, "normal／`--no-scripts`のどちらも、Setup直後に次の同じ必須Key Stepを実行します。") { convergence_line = NR }
        index($0, "chmod 600 .env") { chmod_line = NR }
        index($0, "stat -c") && index($0, ".env") { mode_line = NR }
        index($0, "sed -i") && index($0, "BLACKOPS_STORAGE_KEY") && index($0, ".env") { write_line = NR }
        END {
            if (!normal_create_line || !normal_setup_line || !no_scripts_create_line || !no_scripts_setup_line ||
                !convergence_line || !chmod_line || !mode_line || !write_line ||
                !(normal_create_line < normal_setup_line && normal_setup_line < no_scripts_create_line &&
                  no_scripts_create_line < no_scripts_setup_line && no_scripts_setup_line < convergence_line &&
                  convergence_line < chmod_line && chmod_line < mode_line && mode_line < write_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail "${file} normal and --no-scripts setup must converge before the shared verified key block"
}

deptrac_layer_block() {
    local layer="$1"

    awk -v layer="${layer}" '
        $0 == "    - name: " layer { inside = 1; print; next }
        inside && /^    - name:/ { exit }
        inside { print }
    ' "${repository_root}/deptrac.yaml"
}

deptrac_ruleset_block() {
    local layer="$1"

    awk -v layer="${layer}" '
        $0 == "    " layer ":" { inside = 1; next }
        inside && /^    [A-Za-z]/ { exit }
        inside { print }
    ' "${repository_root}/deptrac.yaml"
}

assert_deptrac_collector() {
    local layer="$1"
    local pattern="$2"

    deptrac_layer_block "${layer}" | grep -Fxq -- "          value: '/${pattern}/'" \
        || fail "deptrac ${layer} collector is missing exact pattern: ${pattern}"
}

assert_deptrac_rule() {
    local layer="$1"
    local target="$2"

    deptrac_ruleset_block "${layer}" | grep -Fxq -- "      - ${target}" \
        || fail "deptrac ${layer} ruleset is missing bounded target: ${target}"
}

assert_deptrac_no_rule() {
    local layer="$1"
    local target="$2"

    ! deptrac_ruleset_block "${layer}" | grep -Fxq -- "      - ${target}" \
        || fail "deptrac ${layer} ruleset contains forbidden generic target: ${target}"
}

assert_deptrac_sccs() {
    local actual
    local expected

    actual="$(awk '
        function add_layer(name) {
            if (!(name in layer_index)) {
                layer_index[name] = ++layer_count
                layer_name[layer_count] = name
            }
        }
        /^  ruleset:/ {
            in_ruleset = 1
            next
        }
        !in_ruleset { next }
        /^    [A-Za-z][A-Za-z0-9_]*:$/ {
            layer = $0
            sub(/^    /, "", layer)
            sub(/:$/, "", layer)
            add_layer(layer)
            next
        }
        /^      - / && layer != "" {
            target = $0
            sub(/^      - /, "", target)
            add_layer(target)
            edge[layer_index[layer], layer_index[target]] = 1
            next
        }
        END {
            for (i = 1; i <= layer_count; i++) {
                reachable[i, i] = 1
            }
            for (key in edge) {
                split(key, pair, SUBSEP)
                reachable[pair[1], pair[2]] = 1
            }
            for (via = 1; via <= layer_count; via++) {
                for (from = 1; from <= layer_count; from++) {
                    if (reachable[from, via]) {
                        for (to = 1; to <= layer_count; to++) {
                            if (reachable[via, to]) {
                                reachable[from, to] = 1
                            }
                        }
                    }
                }
            }
            for (from = 1; from <= layer_count; from++) {
                if (visited[from]) {
                    continue
                }
                member_count = 0
                members = ""
                for (to = from; to <= layer_count; to++) {
                    if (reachable[from, to] && reachable[to, from]) {
                        visited[to] = 1
                        members = members (member_count++ ? "," : "") layer_name[to]
                    }
                }
                if (member_count > 1) {
                    print members
                }
            }
        }
    ' "${repository_root}/deptrac.yaml")"
    actual="$(printf '%s\n' "${actual}" | while IFS= read -r scc; do
        test -n "${scc}" || continue
        printf '%s\n' "${scc}" | tr ',' '\n' | sort | paste -sd, -
    done | sort)"
    expected=$'Application,Auth,Http,Internal,InternalApplication,InternalAuth,InternalHttp,InternalIdempotency\nCore,Idempotency,Telemetry'

    [ "${actual}" = "${expected}" ] \
        || fail "deptrac non-trivial SCCs changed (expected Core/Idempotency/Telemetry and Application/Auth/Http/Internal plus four implementation layers): ${actual}"
}

assert_deptrac_collector InternalApplication '^BlackOps\\Internal\\Application(\\|$)'
assert_deptrac_collector InternalAuth '^BlackOps\\Internal\\Auth(\\|$)'
assert_deptrac_collector InternalHttp '^BlackOps\\Internal\\Http(\\|$)'
assert_deptrac_collector InternalIdempotency '^BlackOps\\Internal\\Idempotency(\\|$)'
assert_deptrac_collector InternalSapiRuntime '^BlackOps\\Internal\\Runtime\\FrankenPhp(\\|$)'
assert_deptrac_collector Internal '^BlackOps\\Internal(?!(?:\\Application|\\Auth|\\Http|\\Idempotency|\\Telemetry|\\StorageProtection)(?:\\|$)|\\Runtime\\FrankenPhp(?:\\|$)|\\Execution\\DeferredOperationContextValidator$).*'

assert_deptrac_rule Application InternalApplication
assert_deptrac_rule Auth InternalAuth
assert_deptrac_rule Http InternalHttp
assert_deptrac_rule Http InternalIdempotency
assert_deptrac_rule Http InternalSapiRuntime
assert_deptrac_no_rule Application Internal
assert_deptrac_no_rule Auth Internal
assert_deptrac_no_rule Http Internal
assert_deptrac_sccs

contains Dockerfile 'COMPOSER_ROOT_VERSION=1.2.0@dev'
contains composer.json '"carthage-software/mago": "1.42.0"'
contains composer.json '"deptrac/deptrac": "4.7.1"'
contains mago.toml 'baseline = "mago-lint-baseline.toml"'
contains .gitattributes '/mago-lint-baseline.toml export-ignore'
contains composer.json '"/mago-lint-baseline.toml"'
contains mago.toml 'baseline-variant = "strict"'
contains .github/workflows/ci.yml 'mago lint --verify-baseline'
test "$(grep -c '^variant = "strict"$' "${repository_root}/mago-lint-baseline.toml")" -eq 1 \
    || fail 'Mago baseline must use exactly one strict variant declaration'
contains examples/quickstart/composer.json '"blackops/framework": "^1.2"'
contains src/Internal/Telemetry/TelemetryTracer.php "public const VERSION = '1.2.0';"
contains src/Internal/Telemetry/TelemetryMetrics.php "public const VERSION = '1.2.0';"

for consumer in \
    tests/Consumer/quickstart-e2e.sh \
    tests/Consumer/auth-generator-fresh.sh \
    tests/Consumer/scheduled-operation.sh \
    tests/Consumer/storage-protection-rotation.sh \
    tests/Consumer/frankenphp-worker-mode.sh; do
    contains "${consumer}" 'blackops/framework":"1.2.0'
done

for consumer in \
    tests/Consumer/auth-generator-fresh.sh \
    tests/Consumer/frankenphp-worker-mode.sh \
    tests/Consumer/scheduled-operation.sh; do
    contains "${consumer}" 'umask 077'
    contains "${consumer}" 'storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"'
    contains "${consumer}" 'decoded_storage_key_length="$(printf'
    contains "${consumer}" 'base64 --decode | wc -c)"'
    contains "${consumer}" 'chmod 600 "${CONSUMER}/.env"'
    assert_storage_key_contract "${consumer}"
done

contains tests/Consumer/skeleton-create-project.sh '"blackops/framework": "1.2.0"'
contains tests/Consumer/skeleton-create-project.sh 'blackops/skeleton":"1.2.0"'
contains tests/Consumer/skeleton-publication.sh 'version=1.2.0'
contains tests/Consumer/skeleton-publication-workflow.sh 'run_publication "${new_remote}" 1.2.0 false'

assert_skeleton_workflow_toolchain() {
    awk '
        index($0, "uses: jdx/mise-action@v4") {
            mise_line = NR
        }
        index($0, "install: true") {
            install_line = NR
        }
        index($0, "cache: true") {
            cache_line = NR
        }
        index($0, "test \"$(node --version)\" = \"v24.18.0\"") {
            node_line = NR
        }
        index($0, "test \"$(pnpm --version)\" = \"11.12.0\"") {
            pnpm_line = NR
        }
        index($0, "bash tests/Consumer/quickstart-e2e.sh") {
            consumer_line = NR
        }
        END {
            if (!mise_line || !install_line || !cache_line || !node_line ||
                !pnpm_line || !consumer_line ||
                !(mise_line < install_line && install_line < cache_line &&
                  cache_line < node_line && node_line < pnpm_line &&
                  pnpm_line < consumer_line)) {
                exit 1
            }
        }
    ' "${repository_root}/.github/workflows/publish-skeleton.yml" \
        || fail 'Skeleton publication Workflow must install and verify the pinned mise toolchain before Consumer gates'
}

assert_skeleton_workflow_toolchain

assert_quickstart_output_drain() {
    local file='tests/Consumer/quickstart-e2e.sh'

    contains "${file}" 'if test -n "${BLACKOPS_REPOSITORY_ROOT:-}"; then'
    contains "${file}" 'ROOT=$(cd "${BLACKOPS_REPOSITORY_ROOT}" && pwd)'
    contains "${file}" 'ROOT=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)'
    contains "${file}" 'database_status="$(HTTP_PORT='
    contains "${file}" 'retention_plan_output="$(HTTP_PORT='
    contains "${file}" 'retention_purge_output="$(HTTP_PORT='
    contains "${file}" 'grep -q '\''pending:'\'' <<<"${database_status}"'
    contains "${file}" 'grep -q '\''Total:'\'' <<<"${retention_plan_output}"'
    contains "${file}" 'grep -q '\''dry run'\'' <<<"${retention_purge_output}"'

    if rg -n -F 'database:status | grep -q' "${repository_root}/${file}" \
        || rg -n -F 'retention:plan | grep -q' "${repository_root}/${file}" \
        || rg -n -F 'retention:purge --dry-run | grep -q' "${repository_root}/${file}"; then
        fail "${file} must drain Docker Compose output before marker assertions"
    fi
}

assert_manual_recovery_harness() {
    local file='.github/workflows/publish-skeleton.yml'

    contains "${file}" 'ref: refs/tags/${{ inputs.release_version || github.ref_name }}'
    contains "${file}" 'DISPATCH_SHA: ${{ github.sha }}'
    contains "${file}" 'if test "${MANUAL_RECOVERY}" ='
    contains "${file}" 'git fetch --quiet --no-tags origin "${DISPATCH_SHA}"'
    contains "${file}" 'git diff --quiet "${release_commit}" "${DISPATCH_SHA}" --'
    contains "${file}" "':(exclude)examples/quickstart/README.md'"
    contains "${file}" 'release_commit}:${harness_path}'
    contains "${file}" 'src composer.json examples/quickstart resources migrations'
    contains "${file}" 'tests/Consumer/framework-update-generators.sh'
    contains "${file}" 'harness_paths=('
    contains "${file}" 'restore_harnesses()'
    contains "${file}" 'for harness_path in "${harness_paths[@]}"; do'
    contains "${file}" 'git checkout -- "${harness_path}"'
    contains "${file}" 'git show "${DISPATCH_SHA}:${harness_path}" > "${harness_path}"'
    contains "${file}" 'test "$(git hash-object "${harness_path}")" = "$(git rev-parse "${release_commit}:${harness_path}")"'
    contains "${file}" 'test "$(git hash-object "${harness_path}")" = "${dispatch_harness_blob}"'
    contains "${file}" 'BLACKOPS_REPOSITORY_ROOT="${GITHUB_WORKSPACE}" bash tests/Consumer/quickstart-e2e.sh'
    contains "${file}" 'bash tests/Consumer/skeleton-create-project.sh'
    contains "${file}" 'bash tests/Consumer/framework-update-generators.sh'
    contains "${file}" 'git diff --quiet "${release_commit}" --'

    awk '
        index($0, "ref: refs/tags/${{ inputs.release_version || github.ref_name }}") { tag_line = NR }
        index($0, "DISPATCH_SHA: ${{ github.sha }}") { dispatch_line = NR }
        index($0, "release_commit=\"$(git rev-parse HEAD)\"") { release_line = NR }
        index($0, "git diff --quiet \"${release_commit}\" \"${DISPATCH_SHA}\"") { drift_line = NR }
        index($0, "harness_paths=(") { harness_paths_line = NR; harness_paths_open = 1 }
        harness_paths_open && /^[[:space:]]*\)/ { harness_paths_close_line = NR; harness_paths_open = 0 }
        harness_paths_open && /^[[:space:]]+tests\/Consumer\/quickstart-e2e\.sh[[:space:]]*$/ { quickstart_member_line = NR }
        harness_paths_open && /^[[:space:]]+tests\/Consumer\/framework-update-generators\.sh[[:space:]]*$/ { generator_member_line = NR }
        index($0, "git show \"${DISPATCH_SHA}:${harness_path}\" > \"${harness_path}\"") { overlay_line = NR }
        index($0, "test \"$(git hash-object \"${harness_path}\")\" = \"${dispatch_harness_blob}\"") { hash_check_line = NR }
        index($0, "BLACKOPS_REPOSITORY_ROOT=\"${GITHUB_WORKSPACE}\" bash tests/Consumer/quickstart-e2e.sh") { manual_run_line = NR }
        index($0, "bash tests/Consumer/skeleton-create-project.sh") {
            if (!manual_skeleton_line) {
                manual_skeleton_line = NR
            }
        }
        index($0, "bash tests/Consumer/framework-update-generators.sh") {
            if (!manual_generator_line) {
                manual_generator_line = NR
            }
        }
        /^[[:space:]]*restore_harnesses\(\)/ { restore_definition_line = NR }
        restore_definition_line && !restore_loop_line && index($0, "for harness_path in \"${harness_paths[@]}\"; do") { restore_loop_line = NR }
        index($0, "git checkout -- \"${harness_path}\"") { restore_checkout_line = NR }
        restore_definition_line && !restore_close_line && /^[[:space:]]*}[[:space:]]*$/ { restore_close_line = NR }
        restore_definition_line && index($0, "git rev-parse \"${release_commit}:") { restore_hash_check_line = NR }
        index($0, "trap restore_harnesses EXIT") { trap_install_line = NR }
        /^[[:space:]]*restore_harnesses[[:space:]]*$/ { restore_line = NR }
        index($0, "trap - EXIT") { trap_clear_line = NR }
        /^          else$/ { else_line = NR }
        else_line && index($0, "bash tests/Consumer/quickstart-e2e.sh") && !ordinary_run_line { ordinary_run_line = NR }
        else_line && index($0, "bash tests/Consumer/skeleton-create-project.sh") && !ordinary_skeleton_line { ordinary_skeleton_line = NR }
        else_line && index($0, "bash tests/Consumer/framework-update-generators.sh") && !ordinary_generator_line { ordinary_generator_line = NR }
        END {
            if (!tag_line || !dispatch_line || !release_line || !drift_line ||
                !harness_paths_line || !harness_paths_close_line || !quickstart_member_line ||
                !generator_member_line || !overlay_line || !hash_check_line || !manual_run_line ||
                !manual_skeleton_line || !manual_generator_line || !restore_definition_line ||
                !restore_loop_line || !restore_checkout_line || !restore_close_line || !restore_hash_check_line || !trap_install_line ||
                !restore_line || !trap_clear_line || !else_line || !ordinary_run_line ||
                !ordinary_skeleton_line || !ordinary_generator_line ||
                !(tag_line < dispatch_line && dispatch_line < release_line &&
                release_line < drift_line && drift_line < harness_paths_line &&
                harness_paths_line < quickstart_member_line && quickstart_member_line < generator_member_line &&
                generator_member_line < harness_paths_close_line && harness_paths_close_line < restore_definition_line &&
                restore_definition_line < restore_loop_line && restore_loop_line < restore_checkout_line &&
                restore_checkout_line < restore_hash_check_line && restore_hash_check_line < restore_close_line &&
                restore_close_line < trap_install_line && trap_install_line < overlay_line &&
                overlay_line < hash_check_line && hash_check_line < manual_run_line &&
                manual_run_line < manual_skeleton_line && manual_skeleton_line < manual_generator_line &&
                manual_generator_line < restore_line &&
                restore_line < trap_clear_line && trap_clear_line < else_line && else_line < ordinary_run_line &&
                ordinary_run_line < ordinary_skeleton_line && ordinary_skeleton_line < ordinary_generator_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail 'Manual Recovery must overlay only the dispatch-SHA harness after release-runtime equality and retain the ordinary tag path'
}

contains .github/workflows/publish-skeleton.yml "':(exclude)examples/quickstart/README.md'"
contains tests/Consumer/framework-update-runtime.sh "':(exclude)examples/quickstart/README.md'"

assert_quickstart_output_drain
assert_manual_recovery_harness

assert_website_font_contract() {
    contains docs/website/package.json '"blume": "1.3.0"'
    contains docs/website/blume.config.ts "localFont('UbuntuSans.ttf')"
    contains docs/website/blume.config.ts "localFont('UbuntuMono.ttf')"
    absent docs/website/blume.config.ts 'fonts.googleapis.com'
    absent docs/website/blume.config.ts 'fonts.gstatic.com'
    contains docs/website/theme.css 'var(--blume-font-body, ui-sans-serif)'
    contains docs/website/theme.css 'var(--blume-font-mono, ui-monospace, monospace)'
    contains docs/website/scripts/check-artifact.mjs 'fontProviders\.google|fonts\.googleapis\.com|fonts\.gstatic\.com'
    contains docs/website/scripts/check-artifact.mjs 'generatedProviders.length !== 2'
    contains docs/website/scripts/check-artifact.mjs 'fontProviders\.([A-Za-z0-9_]+)\s*\('
    contains docs/website/scripts/check-artifact.mjs '@font-face'
    contains docs/website/scripts/check-artifact.mjs 'expectedAssets'
    contains docs/website/scripts/check-artifact.mjs '28c4c189a44803b1986fd16074187034dc6d94ad35f5e87de13dd0e786b70b73'
    contains docs/website/scripts/check-artifact.mjs 'fbf1e748836994f730e602f7dcf2525564d6d78aa336080cbb73af909d0e08ee'
    contains docs/website/scripts/check-artifact.mjs 'bca346a561b9668925ff55af1fcf0e10e65e07b1b40dd057bb4f3ded848ef8cf'
    contains docs/website/scripts/check-artifact.mjs 'Ubuntu-Font-Licence-1.0'
    contains docs/website/scripts/check-artifact.mjs 'docs/website/.blume/astro.config.mjs'
    contains docs/website/scripts/check-artifact.mjs 'fontProviders.local()'
    contains docs/website/scripts/check-artifact.mjs 'localFontReferences'
    contains docs/website/scripts/check-artifact.mjs 'Ubuntu-Font-License-1.0.txt'
    contains docs/website/tests/reader-experience.test.mjs "localFont\\('UbuntuSans\\.ttf'\\)"
    contains docs/website/tests/reader-experience.test.mjs '"blume": "1\.3\.0"'
    contains docs/website/tests/reader-experience.test.mjs 'assertLocalProviderOnly'
    contains docs/website/tests/reader-experience.test.mjs "fontProviders.fontsource({ family: 'Inter' })"
    test -s "${repository_root}/docs/website/public/fonts/UbuntuSans.ttf" \
        || fail 'Website Ubuntu Sans asset must be present and non-empty'
    test -s "${repository_root}/docs/website/public/fonts/UbuntuMono.ttf" \
        || fail 'Website Ubuntu Mono asset must be present and non-empty'
    test -s "${repository_root}/docs/website/public/licenses/Ubuntu-Font-License-1.0.txt" \
        || fail 'Website Ubuntu font license must be present and non-empty'
    test "$(sha256sum "${repository_root}/docs/website/public/fonts/UbuntuSans.ttf" | cut -d' ' -f1)" = \
        '28c4c189a44803b1986fd16074187034dc6d94ad35f5e87de13dd0e786b70b73' \
        || fail 'Website Ubuntu Sans asset checksum changed'
    test "$(sha256sum "${repository_root}/docs/website/public/fonts/UbuntuMono.ttf" | cut -d' ' -f1)" = \
        'fbf1e748836994f730e602f7dcf2525564d6d78aa336080cbb73af909d0e08ee' \
        || fail 'Website Ubuntu Mono asset checksum changed'
    test "$(sha256sum "${repository_root}/docs/website/public/licenses/Ubuntu-Font-License-1.0.txt" | cut -d' ' -f1)" = \
        'bca346a561b9668925ff55af1fcf0e10e65e07b1b40dd057bb4f3ded848ef8cf' \
        || fail 'Website Ubuntu font license checksum changed'
    contains docs/website/public/licenses/Ubuntu-Font-License-1.0.txt 'Ubuntu-Font-Licence-1.0'
}

assert_website_font_contract

manual_recovery_fixture_contract() {
    awk '
        /^[[:space:]]*restore_harnesses\(\)/ {
            restore_definition_line = NR
            restore_body = 1
        }
        restore_body && /^[[:space:]]*}[[:space:]]*$/ {
            restore_close_line = NR
            restore_body = 0
        }
        restore_body && index($0, "git checkout -- \"${harness_path}\"") {
            restore_checkout_line = NR
        }
        restore_body && index($0, "git rev-parse \"${release_commit}:") {
            restore_hash_line = NR
        }
        index($0, "trap restore_harnesses EXIT") { trap_install_line = NR }
        /^[[:space:]]*restore_harnesses[[:space:]]*$/ { restore_line = NR }
        index($0, "trap - EXIT") && !trap_clear_line { trap_clear_line = NR }
        index($0, "git show \"${DISPATCH_SHA}:${harness_path}\" > \"${harness_path}\"") {
            overlay_line = NR
        }
        END {
            if (!restore_definition_line || !restore_close_line ||
                !restore_checkout_line || !restore_hash_line ||
                !trap_install_line || !overlay_line || !restore_line ||
                !trap_clear_line ||
                !(restore_definition_line < restore_checkout_line &&
                  restore_checkout_line < restore_hash_line &&
                  restore_hash_line < restore_close_line &&
                  restore_close_line < trap_install_line &&
                  trap_install_line < overlay_line &&
                  overlay_line < restore_line &&
                  restore_line < trap_clear_line)) {
                exit 1
            }
        }
    ' "$1"
}

assert_manual_recovery_negative_fixtures() (
    local source_file="${repository_root}/.github/workflows/publish-skeleton.yml"
    local fixture

    fixture="$(mktemp)"
    trap 'rm -f "${fixture}"' EXIT

    awk '
        /^[[:space:]]*restore_harnesses\(\)/ {
            print
            print "                :"
            in_restore = 1
            next
        }
        in_restore && /^[[:space:]]*}[[:space:]]*$/ {
            print
            in_restore = 0
            next
        }
        in_restore { next }
        { print }
    ' "${source_file}" >"${fixture}"
    if manual_recovery_fixture_contract "${fixture}"; then
        fail 'Manual Recovery empty restore fixture must be rejected'
    fi

    awk '
        index($0, "git checkout --") {
            checkout_line = $0
            next
        }
        /^[[:space:]]*restore_harnesses\(\)/ { in_restore = 1 }
        in_restore && /^[[:space:]]*}[[:space:]]*$/ {
            print
            print checkout_line
            checkout_line = ""
            in_restore = 0
            next
        }
        { print }
    ' "${source_file}" >"${fixture}"
    test "$(grep -Fc 'git checkout -- "${harness_path}"' "${fixture}")" -eq 1 \
        || fail 'Manual Recovery function-external checkout fixture must retain exactly one checkout line'
    test "$(awk '
        /^[[:space:]]*restore_harnesses\(\)/ { in_restore=1; next }
        in_restore && /^[[:space:]]*}[[:space:]]*$/ { in_restore=0; next }
        in_restore && index($0, "git checkout --") { count++ }
        END { print count + 0 }
    ' "${fixture}")" -eq 0 \
        || fail 'Manual Recovery function-external checkout fixture must place checkout outside restore_harnesses'
    if manual_recovery_fixture_contract "${fixture}"; then
        fail 'Manual Recovery function-external checkout fixture must be rejected'
    fi

    grep -v 'git rev-parse "${release_commit}:' "${source_file}" >"${fixture}"
    if manual_recovery_fixture_contract "${fixture}"; then
        fail 'Manual Recovery restore-hash fixture must be rejected'
    fi

    awk '
        /^[[:space:]]*restore_harnesses[[:space:]]*$/ && !inserted {
            print "            trap - EXIT"
            inserted = 1
        }
        { print }
    ' "${source_file}" >"${fixture}"
    if manual_recovery_fixture_contract "${fixture}"; then
        fail 'Manual Recovery early-trap-clear fixture must be rejected'
    fi
)

assert_manual_recovery_negative_fixtures

runtime_exclusion_contract() {
    awk '
        index($0, ":(exclude)") {
            exclusion_count++
            if (index($0, ":(exclude)examples/quickstart/README.md")) exact_count++
        }
        END { exit !(exclusion_count == 1 && exact_count == 1) }
    ' "$1"
}

assert_runtime_exclusion_negative_fixtures() (
    local source_file
    local fixture

    fixture="$(mktemp)"
    trap 'rm -f "${fixture}"' EXIT
    for source_file in \
        "${repository_root}/.github/workflows/publish-skeleton.yml" \
        "${repository_root}/tests/Consumer/framework-update-runtime.sh"; do
        if ! runtime_exclusion_contract "${source_file}"; then
            fail "${source_file} must contain exactly one README-only runtime exclusion"
        fi
        sed 's#:(exclude)examples/quickstart/README\.md#:(exclude)examples/quickstart/**#' "${source_file}" >"${fixture}"
        if runtime_exclusion_contract "${fixture}"; then
            fail "${source_file} broad runtime exclusion fixture must be rejected"
        fi
        cp "${source_file}" "${fixture}"
        printf '%s\n' "':(exclude)examples/quickstart/README.md'" >>"${fixture}"
        if runtime_exclusion_contract "${fixture}"; then
            fail "${source_file} extra runtime exclusion fixture must be rejected"
        fi
    done
)

assert_runtime_exclusion_negative_fixtures

contains tests/Consumer/version-baseline.sh 'manual_recovery_fixture_contract()'
contains tests/Consumer/version-baseline.sh 'assert_manual_recovery_negative_fixtures()'
contains tests/Consumer/version-baseline.sh 'assert_release_runtime_diff_contract()'

assert_release_runtime_diff_contract() (
    local fixture_root
    local base_commit
    local readme_commit
    local runtime_commit

    fixture_root="$(mktemp -d)"
    trap 'rm -rf "${fixture_root}"' EXIT
    git init --quiet "${fixture_root}"
    git -C "${fixture_root}" config user.name 'Release Runtime Contract'
    git -C "${fixture_root}" config user.email 'release-runtime-contract@invalid.example'
    mkdir -p "${fixture_root}/src" "${fixture_root}/examples/quickstart" \
        "${fixture_root}/resources" "${fixture_root}/migrations"
    touch "${fixture_root}/composer.json" "${fixture_root}/src/runtime.php" \
        "${fixture_root}/examples/quickstart/bootstrap.php" \
        "${fixture_root}/examples/quickstart/README.md"
    git -C "${fixture_root}" add .
    git -C "${fixture_root}" commit --quiet --message baseline
    base_commit="$(git -C "${fixture_root}" rev-parse HEAD)"

    printf 'README-only update\n' >"${fixture_root}/examples/quickstart/README.md"
    git -C "${fixture_root}" add examples/quickstart/README.md
    git -C "${fixture_root}" commit --quiet --message readme
    readme_commit="$(git -C "${fixture_root}" rev-parse HEAD)"
    if ! git -C "${fixture_root}" diff --quiet "${base_commit}" "${readme_commit}" -- \
        src composer.json examples/quickstart resources migrations \
        ':(exclude)examples/quickstart/README.md'; then
        fail 'README-only release-runtime fixture must be accepted'
    fi

    printf 'runtime drift\n' >"${fixture_root}/src/runtime.php"
    git -C "${fixture_root}" add src/runtime.php
    git -C "${fixture_root}" commit --quiet --message runtime
    runtime_commit="$(git -C "${fixture_root}" rev-parse HEAD)"
    if git -C "${fixture_root}" diff --quiet "${readme_commit}" "${runtime_commit}" -- \
        src composer.json examples/quickstart resources migrations \
        ':(exclude)examples/quickstart/README.md'; then
        fail 'runtime Source drift fixture must be rejected'
    fi
)

assert_release_runtime_diff_contract

assert_generator_resource_inventory() {
    local file='.github/workflows/publish-skeleton.yml'

    contains "${file}" 'test -d resources/stubs'
    contains "${file}" 'invalid_stub_entries='
    contains "${file}" 'filesystem_stubs='
    contains "${file}" "find resources/stubs -mindepth 1 -maxdepth 1 -type f -name '*.stub'"
    contains "${file}" "-printf 'resources/stubs/%P\\n'"
    contains "${file}" "git ls-files -- 'resources/stubs/*.stub' | sort"
    contains "${file}" 'test -z "${invalid_stub_entries}"'
    contains "${file}" 'test -n "${filesystem_stubs}"'
    contains "${file}" 'test -n "${tracked_stubs}"'
    contains "${file}" 'test "${filesystem_stubs}" = "${tracked_stubs}"'
    contains "${file}" "! find examples/quickstart -type f -path '*/stubs/*' -print -quit | grep ."
    absent "${file}" 'expected_stubs='
    absent "${file}" 'migration.php.stub'
    absent "${file}" 'operation-outcome.php.stub'
    absent "${file}" 'operation-value.php.stub'
    absent "${file}" 'operation.php.stub'

    awk '
        index($0, "- name: Verify generator resource ownership") { ownership_line = NR }
        index($0, "test -d resources/stubs") { directory_line = NR }
        index($0, "invalid_stub_entries=") { invalid_assignment_line = NR }
        index($0, "test -z \"${invalid_stub_entries}\"") { invalid_test_line = NR }
        index($0, "filesystem_stubs=") { filesystem_assignment_line = NR }
        index($0, "tracked_stubs=") { tracked_assignment_line = NR }
        index($0, "test -n \"${filesystem_stubs}\"") { filesystem_nonempty_line = NR }
        index($0, "test -n \"${tracked_stubs}\"") { tracked_nonempty_line = NR }
        index($0, "test \"${filesystem_stubs}\" = \"${tracked_stubs}\"") { equality_line = NR }
        index($0, "! find examples/quickstart -type f -path '\''*/stubs/*'\''") { quickstart_line = NR }
        END {
            if (!ownership_line || !directory_line || !invalid_assignment_line || !invalid_test_line ||
                !filesystem_assignment_line || !tracked_assignment_line || !filesystem_nonempty_line ||
                !tracked_nonempty_line || !equality_line || !quickstart_line ||
                !(ownership_line < directory_line && directory_line < invalid_assignment_line &&
                invalid_assignment_line < invalid_test_line && invalid_test_line < filesystem_assignment_line &&
                filesystem_assignment_line < tracked_assignment_line && tracked_assignment_line < filesystem_nonempty_line &&
                filesystem_nonempty_line < tracked_nonempty_line && tracked_nonempty_line < equality_line &&
                equality_line < quickstart_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail 'Generator resource ownership must use a non-empty exact filesystem/Git root inventory'
}

assert_generator_resource_inventory

assert_generator_tag_lifecycle() {
    local file='tests/Consumer/framework-update-generators.sh'

    contains "${file}" "candidate_tag_ref='refs/tags/1.2.0'"
    contains "${file}" 'candidate_tag_type="$(git -C "${framework_repository}" cat-file -t "${candidate_tag_ref}" 2>/dev/null || true)"'
    contains "${file}" 'if test -z "${candidate_tag_type}"; then'
    contains "${file}" 'git -C "${framework_repository}" tag 1.2.0 "${current_commit}"'
    contains "${file}" 'candidate_source_commit="${current_commit}"'
    contains "${file}" 'test "${candidate_tag_type}" = '\''tag'\'''
    contains "${file}" 'published_candidate_commit="$(git -C "${framework_repository}" rev-parse "${candidate_tag_ref}^{commit}")"'
    contains "${file}" 'root_published_candidate_commit="$(git -C "${repository_root}" rev-parse "${candidate_tag_ref}^{commit}")"'
    contains "${file}" 'test "${published_candidate_commit}" = "${root_published_candidate_commit}"'
    contains "${file}" 'src composer.json examples/quickstart resources migrations;'
    contains "${file}" 'candidate_source_commit="${published_candidate_commit}"'
    contains "${file}" 'candidate_tag_ref}^{commit}")" = "${candidate_source_commit}"'

    awk '
        index($0, "candidate_tag_ref='\''refs/tags/1.2.0'\''") { ref_line = NR }
        index($0, "candidate_tag_type=") { type_line = NR }
        index($0, "if test -z \"${candidate_tag_type}\"; then") { absent_line = NR }
        index($0, "tag 1.2.0 \"${current_commit}\"") { create_line = NR }
        index($0, "candidate_source_commit=\"${current_commit}\"") { absent_source_line = NR }
        index($0, "test \"${candidate_tag_type}\" = '\''tag'\''") { annotated_line = NR }
        /^[[:space:]]+published_candidate_commit=/ { published_line = NR }
        /^[[:space:]]+root_published_candidate_commit=/ { root_line = NR }
        index($0, "test \"${published_candidate_commit}\" = \"${root_published_candidate_commit}\"") { equality_line = NR }
        index($0, "diff --quiet \"${published_candidate_commit}\" \"${current_commit}\"") { drift_line = NR }
        index($0, "candidate_source_commit=\"${published_candidate_commit}\"") { published_source_line = NR }
        index($0, "candidate_tag_ref}^{commit}\")\" = \"${candidate_source_commit}\"") { final_line = NR }
        END {
            if (!ref_line || !type_line || !absent_line || !create_line || !absent_source_line ||
                !annotated_line || !published_line || !root_line || !equality_line || !drift_line ||
                !published_source_line || !final_line ||
                !(ref_line < type_line && type_line < absent_line && absent_line < create_line &&
                create_line < absent_source_line && absent_source_line < annotated_line &&
                annotated_line < published_line && published_line < root_line &&
                root_line < equality_line && equality_line < drift_line &&
                drift_line < published_source_line && published_source_line < final_line)) {
                exit 1
            }
        }
    ' "${repository_root}/${file}" \
        || fail 'Generator Consumer must fail closed across absent and published annotated tag lanes'
}

assert_generator_tag_lifecycle

# Stable onboarding and its published CTA remain pinned to the immutable 1.2.0 lane.
contains README.md 'Latest Experimental StableはFramework／Skeleton `1.2.0`です。'
contains README.md 'composer create-project blackops/skeleton my-app 1.2.0'
contains README.md 'Framework／Skeleton `1.2.0`はannotated Tag、GitHub Release、Packagistへ公開済みです。'
contains docs/website/pages/index.astro 'Latest Experimental Stable 1.2.0'
contains docs/website/pages/index.astro 'composer create-project blackops/skeleton my-app 1.2.0'
contains docs/website/pages/index.astro 'Published Framework／Skeleton／Packagist／GitHub Release'
contains docs/guide/installation.md 'Latest Experimental Stable 1.2.0'
contains docs/guide/installation.md 'composer create-project --no-scripts blackops/skeleton my-app 1.2.0'
contains docs/guide/mvp-status.md 'Latest Experimental StableはFramework／Skeleton `1.2.0`です。'
contains docs/guide/mvp-status.md 'composer create-project blackops/skeleton my-app 1.2.0'
contains docs/guide/mvp-status.md 'diagnostics.storage_failed'
contains docs/guide/mvp-sample.md 'Experimental Stable 1.2.0'
contains docs/guide/mvp-sample.md 'composer create-project blackops/skeleton my-app 1.2.0'
contains docs/guide/mvp-sample.md 'ownership limitationはRemote smoke全体の失敗ではありません。'
contains docs/guide/observability.md 'Versionは公開済み`1.2.0`です。'
contains examples/quickstart/README.md '公開済みExperimental Stable `1.2.0`'
contains examples/quickstart/README.md 'composer create-project --no-scripts blackops/skeleton my-app 1.2.0'
contains docs/internal/installed-application-status.md 'Framework／Skeleton `1.2.0`はGitHub、Packagist、GitHub Releaseへ公開済み'
contains docs/internal/installed-application-status.md 'diagnostics.storage_failed'

contains CHANGELOG.md '## [Unreleased]'
test "$(grep -c '^## \[Unreleased\]$' "${repository_root}/CHANGELOG.md")" -eq 1 \
    || fail 'CHANGELOG.md must contain exactly one Unreleased section'
contains CHANGELOG.md '## [1.2.0] - 2026-08-15'
contains CHANGELOG.md '公開済みExperimental Release'
contains CHANGELOG.md 'root-owned `var/log/journal.jsonl`のbind-mount制約'
contains CHANGELOG.md '## [1.1.0] - 2026-07-16'
contains CHANGELOG.md 'Skeletonは`blackops/framework: ^1.1`を要求する。'
for section in '### Added' '### Changed' '### Removed' '### Fixed' '### Known Limitations'; do
    contains CHANGELOG.md "${section}"
done
for contract in \
    'Version20260808000000.php' \
    'Version20260808010000.php' \
    'CanonicalJournalReader' \
    'OutcomeReader' \
    '9つの`1.2.0` PostgreSQL Migration'; do
    contains CHANGELOG.md "${contract}"
done
contains UPGRADE.md '## 1.0.0から1.1.0'
contains UPGRADE.md '## 1.1.0から1.2.0'
contains UPGRADE.md '公開済みPackageの`1.2.0`をApplication Rootで明示的に要求します。'
for section in \
    '### 1. BackupとRollback境界を固定する' \
    '### 2. 公開PackageとComposerを準備する' \
    '### 5. Database MigrationをBackup後に順序実行する'; do
    contains UPGRADE.md "${section}"
done
contains UPGRADE.md '**Compatibility-first Lane**'
contains UPGRADE.md '**Opt-in 1.2.0-Skeleton Lane**'
contains UPGRADE.md "'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php'"
contains UPGRADE.md 'Application configuration key "app.build.frontend_manifest" must be a non-empty absolute path.'
contains UPGRADE.md '`1.2.0` HTTP／Worker Runtimeへ進むOpt-in Laneでは'
contains UPGRADE.md 'Storage protection provider is required for application bootstrap.'
contains UPGRADE.md "'services' => ["
contains UPGRADE.md '`app/ApplicationServiceProvider.php`へ次の完全なApplication-owned Provider'
contains UPGRADE.md 'namespace App;'
contains UPGRADE.md 'final readonly class ApplicationServiceProvider implements ServiceProvider'
contains UPGRADE.md 'app/Security/SampleStorageKeyProvider.php'
contains UPGRADE.md 'cp .env.example .env'
contains UPGRADE.md 'docker compose --profile worker up -d worker'
contains UPGRADE.md 'docker compose build app http'
contains UPGRADE.md 'docker compose run --rm app php blackops database:migrate'
contains UPGRADE.md 'Provider-present Worker-mode HTTP／Worker Positive'
absent UPGRADE.md 'Provider-presentのDatabase Migration／HTTP／Worker Positive lane'
contains UPGRADE.md 'set -euo pipefail'
contains UPGRADE.md 'cleanup() {'
contains UPGRADE.md 'rm -f .env'
contains UPGRADE.md 'docker compose down >/dev/null 2>&1 || true'
contains UPGRADE.md 'if test -n "${skeleton_temporary_root}"; then'
absent UPGRADE.md 'cleanup() { docker compose down; rm -f .env; }'
contains UPGRADE.md '同じDisposable Application RootのShellで順に実行します'
contains UPGRADE.md 'Provider-missing Classic HTTP safe 500／Worker CLI safe Negative'
contains docs/internal/installed-application-status.md '両lane共通のDatabase migration/setup（DDL guard evidence）'
contains UPGRADE.md 'Fresh Disposable laneでは、まずStable `1.1.0`の`database:status`が`applied: 0`／`pending: 2`'
contains UPGRADE.md 'Do not run Stable database:status after this migrate.'
contains UPGRADE.md 'Framework-only `1.2.0` update／strict validate'
contains UPGRADE.md '`1.2.0` status 2/9'
contains UPGRADE.md '`1.2.0` dry-run／migrate'
contains UPGRADE.md 'Runtime Consumerで検証済みのmerge'
contains UPGRADE.md 'blackops`、Caddyfile、ComposeはStable `1.1.0`のまま保持し、コピー／上書きしない。'
contains UPGRADE.md 'tests/Consumer/framework-update-runtime.sh'
contains UPGRADE.md 'blackops.schema_migrations'
contains UPGRADE.md 'Version20260712000000'
contains UPGRADE.md 'operations_payload_tombstone_check'
contains UPGRADE.md 'composer create-project --no-install --no-scripts blackops/skeleton "${skeleton_source}" 1.2.0'
contains UPGRADE.md "skeleton_temporary_root=''"
contains UPGRADE.md 'skeleton_temporary_root="$(mktemp -d)"'
contains UPGRADE.md 'if test -n "${skeleton_temporary_root}"; then'
absent UPGRADE.md "trap 'rm -rf \"\${skeleton_temporary_root}\"' EXIT"
absent UPGRADE.md 'trap - EXIT'
contains UPGRADE.md 'cmp "${skeleton_source}/bootstrap/app.php" bootstrap/app.php'
contains UPGRADE.md 'Stable 1.1.0と1.2.0の差分'
contains UPGRADE.md '-v ON_ERROR_STOP=1'
contains docs/website/tests/guide-code.test.mjs 'P22-003 upgrade order and runtime merge matrix stay executable'
contains UPGRADE.md 'exact body `{"message":"Welcome to BlackOps"}`'
contains UPGRADE.md 'docker compose ps --status running --services | grep -Fxq worker'
contains UPGRADE.md "grep -Eiq '^HTTP/[^[:space:]]+[[:space:]]+200([[:space:]]|$)'"
contains UPGRADE.md "grep -Eiq '^content-type:[[:space:]]*application/json([;[:space:]]|$)'"
contains UPGRADE.md 'for attempt in 1 2 3 4 5; do'
contains UPGRADE.md "curl -fsS -H 'X-Sample-Token: local-example' -D \"\${response_headers}\" -o \"\${response_body}\" http://127.0.0.1:8080/welcome"
contains docs/guide/installation.md "curl -i -H 'X-Sample-Token: local-example' http://127.0.0.1:8080/welcome"
contains docs/guide/installation.md 'storage_key="$(head -c 32 /dev/urandom | base64 -w 0)"'
contains docs/guide/installation.md 'set -euo pipefail'
contains docs/guide/installation.md 'test "${decoded_storage_key_length}" -eq 32'
contains docs/guide/installation.md 'sed -i "s|^BLACKOPS_STORAGE_KEY=.*|BLACKOPS_STORAGE_KEY=${storage_key}|" .env'
assert_guide_storage_key_contract docs/guide/installation.md
assert_guide_storage_key_contract docs/guide/mvp-sample.md
assert_installation_key_convergence
assert_mvp_key_convergence
contains docs/guide/first-operation.md '公開済みExperimental Stable `1.2.0`'
contains docs/guide/first-operation.md '#[Authorize]'
contains docs/guide/first-operation.md 'Sample Token Authentication'
contains docs/guide/first-operation.md 'Status Resource'
contains docs/guide/first-operation.md '#[Deferred]'
absent docs/guide/first-operation.md 'Stable `1.1.0`'
absent docs/guide/first-operation.md 'main Preview'
contains examples/quickstart/README.md 'docs/guide/mvp-sample.md#stable-120-authentication-and-deferred-journey'
absent examples/quickstart/README.md 'docs/guide/mvp-sample.md#stable-120-quickstart'
contains docs/website/scripts/check-site.mjs 'id="stable-120-authentication-and-deferred-journey"'
contains docs/website/scripts/check-site.mjs 'quickstartAnchorCount'
contains docs/website/scripts/check-site.mjs 'id="stable-120-quickstart"'
contains docs/website/tests/guide-code.test.mjs 'function assertQuickstartConvergence(source)'
contains docs/website/tests/guide-code.test.mjs "const heading = '### Stable 1.2.0 Authentication and Deferred Journey'"
contains docs/website/tests/guide-code.test.mjs 'function moveNoScriptsBlockAfterKey(source)'
contains docs/website/tests/guide-code.test.mjs 'assert.throws(() => assertQuickstartConvergence(moveNoScriptsBlockAfterKey(quickstart))'
contains docs/website/tests/guide-code.test.mjs 'Quickstart convergence guard rejects a drifted current heading'
contains docs/website/tests/guide-code.test.mjs 'assert.throws(() => assertQuickstartConvergence(driftedHeading)'
contains docs/website/tests/guide-code.test.mjs 'assert.throws(() => assertQuickstartReadmeFragment(driftedTarget)'
contains docs/guide/runtime-bootstrap.md 'Stable `1.1.0`の`/welcome`は`#[Authorize]`を持たない認可匿名'
contains docs/guide/mvp-sample.md '`ShowWelcome`は`#[Authorize(SampleUserAuthorizationPolicy::class)]`で保護され'
contains docs/guide/mvp-sample.md 'Header省略はAnonymousとして`401`、不正値はOperation受付前の`401`'
contains docs/guide/mvp-sample.md '32-byte Base64のLocal Development Key'
contains docs/guide/mvp-sample.md 'set -euo pipefail'
contains docs/website/tests/guide-code.test.mjs 'authenticated-header'
absent UPGRADE.md "grep -Fiq '^HTTP/.* 200'"
absent UPGRADE.md "grep -Fiq '^content-type: application/json'"
absent UPGRADE.md 'sed -i "s/^BLACKOPS_STORAGE_KEY='
test "$(git -C "${repository_root}" show 1.1.0:examples/quickstart/.env.example | grep -c '^BLACKOPS_STORAGE_KEY=')" -eq 0 \
    || fail 'Stable 1.1.0 unexpectedly contains a storage key environment line'
test "$(grep -c '^BLACKOPS_STORAGE_KEY=' "${repository_root}/examples/quickstart/.env.example")" -eq 1 \
    || fail 'Current quickstart must contain exactly one storage key environment line'
absent UPGRADE.md 'Consumer後は同じApplication-owned SourceをComposeへ手動で配置'
absent UPGRADE.md 'cp ../blackops/examples/quickstart/'
contains docs/internal/installed-application-status.md "'frontend_manifest' => dirname(__DIR__) . '/var/build/frontend.php'"
contains docs/internal/installed-application-status.md 'P22-003 fixed-SHA Full Gate'
for contract in \
    'blackops/framework:^1.2' \
    'Version20260808000000.php' \
    'tests/Consumer/framework-update-generators.sh'; do
    contains UPGRADE.md "${contract}"
done
contains tests/Consumer/framework-update-generators.sh "cat-file -t refs/tags/1.1.0"
contains tests/Consumer/framework-update-generators.sh 'blackops/framework:1.2.0'
contains tests/Consumer/framework-update-generators.sh 'tag 1.2.0'
contains tests/Consumer/framework-update-generators.sh 'blackops build:compile'
contains tests/Consumer/framework-update-generators.sh 'blackops operation:list'
test -x "${repository_root}/tests/Consumer/framework-update-runtime.sh" \
    || fail 'Runtime consumer must be executable'
contains tests/Consumer/framework-update-runtime.sh "cat-file -t refs/tags/1.1.0"
contains tests/Consumer/framework-update-runtime.sh 'migrations=11'
contains tests/Consumer/framework-update-runtime.sh 'Migration status mismatch at %s:'
contains tests/Consumer/framework-update-runtime.sh 'assert_migration_status stable-before-migrate 0 2'
contains tests/Consumer/framework-update-runtime.sh 'Stable post-migrate status diagnostic changed'
contains tests/Consumer/framework-update-runtime.sh 'blackops.schema_migrations'
contains tests/Consumer/framework-update-runtime.sh 'Version20260712000000'
contains tests/Consumer/framework-update-runtime.sh 'operations_payload_tombstone_check'
contains tests/Consumer/framework-update-runtime.sh 'assert_migration_status candidate-before-migrate 2 9'
contains tests/Consumer/framework-update-runtime.sh 'assert_migration_status candidate-after-migrate 11 0'
contains tests/Consumer/framework-update-runtime.sh 'config merge failed: expected unique HTTP/frontend manifest markers'
contains tests/Consumer/framework-update-runtime.sh 'final root closure was not uniquely located'
contains tests/Consumer/framework-update-runtime.sh 'file_put_contents($path, $source)'
contains tests/Consumer/framework-update-runtime.sh "-H 'X-Sample-Token: local-example'"
contains tests/Consumer/framework-update-runtime.sh '$quote = chr(39);'
contains tests/Consumer/framework-update-runtime.sh 'http_port=$((18080 + RANDOM % 1000))'
contains tests/Consumer/framework-update-runtime.sh 'provider-present=http-worker'
contains tests/Consumer/framework-update-runtime.sh 'classic_http_port=$((http_port + 1))'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing=classic-http-worker-safe-negative'
contains tests/Consumer/framework-update-runtime.sh '--profile classic-mode up -d http-classic'
contains tests/Consumer/framework-update-runtime.sh 'classic-http.log'
contains tests/Consumer/framework-update-runtime.sh 'fail_stage()'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing-classic-http-readiness'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing-redaction'
contains tests/Consumer/framework-update-runtime.sh 'provider-missing-services-removal'
contains tests/Consumer/framework-update-runtime.sh "candidate_tag_ref='refs/tags/1.2.0'"
contains tests/Consumer/framework-update-runtime.sh 'candidate_tag_type="$(git -C "${framework_repository}" cat-file -t "${candidate_tag_ref}" 2>/dev/null || true)"'
contains tests/Consumer/framework-update-runtime.sh "local runtime candidate' 1.2.0"
contains tests/Consumer/framework-update-runtime.sh 'test "${candidate_tag_type}" = tag'
contains tests/Consumer/framework-update-runtime.sh 'published_candidate_commit="$(git -C "${framework_repository}" rev-parse "${candidate_tag_ref}^{commit}")"'
contains tests/Consumer/framework-update-runtime.sh 'root_published_candidate_commit="$(git -C "${repository_root}" rev-parse "${candidate_tag_ref}^{commit}")"'
contains tests/Consumer/framework-update-runtime.sh 'test "${published_candidate_commit}" = "${root_published_candidate_commit}"'
contains tests/Consumer/framework-update-runtime.sh 'diff --quiet "${published_candidate_commit}" "${candidate_commit}" --'
contains tests/Consumer/framework-update-runtime.sh "':(exclude)examples/quickstart/README.md'"
contains tests/Consumer/framework-update-runtime.sh 'Published 1.2.0 release-runtime Source drifted from current HEAD.'
contains tests/Consumer/framework-update-runtime.sh 'candidate_source_commit="${published_candidate_commit}"'
contains tests/Consumer/framework-update-runtime.sh 'candidate_source_commit="${candidate_commit}"'
contains tests/Consumer/framework-update-runtime.sh 'verify_runtime_bootstrap()'
contains tests/Consumer/framework-update-runtime.sh 'bootstrap/app.php'
contains tests/Consumer/framework-update-runtime.sh 'public/index.php'
contains tests/Consumer/framework-update-runtime.sh 'public/worker.php'
contains tests/Consumer/framework-update-runtime.sh 'runtime-bootstrap-drift'
contains tests/Consumer/framework-update-runtime.sh 'Storage protection provider is required for application bootstrap.'
contains tests/Consumer/framework-update-runtime.sh 'BLACKOPS_STORAGE_KEY|BOPD|SQLSTATE|PDO'
contains tests/Consumer/framework-update-runtime.sh 'rm -f "${consumer_root}/.env"'
contains tests/Consumer/framework-update-runtime.sh 'down --volumes --remove-orphans --rmi local'
contains tests/Consumer/framework-update-runtime.sh 'display_errors=0 blackops worker:run'
contains tests/Consumer/framework-update-runtime.sh 'trap '\''exit 130'\'' INT TERM'
contains .github/workflows/ci.yml 'framework-update-runtime:'
contains .github/workflows/ci.yml 'bash tests/Consumer/framework-update-runtime.sh'
contains .github/workflows/ci.yml 'fetch-depth: 0'
contains .github/workflows/ci.yml 'HOST_UID=%s\n'

# Stale pre-publication claims must not return to public documentation.
for file in README.md CHANGELOG.md UPGRADE.md examples/quickstart/README.md \
    docs/guide/installation.md docs/guide/first-operation.md docs/guide/mvp-status.md docs/guide/mvp-sample.md \
    docs/guide/observability.md docs/internal/installed-application-status.md \
    docs/website/pages/index.astro; do
    absent "${file}" '未公開の`1.2.0` Release Candidate'
    absent "${file}" 'Repository `main`は未公開の`1.2.0` Release Candidateです。'
    absent "${file}" 'Repository `main`の`1.2.0` Preview Application'
    absent "${file}" 'Repository `main` candidateの`1.2.0`'
    absent "${file}" '## 1.1.0から1.2.0 Preview'
    absent "${file}" 'Opt-in Candidate-Skeleton Lane'
done
absent UPGRADE.md 'Candidate SourceとComposerを準備する'
absent UPGRADE.md 'Repository `main`の未公開`1.2.0` candidate'
absent CHANGELOG.md '9つのCandidate PostgreSQL Migration'

printf 'Version baseline guard passed: published=1.2.0 historical=1.1.0\n'
