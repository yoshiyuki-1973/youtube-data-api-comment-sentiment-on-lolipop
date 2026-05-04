<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * GrokClient の parseJsonArray（private）は SentimentAnalyzerTest のモックでカバー済み。
 * ここでは extractVideoId 以外のパブリックインターフェース仕様を文書化する。
 */
class GrokClientTest extends TestCase
{
    public function testConstructsWithApiKey(): void
    {
        $client = new GrokClient('test-key');
        $this->assertInstanceOf(GrokClient::class, $client);
    }

    public function testConstructsWithCustomModel(): void
    {
        $client = new GrokClient('test-key', 'grok-beta');
        $this->assertInstanceOf(GrokClient::class, $client);
    }

    public function testAnalyzeSentimentBatchReturnsEmptyForEmptyInput(): void
    {
        // 空配列ならAPIを呼ばずに空配列を返す（外部通信なし）
        $client = new GrokClient('dummy-key-no-call');
        $result = $client->analyzeSentimentBatch([]);
        $this->assertSame([], $result);
    }
}
