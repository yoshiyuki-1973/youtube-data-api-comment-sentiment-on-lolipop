<?php

declare(strict_types=1);

class GeminiClient
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';
    private const TIMEOUT = 60;
    private const BATCH_SIZE = 10;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.5-flash'
    ) {}

    /**
     * @param  array<array{text:string, ...}> $comments
     * @return array<array{positive:float, negative:float, neutral:float}>
     */
    public function analyzeSentimentBatch(array $comments): array
    {
        if (empty($comments)) {
            return [];
        }

        $results = [];
        $batches = array_chunk($comments, self::BATCH_SIZE);

        foreach ($batches as $batch) {
            $results = array_merge($results, $this->analyzeBatch($batch));
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
Analyze the sentiment of each YouTube comment below.

Comments:
{$numbered}

Return only a JSON array. Do not include Markdown fences or explanations.
Each item must have positive, negative, and neutral scores, and the three scores must sum to 1.0.
Example:
[{"positive":0.0,"negative":0.0,"neutral":0.0}]
PROMPT;

        $content = $this->generateContent($prompt);

        return $this->parseJsonArray($content, count($batch));
    }

    private function generateContent(string $prompt): string
    {
        $payload = json_encode([
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0,
            ],
        ], JSON_UNESCAPED_UNICODE);

        $url = self::BASE_URL . '/models/' . rawurlencode($this->model) . ':generateContent';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey,
            ],
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new GeminiApiException("Gemini API connection failed: {$error}");
        }

        if ($status === 429) {
            throw new GeminiApiException('Gemini API rate limit reached. Please retry later.');
        }

        if ($status !== 200) {
            throw new GeminiApiException("Gemini API error (HTTP {$status}): {$body}");
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new GeminiApiException('Gemini API returned invalid JSON.');
        }

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        if (!is_array($parts)) {
            return '';
        }

        $text = '';
        foreach ($parts as $part) {
            $text .= (string)($part['text'] ?? '');
        }

        return $text;
    }

    /**
     * @return array<array{positive:float, negative:float, neutral:float}>
     */
    private function parseJsonArray(string $content, int $expectedCount): array
    {
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
            $neu = max(0.0, (float)($score['neutral'] ?? 0));
            $sum = $pos + $neg + $neu;

            if ($sum <= 0) {
                $results[] = ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];
                continue;
            }

            $results[] = [
                'positive' => round($pos / $sum, 4),
                'negative' => round($neg / $sum, 4),
                'neutral' => round($neu / $sum, 4),
            ];
        }

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
