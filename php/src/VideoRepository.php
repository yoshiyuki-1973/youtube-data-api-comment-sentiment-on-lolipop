<?php

declare(strict_types=1);

class VideoRepository
{
    // キャッシュ有効時間（時間）
    private const CACHE_HOURS = 24;

    public function __construct(private readonly PDO $db) {}

    /**
     * キャッシュから分析結果を取得する。キャッシュ切れ or 未保存なら null を返す。
     */
    public function findCachedResult(string $videoId, int $commentLimit): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT ar.*, v.title, v.channel_name, v.channel_id,
                    v.published_at AS video_published_at,
                    v.view_count, v.like_count, v.comment_count
             FROM analysis_results ar
             JOIN videos v ON v.video_id = ar.video_id
             WHERE ar.video_id = :video_id
               AND ar.comment_limit = :limit
               AND ar.analyzed_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
             ORDER BY ar.analyzed_at DESC
             LIMIT 1'
        );
        $stmt->execute([
            ':video_id' => $videoId,
            ':limit'    => $commentLimit,
            ':hours'    => self::CACHE_HOURS,
        ]);
        $result = $stmt->fetch();

        if (!$result) {
            return null;
        }

        $stmt2 = $this->db->prepare(
            'SELECT comment_text AS text, author_name AS author, like_count,
                    published_at, sentiment, positive_score, negative_score, neutral_score
             FROM comments
             WHERE analysis_id = :id
             ORDER BY like_count DESC'
        );
        $stmt2->execute([':id' => $result['id']]);
        $result['comments'] = $stmt2->fetchAll();

        return $result;
    }

    /**
     * 動画情報・分析結果・コメントをDBに保存し、整形済み結果配列を返す
     *
     * @param  array $video           fetchVideo() の戻り値
     * @param  array $analyzedComments analyzeComments() の戻り値
     * @param  array $summary         SentimentAnalyzer::summarize() の戻り値
     * @param  int   $commentLimit    取得件数
     */
    public function saveResult(array $video, array $analyzedComments, array $summary, int $commentLimit): array
    {
        $this->db->beginTransaction();
        try {
            $this->upsertVideo($video);
            $analysisId = $this->insertAnalysisResult($video['video_id'], $summary, $commentLimit);
            $this->insertComments($analysisId, $analyzedComments);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return [
            'id'           => $analysisId,
            'video_id'     => $video['video_id'],
            'title'        => $video['title'],
            'channel_name' => $video['channel_name'],
            'view_count'   => $video['view_count'],
            'like_count'   => $video['like_count'],
            'comment_count'=> $video['comment_count'],
            'comments'     => $analyzedComments,
        ] + $summary;
    }

    private function upsertVideo(array $video): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO videos
               (video_id, title, channel_name, channel_id, published_at,
                view_count, like_count, comment_count, fetched_at)
             VALUES
               (:video_id, :title, :channel_name, :channel_id, :published_at,
                :view_count, :like_count, :comment_count, NOW())
             ON DUPLICATE KEY UPDATE
               title         = VALUES(title),
               view_count    = VALUES(view_count),
               like_count    = VALUES(like_count),
               comment_count = VALUES(comment_count),
               fetched_at    = NOW()'
        );
        $stmt->execute([
            ':video_id'      => $video['video_id'],
            ':title'         => $video['title'],
            ':channel_name'  => $video['channel_name'],
            ':channel_id'    => $video['channel_id'],
            ':published_at'  => (new \DateTime($video['published_at']))->format('Y-m-d H:i:s'),
            ':view_count'    => $video['view_count'],
            ':like_count'    => $video['like_count'],
            ':comment_count' => $video['comment_count'],
        ]);
    }

    private function insertAnalysisResult(string $videoId, array $summary, int $commentLimit): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO analysis_results
               (video_id, comment_limit,
                positive_count, negative_count, neutral_count, total_count,
                positive_ratio, negative_ratio, neutral_ratio,
                avg_positive_score, avg_negative_score, avg_neutral_score,
                analyzed_at)
             VALUES
               (:video_id, :comment_limit,
                :positive_count, :negative_count, :neutral_count, :total_count,
                :positive_ratio, :negative_ratio, :neutral_ratio,
                :avg_positive_score, :avg_negative_score, :avg_neutral_score,
                NOW())'
        );
        $stmt->execute([
            ':video_id'          => $videoId,
            ':comment_limit'     => $commentLimit,
            ':positive_count'    => $summary['positive_count'],
            ':negative_count'    => $summary['negative_count'],
            ':neutral_count'     => $summary['neutral_count'],
            ':total_count'       => $summary['total_count'],
            ':positive_ratio'    => $summary['positive_ratio'],
            ':negative_ratio'    => $summary['negative_ratio'],
            ':neutral_ratio'     => $summary['neutral_ratio'],
            ':avg_positive_score'=> $summary['avg_positive_score'],
            ':avg_negative_score'=> $summary['avg_negative_score'],
            ':avg_neutral_score' => $summary['avg_neutral_score'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    private function insertComments(int $analysisId, array $comments): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO comments
               (analysis_id, comment_text, author_name, like_count,
                published_at, sentiment, positive_score, negative_score, neutral_score)
             VALUES
               (:analysis_id, :text, :author, :like_count,
                :published_at, :sentiment, :positive_score, :negative_score, :neutral_score)'
        );
        foreach ($comments as $c) {
            $stmt->execute([
                ':analysis_id'   => $analysisId,
                ':text'          => $c['text'],
                ':author'        => $c['author'],
                ':like_count'    => $c['like_count'],
                ':published_at'  => (new \DateTime($c['published_at']))->format('Y-m-d H:i:s'),
                ':sentiment'     => $c['sentiment'],
                ':positive_score'=> $c['positive_score'],
                ':negative_score'=> $c['negative_score'],
                ':neutral_score' => $c['neutral_score'],
            ]);
        }
    }
}
