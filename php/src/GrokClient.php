<?php

declare(strict_types=1);

class GrokClient
{
    private const BASE_URL   = 'https://api.x.ai/v1';
    private const TIMEOUT    = 60;
    // 1回のAPIリクエストで処理するコメント数
    private const BATCH_SIZE = 10;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'grok-3-mini'
    ) {}

    /**
     * コメント配列をバッチで感情分析し、スコア配列を返す
     *
     * @param  array<array{text:string, ...}> $comments
     * @return array<array{positive:float, negative:float, neutral:float}>
     */
    public function analyzeSentimentBatch(array $comments): array
    {
        if (empty($comments)) {
            return [];
        }

        $results = [];
        $batches  = array_chunk($comments, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            $batchResults = $this->analyzeBatch($batch);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * @param  array<array{text:string}> $batch
     * @return array<array{positive:float, negative:float, neutral:float}>
     */
    private function analyzeBatch(array $batch): array
    {
        $numbered = '';
        foreach ($batch as $i => $comment) {
            $text = mb_substr(strip_tags($comment['text']), 0, 300);
            $numbered .= ($i + 1) . '. ' . $text . "\n";
        }

        $prompt = <<<PROMPT
以下のYouTubeコメントそれぞれの感情を分析してください。

コメント一覧:
{$numbered}
各コメントの positive（ポジティブ）、negative（ネガティブ）、neutral（中立）の確率スコアを返してください。
3つの値の合計は必ず1.0になるようにしてください。

JSON配列のみで回答してください（説明文・コードブロック不要）:
[{"positive":0.0,"negative":0.0,"neutral":0.0},...]
PROMPT;

        $content = $this->chatCompletion($prompt);
        $scores  = $this->parseJsonArray($content, count($batch));

        return $scores;
    }

    private function chatCompletion(string $prompt): string
    {
        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0,
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::BASE_URL . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new GrokApiException("Grok APIへの接続に失敗しました: {$error}");
        }

        if ($status === 429) {
            throw new GrokApiException('Grok APIのレート制限に達しました。しばらく待ってから再試行してください。');
        }

        if ($status !== 200) {
            throw new GrokApiException("Grok APIエラー (HTTP {$status}): {$body}");
        }

        $data = json_decode($body, true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * レスポンスからJSON配列を抽出し、スコアを正規化して返す
     *
     * @return array<array{positive:float, negative:float, neutral:float}>
     */
    private function parseJsonArray(string $content, int $expectedCount): array
    {
        // コードブロックやテキストからJSON配列を抽出
        if (!preg_match('/\[[\s\S]*\]/u', $content, $matches)) {
            return $this->defaultScores($expectedCount);
        }

        $decoded = json_decode($matches[0], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $this->defaultScores($expectedCount);
        }

        $results = [];
        foreach ($decoded as $score) {
            $pos = max(0.0, (float)($score['positive'] ?? 0));
            $neg = max(0.0, (float)($score['negative'] ?? 0));
            $neu = max(0.0, (float)($score['neutral']  ?? 0));

            // 合計が0の場合は均等配分
            $sum = $pos + $neg + $neu;
            if ($sum <= 0) {
                $results[] = ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
                continue;
            }

            $results[] = [
                'positive' => round($pos / $sum, 4),
                'negative' => round($neg / $sum, 4),
                'neutral'  => round($neu / $sum, 4),
            ];
        }

        // 件数が不足している場合はデフォルト値で補完
        while (count($results) < $expectedCount) {
            $results[] = ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
        }

        return array_slice($results, 0, $expectedCount);
    }

    /** @return array<array{positive:float, negative:float, neutral:float}> */
    private function defaultScores(int $count): array
    {
        return array_fill(0, $count, ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34]);
    }
}
