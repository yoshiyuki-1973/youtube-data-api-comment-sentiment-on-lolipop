-- YouTube動画コメント感情分析システム - MySQLスキーマ
-- 既存の対象データベースを選択した状態で実行する
-- CREATE DATABASE / USE は含めない

-- 動画メタデータキャッシュ
CREATE TABLE IF NOT EXISTS videos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id     VARCHAR(20)   NOT NULL UNIQUE,
    title        VARCHAR(500)  NOT NULL,
    channel_name VARCHAR(255)  NOT NULL,
    channel_id   VARCHAR(50)   NOT NULL,
    published_at DATETIME      NOT NULL,
    view_count   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    like_count   BIGINT UNSIGNED NOT NULL DEFAULT 0,
    comment_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    fetched_at   DATETIME      NOT NULL,
    INDEX idx_fetched_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 分析結果（動画×取得件数ごとにキャッシュ）
CREATE TABLE IF NOT EXISTS analysis_results (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id            VARCHAR(20)    NOT NULL,
    comment_limit       SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    positive_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    negative_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    neutral_count       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    total_count         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    positive_ratio      DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    negative_ratio      DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    neutral_ratio       DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    avg_positive_score  DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    avg_negative_score  DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    avg_neutral_score   DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    analyzed_at         DATETIME       NOT NULL,
    FOREIGN KEY (video_id) REFERENCES videos(video_id) ON DELETE CASCADE,
    INDEX idx_video_limit (video_id, comment_limit),
    INDEX idx_analyzed_at (analyzed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- コメント詳細（分析結果ごとに保存）
CREATE TABLE IF NOT EXISTS comments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    analysis_id     INT UNSIGNED   NOT NULL,
    comment_text    TEXT           NOT NULL,
    author_name     VARCHAR(255)   NOT NULL DEFAULT '',
    like_count      INT UNSIGNED   NOT NULL DEFAULT 0,
    published_at    DATETIME       NOT NULL,
    sentiment       ENUM('pos','neg','neutral') NOT NULL DEFAULT 'neutral',
    positive_score  DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    negative_score  DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    neutral_score   DECIMAL(5,4)   NOT NULL DEFAULT 0.0000,
    FOREIGN KEY (analysis_id) REFERENCES analysis_results(id) ON DELETE CASCADE,
    INDEX idx_analysis_sentiment (analysis_id, sentiment)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
