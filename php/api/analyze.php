<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// POST リクエストのみ受け付ける
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$rawInput = trim((string)($input['url'] ?? ''));
$commentLimit = max(1, min(100, (int)($input['limit'] ?? 10)));

if ($rawInput === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'YouTube URLまたは動画IDを入力してください。']);
    exit;
}

try {
    if (!class_exists(GeminiClient::class)) {
        throw new \RuntimeException('AI分析クライアントのファイルが見つかりません。php/src/GeminiClient.php をサーバーへアップロードしてください。');
    }

    $videoId = YouTubeClient::extractVideoId($rawInput);

    $db = Database::getInstance();
    $repository = new VideoRepository($db);
    $cached = $repository->findCachedResult($videoId, $commentLimit);

    if ($cached !== null) {
        echo json_encode([
            'success' => true,
            'cached' => true,
            'video' => [
                'video_id' => $cached['video_id'],
                'title' => $cached['title'],
                'channel_name' => $cached['channel_name'],
                'view_count' => (int)$cached['view_count'],
                'like_count' => (int)$cached['like_count'],
                'comment_count' => (int)$cached['comment_count'],
            ],
            'summary' => buildSummary($cached),
            'comments' => formatComments($cached['comments']),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ytClient = new YouTubeClient(Config::require('YOUTUBE_API_KEY'));
    $video = $ytClient->fetchVideo($videoId);
    $comments = $ytClient->fetchComments($videoId, $commentLimit);

    $geminiClient = new GeminiClient(
        Config::require('GEMINI_API_KEY'),
        Config::get('GEMINI_MODEL', 'gemini-2.5-flash')
    );
    $analyzer = new SentimentAnalyzer($geminiClient);
    $analyzedComments = $analyzer->analyzeComments($comments);
    $summary = SentimentAnalyzer::summarize($analyzedComments);

    $repository->saveResult($video, $analyzedComments, $summary, $commentLimit);

    echo json_encode([
        'success' => true,
        'cached' => false,
        'video' => [
            'video_id' => $video['video_id'],
            'title' => $video['title'],
            'channel_name' => $video['channel_name'],
            'view_count' => $video['view_count'],
            'like_count' => $video['like_count'],
            'comment_count' => $video['comment_count'],
        ],
        'summary' => $summary,
        'comments' => formatComments($analyzedComments),
    ], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (VideoNotFoundException $e) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (QuotaExceededException $e) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (AuthenticationException $e) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (CommentsDisabledException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (YouTubeApiException $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'YouTube APIへの接続または応答処理に失敗しました。APIキー、クォータ、対象動画を確認してください。'], JSON_UNESCAPED_UNICODE);
    error_log('[YT Sentiment] YouTube API error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
} catch (GeminiApiException $e) {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'AI分析サービスへの接続に失敗しました。しばらく待ってから再試行してください。']);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'データベース接続に失敗しました。DB設定とテーブル作成状況を確認してください。'], JSON_UNESCAPED_UNICODE);
    error_log('[YT Sentiment] Database error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
} catch (\RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'サーバー設定に問題があります。config.php とアップロード済みファイルを確認してください。'], JSON_UNESCAPED_UNICODE);
    error_log('[YT Sentiment] Runtime error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'サーバーエラーが発生しました。']);
    error_log('[YT Sentiment] ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}

function buildSummary(array $data): array
{
    return [
        'positive_count' => (int)$data['positive_count'],
        'negative_count' => (int)$data['negative_count'],
        'neutral_count' => (int)$data['neutral_count'],
        'total_count' => (int)$data['total_count'],
        'positive_ratio' => (float)$data['positive_ratio'],
        'negative_ratio' => (float)$data['negative_ratio'],
        'neutral_ratio' => (float)$data['neutral_ratio'],
        'avg_positive_score' => (float)$data['avg_positive_score'],
        'avg_negative_score' => (float)$data['avg_negative_score'],
        'avg_neutral_score' => (float)$data['avg_neutral_score'],
    ];
}

function formatComments(array $comments): array
{
    return array_map(static function (array $c): array {
        return [
            'text' => $c['text'] ?? $c['comment_text'] ?? '',
            'author' => $c['author'] ?? $c['author_name'] ?? '',
            'like_count' => (int)($c['like_count'] ?? 0),
            'published_at' => $c['published_at'] ?? '',
            'sentiment' => $c['sentiment'],
            'positive_score' => (float)$c['positive_score'],
            'negative_score' => (float)$c['negative_score'],
            'neutral_score' => (float)$c['neutral_score'],
        ];
    }, $comments);
}
