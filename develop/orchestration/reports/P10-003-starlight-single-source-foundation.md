# P10-003 Starlight Single-source Foundation Report

## Summary

Node.js 24 LTS／pnpm 11をExact Pinしたmise Toolchainと、Astro Starlightによる利用者向けDocumentation Website Foundationを構築した。公開本文は`docs/guide/`だけをSource of Truthとし、Starlight ContentとManifestを未追跡領域へ決定的に生成する。

GeneratorはH1 Title、Slug重複、内部Link、Source外参照、公開対象外Path、Repository Absolute Path、Symbolic Linkを検証する。Source Markdownは変更せず、生成ContentへTitle Frontmatterを付与し、Guide間Linkを生成先に合わせて書き換える。通常CIへCredential不要のTest／Check／Build／公開境界Guardも追加した。

## Toolchain and Lock Evidence

- `mise.toml`はNode.js `24.18.0`と`npm:pnpm` `11.12.0`をExact Pinする。
- `docs/website/package.json`の`packageManager`、`engines`、CIのVersion Guardは同じVersionを要求する。
- pnpmのmise標準BackendはLinux Asset名を解決できなかったため、mise Registryが提供する`npm:pnpm` Backendを採用した。`mise exec -- pnpm --version`は`11.12.0`を返す。
- `pnpm-lock.yaml`はLockfile Version 9で、Astro `7.0.7`、Starlight `0.41.3`、Astro Check `0.9.9`、TypeScript `6.0.3`を固定する。
- pnpm 11のDependency Build Allowlistは`pnpm-workspace.yaml`で`esbuild`だけを許可する。
- `mise install`とFrozen Lockfile Installは最終状態で成功した。

## Content Generation Evidence

- Source Rootは`docs/guide/`に固定し、9 MarkdownをStarlight Contentへ生成する。
- H1をTitleとして抽出し、Source本文のH1を生成Contentから除去して決定的なFrontmatterを付与する。
- ManifestはSource／Generated Path、Slug、Title、SHA-256だけを保持し、TimestampやAbsolute Pathを含まない。
- `content:check`は独立した一時Directoryへ2回生成し、Manifestと全生成Fileのbyte-for-byte一致を検証する。
- 生成前後のSource Snapshotを比較し、Generatorが`docs/guide/`を変更しないことを検証する。
- Node Test 9件が正常生成、決定性、Title不在、重複Slug、壊れたLink、Source外Link、公開対象外Path、Absolute Path、Frontmatter、Symbolic Linkの境界を検証する。
- Static BuildはGuide 9 PageとFallback 404の合計10 HTMLを生成し、Pagefind IndexとSitemapを構築した。Artifact Guardも成功した。

## Changed Files

- `mise.toml`
- `.gitignore`
- `.github/workflows/ci.yml`
- `docs/guide/mvp-status.md`
- `docs/guide/runtime-bootstrap.md`
- `docs/website/README.md`
- `docs/website/package.json`
- `docs/website/pnpm-lock.yaml`
- `docs/website/pnpm-workspace.yaml`
- `docs/website/astro.config.mjs`
- `docs/website/tsconfig.json`
- `docs/website/src/content.config.ts`
- `docs/website/scripts/website-paths.mjs`
- `docs/website/scripts/content-pipeline.mjs`
- `docs/website/scripts/generate-content.mjs`
- `docs/website/scripts/check-content.mjs`
- `docs/website/scripts/check-artifact.mjs`
- `docs/website/tests/content-pipeline.test.mjs`
- `develop/orchestration/tasks/P10-003-starlight-single-source-foundation.md`
- `develop/orchestration/reports/P10-003-starlight-single-source-foundation.md`
- `develop/STATE.md`

## Decisions and Assumptions

- Website `site`は確定済み初期Host `https://blackops-docs.pages.dev`とし、Sitemapへ反映した。
- 公開本文を二重管理しないため、`docs/website/src/content/docs/`、`.generated/`、`dist/`は生成物としてGit管理外にした。
- 現行Guideの変更はTask Packetの制約どおり、Internal Link 2件の除去とDevelopment Evidence 1件の利用者向け表現への置換だけに限定した。
- Source MarkdownのFrontmatterは許可せず、既存Repository MarkdownのH1をTitleの唯一の入力とした。
- Starlightの最終Landing／Navigation／404 ContentはP10-004のInformation Architecture Scopeとして扱う。

## Commands and Results

```text
mise install
Result: Success. node 24.18.0 and npm:pnpm 11.12.0 are installed. Sandbox内の初回実行はmise runtime symlinkのRead-only Errorとなったため、許可済み実行で再確認した。

mise exec -- node --version
mise exec -- pnpm --version
mise current
Result: v24.18.0; 11.12.0; node 24.18.0 / npm:pnpm 11.12.0.

mise exec -- pnpm --dir docs/website install --frozen-lockfile
Result: Already up to date. Completed with pnpm 11.12.0.

mise exec -- pnpm --dir docs/website run test
Result: 9 tests / 9 passed / 0 failed.

mise exec -- pnpm --dir docs/website run check
Result: Content validation and determinism checks passed. Astro check: 9 files, 0 errors, 0 warnings, 0 hints.

mise exec -- pnpm --dir docs/website run build
Result: 10 pages built; Pagefind index and sitemap created; Static artifact boundary check passed.

mise exec -- pnpm --dir docs/website run content:generate
Result: Generated Starlight content from docs/guide.

git diff --exit-code -- docs/website/src/content/docs docs/website/.generated
Result: No tracked diff.

! git ls-files docs/website/src/content/docs docs/website/.generated docs/website/dist | grep .
Result: No generated or build output is tracked; negated command exited 0.

! rg -n 'docs/internal|develop/' docs/website/dist docs/website/.generated
Result: No forbidden public reference; negated command exited 0.

python3 -c 'import pathlib, yaml; yaml.safe_load(pathlib.Path(".github/workflows/ci.yml").read_text())'
Result: Parsed successfully.

docker compose run --rm app mago format --check src tests
Result: INFO All files are already formatted. Sandbox内の初回実行はDocker API Permission Errorとなったため、許可済み実行で再確認した。

! rg -n 'Spec(ification)?[[:space:]]*[0-9]+|D[0-9]{3}|P[0-9]+-[0-9]+|TODO\.md:[0-9]+' src tests --glob '*.php'
Result: No management ID references; negated command exited 0.

git diff --check
Result: No output.
```

## Orchestrator Review

- Worker差分がFiles Allowed to Change内に限定され、Guide変更が公開境界3件だけであることを確認した。Orchestrator所有のTask Packetは、検出した公開境界参照を解消できるようScopeと許可Fileを開始後に補正した。
- Frozen Install、Node Test、Content Check、Astro Check、Static Build、Artifact Guard、Git追跡Guard、公開禁止Path Guard、CI YAML、PHP Format、管理番号Guard、`git diff --check`を再実行し、すべて成功した。
- mise-action公式READMEの現行例とReleaseを確認し、通常CIのAction Majorを`jdx/mise-action@v4`へ更新した。Node／pnpmのExact Version Guardは維持した。
- GeneratorのSource固定、決定的Manifest、Source不変、Link書換、公開対象外Path／Absolute Path／Symbolic Link拒否を確認し、Acceptance Criteriaを満たすと判断した。

## Acceptance Criteria

- Exact mise Toolchain Install: Satisfied
- Frozen Lockfile Install: Satisfied
- Guide-only Content and Manifest Generation: Satisfied
- Byte-for-byte Determinism: Satisfied
- Title／Slug／Internal Link／Source Boundary Guards: Satisfied
- Generated Content and Dist Untracked: Satisfied
- Astro Type Check and Static Build: Satisfied
- Normal and Guard Node Tests: Satisfied
- Credential-free Website CI Job: Satisfied

## Remaining Issues

P10-003のAcceptance Criteriaに残作業やBlockerはなく、Orchestrator Reviewで受け入れた。

Starlight Buildは専用`404` ContentがないためFallback 404生成時に`Entry docs → 404 was not found.`と表示するが、Buildは成功して`404.html`を生成する。最終Landing／Navigation／404 Contentの要否はP10-004で判断する。

## Suggested Next Action

P10-004 User Documentation Information Architectureへ進む。
