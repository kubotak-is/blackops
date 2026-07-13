# Development Setup

BlackOpsの実装環境はWSL2 Ubuntu内へ構築する。CommandはRepository Rootで実行する。

```text
/home/kubotak/projects/blackops
```

## Codex Implementation Delegation

Production Codeの実装は、Orchestrator CodexがTask Packetを作成し、Codex GPT-5.6 Luna High workerへ依頼する。

実装依頼で使用するModelは次とする。

```text
Codex GPT-5.6 Luna High
```

Task Packet、Report、STATEはRepository内へ保存する。Credential、Token、Secret、外部ServiceのAPI KeyはRepository内のFile、Task Packet、Reportへ記載しない。

GPT-5.6 Luna High workerは、Task Packetで許可されたFileだけを変更し、必須Command結果をReportへ記録し、Review前にCommitしない。実行環境でModel／Profileを指定できない場合は、別Modelへ黙ってFallbackせずOrchestratorへ返す。

## Docker Compose

PHPと開発ToolはWSL2 Hostへ直接導入しない。Docker Desktopも使用せず、WSL2 Ubuntu内へDocker EngineとCompose Pluginを導入する。

Docker EngineとCompose Pluginが未導入の場合は、Docker公式APT Repositoryから次のPackageを導入する。

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

sudo tee /etc/apt/sources.list.d/docker.sources >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
Components: stable
Architectures: $(dpkg --print-architecture)
Signed-By: /etc/apt/keyrings/docker.asc
EOF

sudo apt-get update
sudo apt-get install -y \
  docker-ce \
  docker-ce-cli \
  containerd.io \
  docker-buildx-plugin \
  docker-compose-plugin
```

Docker Daemonを起動し、現在のUserから利用できるようにする。

```bash
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Group変更は現在のShellへ即時反映されないため、WSL2 Shellへ入り直す。再起動後に確認する。

```bash
docker version
docker compose version
docker run --rm hello-world
```

Phase 0で次のServiceを `compose.yaml` に定義する。

```text
app         PHP 8.5、Composer、Mago、PHPUnit、Deptrac
postgres    PostgreSQL 18
```

Application ContainerはRepository RootをWorking Directoryとしてマウントし、HostへのPHP／Composer導入なしで全Commandを実行できる。

### 初回Buildと依存関係の導入

```bash
docker compose config
docker compose build app
docker compose run --rm app composer install
```

### Toolchain Smoke Test

```bash
docker compose up -d postgres
docker compose ps
docker compose run --rm app php --version
docker compose run --rm app composer --version
docker compose run --rm app composer validate --strict
docker compose run --rm app mago lint
docker compose run --rm app mago analyze
docker compose run --rm app vendor/bin/phpunit
docker compose run --rm app vendor/bin/deptrac
```

### 終了

```bash
docker compose down
```

## Resume

環境構築を中断した場合は `develop/STATE.md` の `Known Blockers` と `Required Next Action` から再開する。

## Skeleton publication validation

Framework Release TagからSkeleton DistributionへPublishする前に、Local Dry Runを実行する。

```bash
bash tests/Consumer/skeleton-publication.sh 1.0.0 HEAD
```

このCommandはCommitted QuickstartのSplit、Version／Constraint、配布Root、Composer Metadata、Generated State不在、Temporary State Cleanupを検証する。Remote PushやCredential参照は行わない。Release Workflow、Deploy Key、Remote Divergence、Packagist連携の運用境界は [Skeleton Publication](skeleton-publication.md) を参照する。
