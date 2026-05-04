# Project Guide

このリポジトリのメイン開発対象は `php/` ディレクトリです。
旧 Python / Docker / Terraform 構成は廃止し、現行はロリポップ共有サーバー向けの PHP アプリケーションとして整理しています。

## Current Stack

| 項目 | 内容 |
|---|---|
| 言語 | PHP 8.3 |
| 実行環境 | Apache + PHP |
| AI | Grok API |
| 外部 API | YouTube Data API v3 |
| DB | MySQL |
| テスト | PHPUnit |

## Setup

```bash
cd php
cp config.php.example config.php
# config.php を編集して API キー・DB 情報を設定

php --version
composer --version
composer install
```

`composer install` は PHP 8.3 と Composer 2 を前提とします。

DB スキーマは標準手順として phpMyAdmin から `sql/schema.sql` を実行します。
SSH / CLI が使える場合は、既存 DB に対して同じ SQL を適用しても構いません。

## Local Run

```bash
cd php
php -S localhost:8080
```

## Test

```bash
cd php
vendor/bin/phpunit
```

## Deploy

デプロイ手順は [docs/12_デプロイ手順書.md](docs/12_デプロイ手順書.md) を参照してください。
`config.php` は `.gitignore` 対象のため、Git ではなく手動で配置します。

## Structure

```text
php/
├── api/
├── assets/
├── sql/
├── src/
├── tests/
├── config.php              # 実設定（Git 管理外）
├── config.php.example      # 設定サンプル
├── composer.json
├── phpunit.xml
└── index.php
```
