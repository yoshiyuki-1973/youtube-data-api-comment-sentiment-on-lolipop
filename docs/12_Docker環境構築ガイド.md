# Docker ローカル環境構築ガイド

ローカル PC 上で Docker を使い、MySQL を含む完全な実行環境を構築する手順書。
PHP や Composer、MySQL をインストールしなくてもアプリを動かせる。

---

## 前提

| ツール | 確認コマンド |
|---|---|
| Docker Desktop（最新版） | `docker --version` |
| Git | `git --version` |

Docker Desktop が起動していることを確認してから進む。

---

## 1. リポジトリ取得

```powershell
git clone <repository-url>
cd youtube-data-api-comment-sentiment-on-lolipop
```

---

## 2. 設定ファイル作成

Docker 用のサンプルをコピーする。DB 接続情報は記入済みなので **API キーだけ** 書き換えればよい。

```powershell
Copy-Item php\config.php.docker.example php\config.php
notepad php\config.php
```

書き換える箇所：

| キー | 設定する値 |
|---|---|
| `YOUTUBE_API_KEY` | Google Cloud Console で取得したキー |
| `GEMINI_API_KEY` | Google AI Studio で取得したキー |

それ以外（`DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`）は変更不要。

---

## 3. コンテナ起動

プロジェクトルートで実行する。

```powershell
docker compose up -d --build
```

初回はイメージのビルドに数分かかる。完了後、以下で状態を確認する。

```powershell
docker compose ps
```

`app` と `db` の STATUS が `running` / `healthy` になれば準備完了。

```
NAME                    STATUS
...-app-1               running
...-db-1                healthy
```

---

## 4. ブラウザで確認

`http://localhost:8080` を開き、以下を確認する。

- [ ] フォームが表示される
- [ ] YouTube URL を入力して「分析」を押すと結果が返る
- [ ] 円グラフとコメント一覧が表示される

---

## 5. テスト実行

PHPUnit をコンテナ内で実行する。初回のみ `composer install` が必要。

```powershell
docker compose exec app composer install
docker compose exec app vendor/bin/phpunit --testdox
```

2 回目以降は `composer install` を省略できる。

```powershell
docker compose exec app vendor/bin/phpunit --testdox
```

---

## 6. 停止・再起動

### 停止（データ保持）

```powershell
docker compose down
```

次回は `docker compose up -d` だけで再起動できる（`--build` 不要）。

### 完全削除（DB データも消す）

```powershell
docker compose down -v
```

次回起動時にスキーマが再適用され、DB が初期状態に戻る。

---

## 7. コードを変更したとき

PHP ファイルはボリュームマウントされているため、ファイルを保存するとブラウザリロードだけで反映される。**コンテナの再起動は不要。**

`Dockerfile` や `docker-compose.yml` を変更した場合のみ再ビルドが必要。

```powershell
docker compose up -d --build
```

---

## 8. トラブルシューティング

### ポート 8080 が使用中

```powershell
docker compose down
```

それでも起動しない場合は `docker-compose.yml` の `ports` を `"8081:80"` に変更して再起動する。

### DB に接続できない

`php/config.php` の DB 設定を確認する。

```php
'DB_HOST' => 'db',           // ← 'localhost' ではなく 'db'
'DB_NAME' => 'yt_sentiment',
'DB_USER' => 'appuser',
'DB_PASS' => 'secret',
```

DB コンテナのログも確認する。

```powershell
docker compose logs db
```

### スキーマが反映されない

ボリュームにデータが残っていると `schema.sql` が再実行されない。一度完全削除してから再起動する。

```powershell
docker compose down -v
docker compose up -d --build
```

### コンテナのログを確認したい

```powershell
docker compose logs app        # PHP / Apache のログ
docker compose logs db         # MySQL のログ
docker compose logs -f app     # リアルタイムで流す
```

---

## 9. 構成メモ

| 項目 | 内容 |
|---|---|
| アプリ URL | `http://localhost:8080` |
| PHP バージョン | 8.3（Apache） |
| MySQL バージョン | 8.0 |
| DB 名 | `yt_sentiment` |
| DB ユーザー | `appuser` / `secret` |
| スキーマ | 初回起動時に `php/sql/schema.sql` が自動適用される |
| コード反映 | ファイル保存 → ブラウザリロードだけで反映（再起動不要） |
