# Landing Reel

トップページのHero映像の生成元です。`reel.html`が時刻`t`から各フレームを決定的に描画し、`render.sh`がPlaywrightのChromiumで30fps・1920×1080・36秒のフレームを取得して、`../public/assets/reel/`へMP4（H.264）、WebM（VP9）、Posterを書き出します。

## 構成

| 時間 | 場面 | 内容 |
| --- | --- | --- |
| 0.0–3.6s | Title | BlackOps／The PHP Framework |
| 3.6–8.6s | 01 One Operation | HTTP・Console・Scheduleから一つのOperationへ |
| 8.6–14.2s | 02 Inline or Deferred | `#[Deferred]`でHTTP 202とWorker実行へ切り替わる |
| 14.2–20.2s | 03 Lifecycle Journal | 同じOperation IDで受付・Retry・完了を追跡する |
| 20.2–24.8s | 04 Typed Boundaries | OperationValue、handle()、Outcomeと宣言的な属性 |
| 24.8–28.2s | 05 Headless | Generated ClientをFrontendから呼び出す |
| 28.2–34.0s | 06 BlackOps CLI | make:operationからdatabase:migrate、build:compile、worker:run、operation:inspectまで |
| 34.0–36.0s | Outro | `composer create-project`。Landingでは一度だけ再生し、この場面で止まる |

場面の開始時刻は`../components/LandingReel.astro`のチャプターと一致させます。時刻を変えた場合は両方を更新してください。映像内のJournal、Operation ID、件数、時間は`Illustrative example`としての説明用で、実際の実行結果を表しません。Version番号は映像へ焼き込まず、Release更新で映像を作り直さなくて済むようにしています。

## プレビュー

`reel.html`をHTTPで配信してBrowserで開くと、実時間で再生されます。`window.renderAt(秒)`で任意の時刻を描画できます。

## 書き出し

必要なもの:

- Repository RootのNode `24.18.0`、pnpm `11.12.0`
- `docs/website`で`pnpm install --frozen-lockfile`して解決される、Pinned `playwright-core` `1.63.0`
- Docker image `mcr.microsoft.com/playwright:v1.63.0-noble`（Capture Browser）
- Version `6`以上で`libx264`と`libvpx-vp9`を含む`ffmpeg`

```bash
pnpm --dir docs/website install --frozen-lockfile
FFMPEG=/path/to/ffmpeg \
  docs/website/reel/render.sh
```

`render.sh`はRepository-owned `playwright-core`を既定解決し、Capture用の一時SourceをRead-onlyでLoopback HTTP Serverへ渡します。Serverは`reel.html`と3種類のFontだけをGET／HEADで配信し、Path Traversal、外部Path、書込みを拒否します。Frame出力は一時Directoryへ置き、終了時に既定では削除します。再確認のために残す場合は`REEL_WORK_DIR=/tmp/blackops-reel-work docs/website/reel/render.sh`を使います。

Scriptは`ffmpeg`のVersionと`libx264`／`libvpx-vp9` Encoderを実行前に検査し、ログへPlaywright package／Docker image、Font SHA-256、36秒・30fps・1920×1080を出力します。完了時には3つの生成AssetのSHA-256も出力するため、RootのRender Receiptへ転記します。

日本語の見出しにはサイトのSubset Fontに無い文字が含まれるため、書き出し時だけ`docs/website/fonts/README.md`と同じRevisionのNoto Sans JP全体を一時Sourceへ取得し、SHA-256を検証します。取得したFont、Frame、StillはCommitしません。
