<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$checks = [
    'youtube' => [
        'ok' => false,
    ],
    'gemini' => [
        'ok' => false,
    ],
    'save' => [
        'ok' => null,
    ],
];
$video = null;
$comments = [];
$scores = [];

try {
    $videoId = isset($_GET['video_id']) && is_string($_GET['video_id'])
        ? YouTubeClient::extractVideoId($_GET['video_id'])
        : 'dQw4w9WgXcQ';
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 1)));

    $youtube = new YouTubeClient(Config::require('YOUTUBE_API_KEY'));
    $video = $youtube->fetchVideo($videoId);
    $comments = $youtube->fetchComments($videoId, $limit);

    $checks['youtube'] = [
        'ok' => true,
        'video_id' => $video['video_id'],
        'title' => $video['title'],
        'requested_limit' => $limit,
        'comment_sample_count' => count($comments),
    ];
} catch (Throwable $e) {
    $checks['youtube'] = [
        'ok' => false,
        'type' => get_class($e),
        'message' => $e->getMessage(),
    ];
}

try {
    $gemini = new GeminiClient(
        Config::require('GEMINI_API_KEY'),
        Config::get('GEMINI_MODEL', 'gemini-2.5-flash')
    );
    $scores = $gemini->analyzeSentimentBatch([
        [
            'text' => 'This is a small connectivity test.',
            'author' => 'probe',
            'like_count' => 0,
            'published_at' => date(DATE_ATOM),
        ],
    ]);

    $checks['gemini'] = [
        'ok' => true,
        'score_count' => count($scores),
        'first_score' => $scores[0] ?? null,
    ];
} catch (Throwable $e) {
    $checks['gemini'] = [
        'ok' => false,
        'type' => get_class($e),
        'message' => $e->getMessage(),
    ];
}

if (($_GET['save'] ?? '') === '1') {
    try {
        if ($video === null || $comments === []) {
            throw new RuntimeException('YouTube probe result is unavailable.');
        }

        $analyzer = new SentimentAnalyzer(new GeminiClient(
            Config::require('GEMINI_API_KEY'),
            Config::get('GEMINI_MODEL', 'gemini-2.5-flash')
        ));
        $analyzedComments = $analyzer->analyzeComments($comments);
        $summary = SentimentAnalyzer::summarize($analyzedComments);

        $repository = new VideoRepository(Database::getInstance());
        $saved = $repository->saveResult($video, $analyzedComments, $summary, $limit);

        $checks['save'] = [
            'ok' => true,
            'analysis_id' => $saved['id'],
            'saved_comments' => count($saved['comments']),
        ];
    } catch (Throwable $e) {
        $checks['save'] = [
            'ok' => false,
            'type' => get_class($e),
            'message' => $e->getMessage(),
        ];
    }
}

$checks['ok'] =
    $checks['youtube']['ok'] === true
    && $checks['gemini']['ok'] === true
    && ($checks['save']['ok'] !== false);

http_response_code($checks['ok'] ? 200 : 500);
echo json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
