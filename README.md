# MealHub

Laravel 12 應用程式，提供餐廳與訂位 API。專案附有 Docker 開發環境與 Lightsail 部署設定，並把應用程式 log 直接送到 `stderr`，方便容器平台（Lightsail、CloudWatch、Docker logs 等）集中收集。

## 本機開發

1. 建立 `.env`：`cp src/mealhub/.env.example src/mealhub/.env`，再依需求調整資料庫 / Redis / JWT 等設定（建議只維護 `src/mealhub/.env`，避免在 repo 根目錄放 runtime secrets）。Docker Compose 指令會自動載入此檔案中的 `DB_*`/`REDIS_*` 等變數，確保容器與應用程式使用同一組帳號密碼。
2. 啟動 docker-compose：
   ```bash
   make init        # 首次：up + install + migrate --seed
   make up          # 之後再次啟動
   make logs        # 觀察所有服務 log
   ```
3. API 透過 `http://localhost:8080`（Nginx），Mailpit `http://localhost:8025`，Grafana `http://localhost:3000`。

## Logging 策略

- `config/logging.php` 與 `.env.example` 將 `LOG_STACK` 預設為 `stderr`，讓一般 log 都寫入 `php://stderr`。
- 容器平台（Lightsail、Docker Desktop、CloudWatch）會自動收集 stdout/stderr，因此不再需要 `storage/logs` 永久寫入；若仍需檔案 log，可於 `.env` 改成 `LOG_STACK=single`。

## Lightsail 容器部署

1. 建立 Lightsail Container Service，記下 service name 與 AWS Region。
2. 將應用程式需要的環境變數（APP_KEY、DB/Redis/JWT 等）在 Lightsail 服務的 *Environment variables* 中設定。`LOG_CHANNEL=stack` 與 `LOG_STACK=stderr` 建議保留，確保 log 會被 Lightsail Captured logs 接收。
3. `lightsail/containers.json`/`public-endpoint.json` 為部署樣板：
   - `containers.json` 會在 CI 中以 `__IMAGE_NAME__` 置換為實際 image。
   - `public-endpoint.json` 把 8080 port 暴露為 HTTP，健康檢查路徑 `/`。
4. 需要直接使用 CLI 時，可透過：
   ```bash
   aws lightsail push-container-image --service-name <service> --label <sha> --image <local-tag>
   aws lightsail create-container-service-deployment \
       --service-name <service> \
       --containers file://lightsail/containers.json \
       --public-endpoint file://lightsail/public-endpoint.json
   ```
   （請先用 `sed` 或 `jq` 將 `__IMAGE_NAME__` 置換為實際 `<service>:<label>`）。

## GitHub Actions CI/CD

`.github/workflows/lightsail-deploy.yml` 會在 push 到 `main`（或手動 `workflow_dispatch`）時：
1. 建置 Docker image。
2. 透過 `aws lightsail push-container-image` 推送到 Lightsail 服務的私有 registry。
3. 以 repo 中的 `lightsail/*.json` 建立新的 deployment。

啟用前需在 repo secrets 設定：
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`
- `LIGHTSAIL_SERVICE_NAME`（Container Service 名稱）

如需要自訂容器名稱或額外環境變數，可修改 `lightsail/containers.json` 後再提交。

## 目錄速覽

| 路徑 | 說明 |
| --- | --- |
| `Dockerfile` | multi-stage build，內含 nginx + php-fpm + supervisor |
| `.dockerignore` | 排除 `.env`、vendor、logs 等不需進入 image context 的檔案 |
| `docker-compose.yml` | 本機開發使用的完整疊代（Postgres、Redis、Grafana…） |
| `lightsail/containers.json` | Lightsail 容器定義樣板（CI 會套入 image） |
| `lightsail/public-endpoint.json` | Lightsail 公開端點與健康檢查設定 |
| `.github/workflows/lightsail-deploy.yml` | Build & Deploy workflow |
| `src/mealhub/` | Laravel 專案原始碼 |

有任何 CI/CD 或部署上的需求，可在此基礎上擴充（例如 staging pipeline、通知等）。
