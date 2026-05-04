<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class SentimentAnalyzerTest extends TestCase
{
    private GrokClient&MockObject $grokMock;
    private SentimentAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->grokMock = $this->createMock(GrokClient::class);
        $this->analyzer = new SentimentAnalyzer($this->grokMock);
    }

    public function testAnalyzeCommentsAttachesScores(): void
    {
        $comments = [
            ['text' => '最高の動画！', 'author' => 'ユーザーA', 'like_count' => 10, 'published_at' => '2024-01-01T00:00:00Z'],
            ['text' => 'つまらない',   'author' => 'ユーザーB', 'like_count' => 2,  'published_at' => '2024-01-02T00:00:00Z'],
        ];

        $this->grokMock
            ->expects($this->once())
            ->method('analyzeSentimentBatch')
            ->willReturn([
                ['positive' => 0.85, 'negative' => 0.10, 'neutral' => 0.05],
                ['positive' => 0.05, 'negative' => 0.90, 'neutral' => 0.05],
            ]);

        $result = $this->analyzer->analyzeComments($comments);

        $this->assertCount(2, $result);
        $this->assertSame('pos', $result[0]['sentiment']);
        $this->assertSame(0.85, $result[0]['positive_score']);
        $this->assertSame('neg', $result[1]['sentiment']);
        $this->assertSame(0.90, $result[1]['negative_score']);
    }

    public function testAnalyzeEmptyCommentsReturnsEmpty(): void
    {
        $this->grokMock->expects($this->never())->method('analyzeSentimentBatch');

        $result = $this->analyzer->analyzeComments([]);
        $this->assertSame([], $result);
    }

    public function testAnalyzeCommentsUsesDefaultOnMissingScore(): void
    {
        $comments = [
            ['text' => 'テスト', 'author' => 'A', 'like_count' => 0, 'published_at' => '2024-01-01T00:00:00Z'],
        ];

        // スコアが1件返ってくる想定だが0件返す
        $this->grokMock
            ->method('analyzeSentimentBatch')
            ->willReturn([]);

        $result = $this->analyzer->analyzeComments($comments);

        $this->assertCount(1, $result);
        // デフォルト値（均等配分）が設定されること
        $this->assertSame(0.33, $result[0]['positive_score']);
    }

    // --- SentimentAnalyzer::summarize のテスト ---

    public function testSummarizeCountsCorrectly(): void
    {
        $analyzed = [
            ['sentiment' => 'pos',     'positive_score' => 0.8, 'negative_score' => 0.1, 'neutral_score' => 0.1],
            ['sentiment' => 'pos',     'positive_score' => 0.7, 'negative_score' => 0.2, 'neutral_score' => 0.1],
            ['sentiment' => 'neg',     'positive_score' => 0.1, 'negative_score' => 0.8, 'neutral_score' => 0.1],
            ['sentiment' => 'neutral', 'positive_score' => 0.3, 'negative_score' => 0.3, 'neutral_score' => 0.4],
        ];

        $summary = SentimentAnalyzer::summarize($analyzed);

        $this->assertSame(2, $summary['positive_count']);
        $this->assertSame(1, $summary['negative_count']);
        $this->assertSame(1, $summary['neutral_count']);
        $this->assertSame(4, $summary['total_count']);
        $this->assertEqualsWithDelta(0.5, $summary['positive_ratio'], 0.001);
        $this->assertEqualsWithDelta(0.25, $summary['negative_ratio'], 0.001);
    }

    public function testSummarizeEmptyArray(): void
    {
        $summary = SentimentAnalyzer::summarize([]);

        $this->assertSame(0, $summary['total_count']);
        $this->assertSame(0.0, $summary['positive_ratio']);
    }
}
