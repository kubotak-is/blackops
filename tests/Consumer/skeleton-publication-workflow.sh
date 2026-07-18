#!/usr/bin/env bash

set -euo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
workflow_file="${repository_root}/.github/workflows/publish-skeleton.yml"
temporary_root="$(mktemp -d)"
workflow_run="${temporary_root}/publish-step.sh"
source_clone="${temporary_root}/source"

fail() {
    printf 'Skeleton publication workflow regression failed: %s\n' "$1" >&2
    exit 1
}

cleanup() {
    rm -rf "${temporary_root}"
}
trap cleanup EXIT

awk '
    /^      - name: Publish split commit and matching annotated tag$/ {
        publication_step = 1
        next
    }
    publication_step && /^      - name:/ {
        exit
    }
    publication_step && /^        run: \|$/ {
        run_block = 1
        next
    }
    run_block {
        sub(/^          /, "")
        print
    }
' "${workflow_file}" > "${workflow_run}"

test -s "${workflow_run}" || fail 'publication run block was not found'
bash -n "${workflow_run}"

git clone --quiet --no-hardlinks --no-tags "${repository_root}" "${source_clone}"
git -C "${source_clone}" rm -r --quiet examples/quickstart
mkdir -p "${source_clone}/examples/quickstart"
cp -a "${repository_root}/examples/quickstart/." "${source_clone}/examples/quickstart/"
git -C "${source_clone}" add examples/quickstart
GIT_AUTHOR_NAME='BlackOps Workflow Regression' \
    GIT_AUTHOR_EMAIL='workflow-regression@blackops.dev' \
    GIT_COMMITTER_NAME='BlackOps Workflow Regression' \
    GIT_COMMITTER_EMAIL='workflow-regression@blackops.dev' \
    git -C "${source_clone}" commit --quiet --message 'Test current skeleton working tree'
source_commit="$(git -C "${source_clone}" rev-parse HEAD)"
split_commit="$(git -C "${source_clone}" subtree split \
    --prefix=examples/quickstart "${source_commit}" 2> "${temporary_root}/split.log")"
for required_path in \
    app/Feature/Diagnostics/TriggerFailure/TriggerFailure.php \
    app/Feature/Diagnostics/TriggerFailure/TriggerFailureValue.php \
    app/Feature/Diagnostics/TriggerFailure/FailureTriggered.php \
    config/diagnostics.php config/logging.php README.md; do
    git -C "${source_clone}" cat-file -e "${split_commit}:${required_path}" \
        || fail "split commit is missing ${required_path}"
done

create_remote() {
    git init --quiet --bare "$1"
}

run_publication() {
    local remote="$1"
    local version="$2"
    local manual_recovery="$3"
    local run_root

    run_root="$(mktemp -d "${temporary_root}/run.XXXXXX")"
    (
        cd "${source_clone}"
        RUNNER_TEMP="${run_root}" \
            GITHUB_WORKSPACE="${source_clone}" \
            SKELETON_REMOTE="${remote}" \
            RELEASE_VERSION="${version}" \
            MANUAL_RECOVERY="${manual_recovery}" \
            bash "${workflow_run}"
    )
}

push_lightweight_tag() {
    local remote="$1"
    local version="$2"
    local commit="$3"

    git -C "${source_clone}" push --quiet "${remote}" \
        "${commit}:refs/tags/${version}"
}

new_remote="${temporary_root}/new.git"
create_remote "${new_remote}"
run_publication "${new_remote}" 1.1.0 false > "${temporary_root}/new.log" 2>&1

test "$(git --git-dir="${new_remote}" cat-file -t refs/tags/1.1.0)" = 'tag' \
    || fail 'new release did not publish an annotated tag object'
test "$(git --git-dir="${new_remote}" for-each-ref \
    --format='%(contents:subject)' refs/tags/1.1.0)" = 'BlackOps Skeleton 1.1.0' \
    || fail 'new release tag message does not match'
test "$(git --git-dir="${new_remote}" rev-parse 'refs/tags/1.1.0^{commit}')" = "${split_commit}" \
    || fail 'new release peeled commit does not match the split commit'
git --git-dir="${new_remote}" cat-file -p refs/tags/1.1.0 \
    | grep -Fq 'tagger BlackOps Release Automation <release@blackops.dev> ' \
    || fail 'new release tagger identity does not match'

idempotent_tag_object="$(git --git-dir="${new_remote}" rev-parse refs/tags/1.1.0)"
run_publication "${new_remote}" 1.1.0 false > "${temporary_root}/idempotent.log" 2>&1
test "$(git --git-dir="${new_remote}" rev-parse refs/tags/1.1.0)" = "${idempotent_tag_object}" \
    || fail 'idempotent publication replaced the existing annotated tag'

divergent_remote="${temporary_root}/divergent.git"
create_remote "${divergent_remote}"
GIT_COMMITTER_NAME='BlackOps Release Automation' \
    GIT_COMMITTER_EMAIL='release@blackops.dev' \
    git -C "${source_clone}" tag --annotate 1.2.0 "${source_commit}" \
        --message 'BlackOps Skeleton 1.2.0'
git -C "${source_clone}" push --quiet "${divergent_remote}" \
    refs/tags/1.2.0:refs/tags/1.2.0
divergent_tag_object="$(git --git-dir="${divergent_remote}" rev-parse refs/tags/1.2.0)"
if run_publication "${divergent_remote}" 1.2.0 false > "${temporary_root}/divergent.log" 2>&1; then
    fail 'publication accepted an annotated tag that peels to another commit'
fi
grep -Fq 'Existing annotated skeleton release tag peels to another commit.' \
    "${temporary_root}/divergent.log" \
    || fail 'divergent annotated tag did not reach the expected rejection boundary'
test "$(git --git-dir="${divergent_remote}" rev-parse refs/tags/1.2.0)" = "${divergent_tag_object}" \
    || fail 'divergent annotated tag changed after rejection'

lightweight_remote="${temporary_root}/lightweight.git"
create_remote "${lightweight_remote}"
push_lightweight_tag "${lightweight_remote}" 1.3.0 "${split_commit}"
if run_publication "${lightweight_remote}" 1.3.0 true > "${temporary_root}/lightweight.log" 2>&1; then
    fail 'manual publication accepted a new lightweight release tag'
fi
grep -Fq 'Existing lightweight skeleton release tag violates the release contract.' \
    "${temporary_root}/lightweight.log" \
    || fail 'new lightweight tag did not reach the expected rejection boundary'
test "$(git --git-dir="${lightweight_remote}" rev-parse refs/tags/1.3.0)" = "${split_commit}" \
    || fail 'new lightweight tag changed after rejection'

legacy_remote="${temporary_root}/legacy.git"
create_remote "${legacy_remote}"
push_lightweight_tag "${legacy_remote}" 1.0.0 "${split_commit}"
legacy_tag_object="$(git --git-dir="${legacy_remote}" rev-parse refs/tags/1.0.0)"
run_publication "${legacy_remote}" 1.0.0 true > "${temporary_root}/legacy.log" 2>&1
grep -Fq 'Keeping immutable legacy Skeleton 1.0.0 lightweight tag during manual recovery.' \
    "${temporary_root}/legacy.log" \
    || fail 'legacy manual recovery did not reach the immutable compatibility boundary'
test "$(git --git-dir="${legacy_remote}" rev-parse refs/heads/main)" = "${split_commit}" \
    || fail 'legacy manual recovery did not publish the matching main commit'
test "$(git --git-dir="${legacy_remote}" rev-parse refs/tags/1.0.0)" = "${legacy_tag_object}" \
    || fail 'legacy manual recovery replaced the lightweight tag'
test "$(git --git-dir="${legacy_remote}" cat-file -t refs/tags/1.0.0)" = 'commit' \
    || fail 'legacy manual recovery changed the lightweight tag object type'
test -z "$(git ls-remote "${legacy_remote}" 'refs/tags/1.0.0^{}')" \
    || fail 'legacy manual recovery created a peeled ref for the lightweight tag'

legacy_trigger_remote="${temporary_root}/legacy-trigger.git"
create_remote "${legacy_trigger_remote}"
push_lightweight_tag "${legacy_trigger_remote}" 1.0.0 "${split_commit}"
if run_publication "${legacy_trigger_remote}" 1.0.0 false > "${temporary_root}/legacy-trigger.log" 2>&1; then
    fail 'tag-triggered publication accepted the legacy lightweight tag exception'
fi
grep -Fq 'Existing lightweight skeleton release tag violates the release contract.' \
    "${temporary_root}/legacy-trigger.log" \
    || fail 'legacy tag trigger did not reach the expected rejection boundary'
test "$(git --git-dir="${legacy_trigger_remote}" rev-parse refs/tags/1.0.0)" = "${split_commit}" \
    || fail 'legacy tag-trigger rejection changed the lightweight tag'

legacy_divergent_remote="${temporary_root}/legacy-divergent.git"
create_remote "${legacy_divergent_remote}"
push_lightweight_tag "${legacy_divergent_remote}" 1.0.0 "${source_commit}"
if run_publication "${legacy_divergent_remote}" 1.0.0 true > "${temporary_root}/legacy-divergent.log" 2>&1; then
    fail 'legacy manual recovery accepted a different direct commit'
fi
grep -Fq 'Existing lightweight skeleton release tag violates the release contract.' \
    "${temporary_root}/legacy-divergent.log" \
    || fail 'divergent legacy tag did not reach the expected rejection boundary'
test "$(git --git-dir="${legacy_divergent_remote}" rev-parse refs/tags/1.0.0)" = "${source_commit}" \
    || fail 'divergent legacy tag changed after rejection'

cleanup
trap - EXIT
test ! -e "${temporary_root}" || fail 'temporary workflow regression tree was not removed'

printf 'Skeleton publication workflow regression passed: split=%s\n' "${split_commit}"
