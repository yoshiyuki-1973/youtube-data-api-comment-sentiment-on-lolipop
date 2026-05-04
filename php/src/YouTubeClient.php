<?php

declare(strict_types=1);

class YouTubeClient
{
    private const BASE_URL = 'https://www.googleapis.com/youtube/v3';
    // 1リクエストあたりの最大取得数（YouTube API上限）
    private const MAX_RESULTS_PER_REQUEST = 100;

    public function __construct(private readonly string $apiKey) {}

    /**
     * 動画メタデータを取得する
     *
     * @return array{video_id:string, title:string, channel_name:string, channel_id:string,
     *               published_at:string, view_count:int, like_count:int, comment_count:int}
     */
    public function fetchVideo(string $videoId): array
    {
        $url = self::BASE_URL . '/videos?' . http_build_query([
            'part' => 'snippet,statistics',
            'id'   => $videoId,
            'key'  => $this->apiKey,
        ]);

        $data = $this->request($url);

        if (empty($data['items'])) {
            throw new VideoNotFoundException("動画が見つかりません: {$videoId}");
        }

        $item = $data['items'][0];
        $stats = $item['statistics'] ?? [];

        return [
            'video_id'      => $item['id'],
            'title'         => $item['snippet']['title'],
            'channel_name'  => $item['snippet']['channelTitle'],
            'channel_id'    => $item['snippet']['channelId'],
            'published_at'  => $item['snippet']['publishedAt'],
            'view_count'    => (int)($stats['viewCount'] ?? 0),
            'like_count'    => (int)($stats['likeCount'] ?? 0),
            'comment_count' => (int)($stats['commentCount'] ?? 0),
        ];
    }

    /**
     * コメントをいいね数降順で取得する
     *
     * @return array<array{text:string, author:string, like_count:int, published_at:string}>
     */
    public function fetchComments(string $videoId, int $limit = 10): array
    {
        // ソート精度を上げるため limit の2倍を取得（最大100件）
        $fetchCount = min($limit * 2, self::MAX_RESULTS_PER_REQUEST);

        $url = self::BASE_URL . '/commentThreads?' . http_build_query([
            'part'       => 'snippet',
            'videoId'    => $videoId,
            'maxResults' => $fetchCount,
            'order'      => 'relevance',
            'key'        => $this->apiKey,
        ]);

        $data = $this->request($url);

        $comments = [];
        foreach ($data['items'] ?? [] as $item) {
            $c = $item['snippet']['topLevelComment']['snippet'];
            $comments[] = [
                'text'         => $c['textDisplay'],
                'author'       => $c['authorDisplayName'],
                'like_count'   => (int)($c['likeCount'] ?? 0),
                'published_at' => $c['publishedAt'],
            ];
        }

        // いいね数降順ソート
        usort($comments, static fn(array $a, array $b): int => $b['like_count'] - $a['like_count']);

        return array_slice($comments, 0, $limit);
    }

    /**
     * YouTube URL / 動画IDから11文字の動画IDを抽出する
     */
    public static function extractVideoId(string $input): string
    {
        $input = trim($input);

        // 11文字の動画IDを直接入力した場合
        if (preg_match('/^[A-Za-z0-9_\-]{11}$/', $input)) {
            return $input;
        }

        // 各URL形式から動画IDを抽出
        $patterns = [
            '/[?&]v=([A-Za-z0-9_\-]{11})/',           // watch?v=
            '/youtu\.be\/([A-Za-z0-9_\-]{11})/',       // youtu.be/
            '/\/shorts\/([A-Za-z0-9_\-]{11})/',         // /shorts/
            '/\/embed\/([A-Za-z0-9_\-]{11})/',          // /embed/
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $m)) {
                return $m[1];
            }
        }

        throw new \InvalidArgumentException('有効なYouTube URLまたは動画IDを入力してください。');
    }

    private function request(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new YouTubeApiException("YouTube APIへの接続に失敗しました: {$error}");
        }

        $data = json_decode($body, true);

        if ($status === 403) {
            $reason = $data['error']['errors'][0]['reason'] ?? '';
            if ($reason === 'quotaExceeded') {
                throw new QuotaExceededException('YouTube APIのクォータを超過しました。翌日（太平洋時間0時）にリセットされます。');
            }
            if ($reason === 'commentsDisabled') {
                throw new CommentsDisabledException('この動画はコメントが無効化されています。');
            }
            throw new AuthenticationException('YouTube APIの認証に失敗しました。APIキーを確認してください。');
        }

        if ($status === 404) {
            throw new VideoNotFoundException('動画が見つかりません。動画IDを確認してください。');
        }

        if ($status !== 200) {
            throw new YouTubeApiException("YouTube APIエラー (HTTP {$status})");
        }

        return $data;
    }
}
