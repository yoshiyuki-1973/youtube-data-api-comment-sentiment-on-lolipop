# VSCode開発マニュアル

PHP / ロリポップ構成におけるローカル開発手順をまとめる。  
この文書は **VSCode 上での開発・テスト・デバッグ** に絞り、デプロイ手順は [12_デプロイ手順書.md](12_デプロイ手順書.md) に分離する。

---

## 1. 前提条件

| ツール | バージョン | 用途 |
|---|---|---|
| VSCode | 最新版 | エディタ |
| PHP | 8.1 以上 | ローカル実行 |
| Composer | 最新版 | 依存関係管理 |
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

### 2.1 リポジトリ取得

```powershell
git clone <repository-url>
cd youtube-data-api-comment-sentiment-on-lolipop
cd php
```

### 2.2 設定ファイル作成

```powershell
Copy-Item config.php.example config.php
code config.php
```

`config.php` に以下を設定する。

- `YOUTUBE_API_KEY`
- `GROK_API_KEY`
- `GROK_MODEL`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

### 2.3 依存関係インストール

```powershell
composer install
```

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

---

## 4. 開発ワークフロー

1. `main` から作業ブランチを切る
2. VSCode で編集する
3. `php -S localhost:8080` で確認する
4. `vendor/bin/phpunit` でテストする
5. コミットしてレビューする

### ブランチ作成例

```powershell
git checkout main
git pull origin main
git checkout -b feature/your-feature-name
```

---

## 5. テスト実行

```powershell
vendor/bin/phpunit
```

詳細出力:

```powershell
vendor/bin/phpunit --testdox
```

特定クラスのみ:

```powershell
vendor/bin/phpunit tests/YouTubeClientTest.php
```

カバレッジ:

```powershell
vendor/bin/phpunit --coverage-html coverage-report
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

### DB 接続エラー

- `config.php` の `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS` を確認する
- ロリポップ管理画面の MySQL 情報と一致しているか確認する

---

## 8. 関連ドキュメント

| ドキュメント | 内容 |
|---|---|
| [05_詳細設計書.md](05_詳細設計書.md) | PHP クラス設計・API 仕様 |
| [10_テスト仕様書.md](10_テスト仕様書.md) | PHPUnit テスト設計 |
| [12_デプロイ手順書.md](12_デプロイ手順書.md) | 初回配置・更新反映 |
| [13_運用手順書.md](13_運用手順書.md) | 運用・障害対応 |
