# Package Architecture

## Composer Package

MVPは `blackops/framework` 単一Composer Packageとして実装・公開する。

利用者は一つのPackageを導入することで、Core、HTTP、Journal、Deferred Execution、Worker、Logging、Consoleを含むMVP機能を利用できる。

## Componentの扱い

MVPではComponentごとのComposer Package分割および独立Releaseを行わない。

責務の境界は単一Package内のNamespaceとソース配置によって表現する。具体的な境界と依存方向は別のDecisionで定める。

個別Packageへの分割は、実利用からComponentの独立性と個別導入の需要が確認された後に再検討する。
