# YouTube コメント感情分析システム

YouTube 動画のコメントを取得し、Gemini API でポジティブ / ニュートラル / ネガティブに分類して可視化する PHP アプリケーションです。
ロリポップ共有サーバーへの配置を前提に、Apache + PHP + MySQL で動作します。

## 解説動画

- 準備中

## 技術スタック

| 分類 | 技術 |
|---|---|
| バックエンド | PHP 8.3 |
| フロントエンド | Vanilla JS, Chart.js |
| AI | Gemini API (Google) |
| 外部 API | YouTube Data API v3 |
| DB | MySQL |
| テスト | PHPUnit |
| 配置先 | ロリポップ共有サーバー |

## 主な機能

- YouTube URL / 動画 ID を入力してコメント感情分析を実行
- コメント件数ごとの分析結果を円グラフと件数で可視化
- 24 時間キャッシュによる API コール削減
- 同一 `video_id × comment_limit` の履歴を複数保持し、最新 `analyzed_at` を採用
- `error_log()` を使った障害調査向けログ出力

## 対応 URL 形式

- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://www.youtube.com/embed/VIDEO_ID`
- `VIDEO_ID`

## セットアップ

前提:

- PHP 8.3
- Composer 2

### 1. リポジトリ取得

```bash
git clone <repository-url>
cd youtube-data-api-comment-sentiment-on-lolipop
```

### 2. 設定ファイル作成

```bash
cp php/config.php.example php/config.php
```

`php/config.php` を編集して以下を設定します。

- `YOUTUBE_API_KEY`
- `GEMINI_API_KEY`
- `GEMINI_MODEL`
- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

### 3. Composer 依存関係インストール

```bash
cd php
composer install
```

`composer install` は PHP 8.3 と Composer 2 を前提とする。

### 4. DB スキーマ適用

標準手順は、ロリポップ管理画面から phpMyAdmin を開いて対象 DB を選択し、[php/sql/schema.sql](php/sql/schema.sql) を実行する方法です。
ロリポップ本番環境への反映は、SSH ログインを使わず、phpMyAdmin と FTP / ロリポップFTPで行います。

本番アップロード後は、ディレクトリ `705`、公開ファイル `604`、`config.php` は原則 `600` のパーミッションを確認します。詳細は [デプロイ手順書](docs/13_デプロイ手順書.md) を参照してください。

### 5. ローカル確認

```bash
cd php
php -S localhost:8080
```

ブラウザで `http://localhost:8080` を開きます。

## テスト実行

```bash
cd php
composer install
vendor/bin/phpunit
```

`vendor/bin/phpunit` は `composer install` 実行後に生成される。
このファイルが存在しない場合、または `composer install` が前提条件不足で失敗する場合、動的テストは未実施となる。

## ディレクトリ構成

```text
youtube-data-api-comment-sentiment-on-lolipop/
├── php/                        # メインアプリケーション
│   ├── api/
│   ├── assets/
│   ├── sql/
│   ├── src/
│   ├── tests/
│   ├── config.php.example      # 設定サンプル
│   ├── composer.json
│   └── index.php
├── docs/                       # 設計・運用ドキュメント
├── .gitignore
├── README.md
└── CLAUDE.md
```

## ドキュメント

| # | ドキュメント | 内容 |
|---|---|---|
| 01 | [企画書](docs/01_企画書.md) | プロジェクト概要・目的 |
| 02 | [要件定義書](docs/02_要件定義書.md) | 業務要件・機能要件 |
| 03 | [技術スタック](docs/03_技術スタック.md) | 使用技術一覧 |
| 04 | [基本設計書](docs/04_基本設計書.md) | システム構成・処理フロー |
| 05 | [詳細設計書](docs/05_詳細設計書.md) | PHP クラス設計・API 仕様 |
| 06 | [データ仕様書](docs/06_データ仕様書.md) | JSON スキーマ定義 |
| 07 | [テーブルレイアウト](docs/07_テーブルレイアウト.md) | MySQL テーブル定義 |
| 08 | [AI仕様書](docs/08_AI仕様書.md) | Gemini API 利用仕様 |
| 09 | [ディレクトリ構成](docs/09_ディレクトリ構成.md) | プロジェクト構造詳細 |
| 10 | [テスト仕様書](docs/10_テスト仕様書.md) | テスト設計・テストケース |
| 11 | [VSCode開発マニュアル](docs/11_VSCode開発マニュアル.md) | ローカル開発手順 |
| 12 | [Docker環境構築ガイド](docs/12_Docker環境構築ガイド.md) | Docker でのローカル実行手順 |
| 13 | [デプロイ手順書](docs/13_デプロイ手順書.md) | 初回配置・更新反映手順 |
| 14 | [運用手順書](docs/14_運用手順書.md) | キャッシュ運用・障害対応 |
| 15 | [操作マニュアル](docs/15_操作マニュアル.md) | エンドユーザー向け操作説明 |

## 作者

遠藤 義之

## ライセンス

MIT License
