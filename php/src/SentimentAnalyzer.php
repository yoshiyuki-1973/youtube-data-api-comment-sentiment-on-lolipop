<?php

declare(strict_types=1);

class SentimentAnalyzer
{
    public function __construct(private readonly GeminiClient $geminiClient) {}

    /**
     * コメント配列を感情分析し、各コメントにスコアとラベルを付与して返す
     *
     * @param  array<array{text:string, author:string, like_count:int, published_at:string}> $comments
     * @return array<array{text:string, author:string, like_count:int, published_at:string,
     *                     sentiment:string, positive_score:float, negative_score:float, neutral_score:float}>
     */
    public function analyzeComments(array $comments): array
    {
        if (empty($comments)) {
            return [];
        }

        $scores  = $this->geminiClient->analyzeSentimentBatch($comments);
        $results = [];

        foreach ($comments as $i => $comment) {
            $score = $scores[$i] ?? ['positive' => 0.33, 'negative' => 0.33, 'neutral' => 0.34];

            $results[] = array_merge($comment, [
                'sentiment'      => $this->determineSentiment($score),
                'positive_score' => $score['positive'],
                'negative_score' => $score['negative'],
                'neutral_score'  => $score['neutral'],
            ]);
        }

        return $results;
    }

    /**
     * スコアが最も高いラベルを返す（同値の場合は pos > neutral > neg の優先順）
     */
    private function determineSentiment(array $score): string
    {
        $pos = $score['positive'];
        $neg = $score['negative'];
        $neu = $score['neutral'];

        if ($pos >= $neg && $pos >= $neu) {
            return 'pos';
        }
        if ($neg > $pos && $neg >= $neu) {
            return 'neg';
        }
        return 'neutral';
    }

    /**
     * 分析済みコメントから集計サマリーを生成する
     *
     * @param  array<array{sentiment:string, positive_score:float, negative_score:float, neutral_score:float}> $analyzed
     * @return array{positive_count:int, negative_count:int, neutral_count:int, total_count:int,
     *               positive_ratio:float, negative_ratio:float, neutral_ratio:float,
     *               avg_positive_score:float, avg_negative_score:float, avg_neutral_score:float}
     */
    public static function summarize(array $analyzed): array
    {
        $counts    = ['pos' => 0, 'neg' => 0, 'neutral' => 0];
        $scoreSum  = ['positive' => 0.0, 'negative' => 0.0, 'neutral' => 0.0];
        $total     = count($analyzed);

        foreach ($analyzed as $c) {
            $counts[$c['sentiment']]++;
            $scoreSum['positive'] += $c['positive_score'];
            $scoreSum['negative'] += $c['negative_score'];
            $scoreSum['neutral']  += $c['neutral_score'];
        }

        return [
            'positive_count'     => $counts['pos'],
            'negative_count'     => $counts['neg'],
            'neutral_count'      => $counts['neutral'],
            'total_count'        => $total,
            'positive_ratio'     => $total > 0 ? round($counts['pos']   / $total, 4) : 0.0,
            'negative_ratio'     => $total > 0 ? round($counts['neg']   / $total, 4) : 0.0,
            'neutral_ratio'      => $total > 0 ? round($counts['neutral'] / $total, 4) : 0.0,
            'avg_positive_score' => $total > 0 ? round($scoreSum['positive'] / $total, 4) : 0.0,
            'avg_negative_score' => $total > 0 ? round($scoreSum['negative'] / $total, 4) : 0.0,
            'avg_neutral_score'  => $total > 0 ? round($scoreSum['neutral']  / $total, 4) : 0.0,
        ];
    }
}
