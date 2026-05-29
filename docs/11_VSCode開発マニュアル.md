# VSCode開発マニュアル

PHP / ロリポップ構成におけるローカル開発手順をまとめる。
この文書は **VSCode 上での開発・テスト・デバッグ** に絞り、デプロイ手順は [13_デプロイ手順書.md](13_デプロイ手順書.md) に分離する。

---

## 1. 前提条件

| ツール | バージョン | 用途 |
|---|---|---|
| VSCode | 最新版 | エディタ |
| PHP | 8.3 | ローカル実行 |
| Composer | 2.x | 依存関係管理 |
| Git | 最新版 | ソース管理 |

### 推奨 VSCode 拡張

| 拡張 ID | 名称 | 用途 |
|---|---|---|
| `bmewburn.vscode-intelephense-client` | PHP Intelephense | PHP 補完・静的解析 |
| `xdebug.php-debug` | PHP Debug | Xdebug 連携 |
| `neilbrayfield.php-docblocker` | PHP DocBlocker | DocBlock 補助 |
| `yzhang.markdown-all-in-one` | Markdown All in One | ドキュメント編集 |

---

## 2. 初回セットアップ

> **ローカル確認の方法は 2 通りある。**
> - **PHP 内蔵サーバー（3.1）** — PHP が手元にあり、DB 接続不要の画面確認だけでよい場合
> - **Docker（3.3）** — DB も含めた完全なローカル環境が必要な場合
>
> Docker を使う場合は 2.3 の `composer install` をスキップし、3.3 の手順に進んでよい。

### 2.1 リポジトリ取得

```powershell
git clone <repository-url>
cd youtube-data-api-comment-sentiment-on-lolipop
```

### 2.2 設定ファイル作成

`php/` ディレクトリで実行する。

```powershell
cd php
Copy-Item config.php.example config.php
code config.php
```

`config.php` に以下を設定する。

| キー | 説明 |
|---|---|
| `YOUTUBE_API_KEY` | Google Cloud Console で取得したキー |
| `GEMINI_API_KEY` | Google AI Studio で取得したキー |
| `GEMINI_MODEL` | 使用モデル（デフォルト: `gemini-2.5-flash`） |
| `DB_HOST` | **PHP 内蔵サーバー**: ロリポップの MySQL ホスト / **Docker**: `db` |
| `DB_NAME` | **PHP 内蔵サーバー**: ロリポップの DB 名 / **Docker**: `yt_sentiment` |
| `DB_USER` | **PHP 内蔵サーバー**: ロリポップの DB ユーザー / **Docker**: `appuser` |
| `DB_PASS` | **PHP 内蔵サーバー**: ロリポップの DB パスワード / **Docker**: `secret` |

### 2.3 依存関係インストール（PHP 内蔵サーバーのみ）

Docker を使う場合はこの手順をスキップし、[3.3 Docker を使う方法](#33-docker-を使う方法db-を含む完全なローカル環境) へ進む。

```powershell
php --version
composer --version
composer install
```

> `composer install` は PHP 8.3 と Composer 2 を前提とする。Composer 1 系では Packagist 側のサポート終了により失敗する。

---

## 3. ローカル動作確認

### 3.1 PHP 内蔵サーバー起動

```powershell
php -S localhost:8080
```

ブラウザで `http://localhost:8080` を開く。

### 3.2 確認ポイント

- フォームが表示されること
- YouTube URL を入力して分析実行できること
- 円グラフと件数が表示されること
- エラー時に JSON 応答と画面メッセージが崩れないこと

> `DB_HOST` はロリポップ MySQL 向けの値を使うため、ローカルから接続できない場合がある。
> その場合は DB 接続確認を別環境で行うか、画面レンダリングと静的資産の確認を優先する。

### 3.3 Docker を使う方法（DB を含む完全なローカル環境）

MySQL コンテナも含めた完全なローカル環境を Docker で構築する手順。2.3 の `composer install` は不要で、PHP や Composer が手元になくても動作する。

#### 前提

| ツール | 用途 |
|---|---|
| Docker Desktop（最新版） | コンテナ実行 |

#### 手順

**1. config.php を Docker 用のサンプルからコピーする**

`php/` ディレクトリで実行する。DB 接続情報は `config.php.docker.example` に設定済みのため、API キーの書き換えだけで使える。

```powershell
Copy-Item config.php.docker.example config.php
code config.php
```

`YOUTUBE_API_KEY` と `GEMINI_API_KEY` を実際の値に書き換える。DB 接続情報（`DB_HOST=db` など）は変更不要。

**2. コンテナをビルド・起動する**

プロジェクトルート（`php/` の一段上）で実行する。

```powershell
cd ..
docker compose up -d --build
```

初回は PHP イメージのビルドに数分かかる。`sql/schema.sql` によるスキーマ適用も自動で行われる。

**3. 起動を確認する**

```powershell
docker compose ps
```

`app` と `db` が `running` / `healthy` になれば準備完了。

```powershell
docker compose logs app   # app コンテナのログ
docker compose logs db    # DB 初期化ログ
```

**4. ブラウザで確認する**

`http://localhost:8080` を開く。確認ポイントは [3.2 確認ポイント](#32-確認ポイント) を参照する。

**5. コンテナを停止する**

```powershell
docker compose down
```

DB データを含めて完全に削除する場合は次を使う。

```powershell
docker compose down -v
```

#### テストをコンテナ内で実行する

コンテナ内の Composer で依存関係をインストールしてから PHPUnit を実行する。

```powershell
docker compose exec app composer install
docker compose exec app vendor/bin/phpunit --testdox
```

#### Dockerfile を変更したら

Dockerfile や `docker-compose.yml` を変更した場合はイメージを再ビルドする。

```powershell
docker compose up -d --build
```

---

## 4. 開発ワークフロー

### PHP 内蔵サーバーを使う場合

1. `main` から作業ブランチを切る
2. VSCode で編集する
3. `php -S localhost:8080` で確認する（`php/` ディレクトリで実行）
4. `vendor/bin/phpunit` でテストする
5. コミットしてレビューする

### Docker を使う場合

1. `main` から作業ブランチを切る
2. VSCode で編集する（ファイルはボリュームマウントにより即座にコンテナへ反映される）
3. `http://localhost:8080` で確認する（コンテナが起動中であること）
4. `docker compose exec app vendor/bin/phpunit --testdox` でテストする
5. コミットしてレビューする

### ブランチ作成例

```powershell
git checkout main
git pull origin main
git checkout -b feature/your-feature-name
```

---

## 5. テスト実行

### PHP 内蔵サーバー環境（ホスト実行）

`php/` ディレクトリで実行する。

```powershell
composer install
vendor/bin/phpunit --testdox
```

特定クラスのみ:

```powershell
vendor/bin/phpunit tests/GeminiClientTest.php
```

カバレッジ:

```powershell
vendor/bin/phpunit --coverage-html coverage-report
```

### Docker 環境（コンテナ内実行）

コンテナが起動中の状態で実行する。初回は `composer install` が必要。

```powershell
docker compose exec app composer install
docker compose exec app vendor/bin/phpunit --testdox
```

特定クラスのみ:

```powershell
docker compose exec app vendor/bin/phpunit tests/GeminiClientTest.php
```

詳細は [10_テスト仕様書.md](10_テスト仕様書.md) を参照する。

---

## 6. デバッグ方法

### 6.1 `error_log()` を使う

```php
error_log('[YT Sentiment] ' . $message);
error_log('[DEBUG] ' . print_r($variable, true));
```

### 6.2 API を直接確認する

```powershell
$body = '{"url":"dQw4w9WgXcQ","limit":5}'
Invoke-WebRequest -Uri "http://localhost:8080/api/analyze.php" `
    -Method POST -Body $body -ContentType "application/json"
```

### 6.3 Xdebug

必要に応じて Xdebug を導入し、VSCode の `PHP Debug` 拡張でブレークポイントを利用する。

---

## 7. トラブルシューティング

### PHP サーバーが起動しない

```powershell
php --version
php -S localhost:8081
```

### Composer が見つからない

```powershell
where composer
php C:\ProgramData\ComposerSetup\bin\composer.phar install
```

### Composer 1 系で失敗する

- `composer --version` を確認し、1.x の場合は 2.x に更新する
- `php --version` を確認し、8.3 未満の場合は先に PHP を更新する

### DB 接続エラー

- `config.php` の `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` を確認する
- ロリポップ管理画面の MySQL 情報と一致しているか確認する

### Docker: ポート 8080 が使用中

```powershell
docker compose down
# 他のプロセスが使用中の場合は docker-compose.yml の ports を "8081:80" に変更する
```

### Docker: DB に接続できない

`config.php` の `DB_HOST` が `db` になっているか確認する（`localhost` ではなく `db`）。

コンテナのログを確認する。

```powershell
docker compose logs db
```

### Docker: スキーマが反映されない

`db_data` ボリュームにすでにデータが存在すると `schema.sql` が再実行されない。ボリュームを削除してやり直す。

```powershell
docker compose down -v
docker compose up -d
```

---

## 8. 関連ドキュメント

| ドキュメント | 内容 |
|---|---|
| [05_詳細設計書.md](05_詳細設計書.md) | PHP クラス設計・API 仕様 |
| [10_テスト仕様書.md](10_テスト仕様書.md) | PHPUnit テスト設計 |
| [13_デプロイ手順書.md](13_デプロイ手順書.md) | 初回配置・更新反映 |
| [14_運用手順書.md](14_運用手順書.md) | 運用・障害対応 |
