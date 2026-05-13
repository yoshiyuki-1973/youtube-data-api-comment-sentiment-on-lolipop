<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GeminiClientTest extends TestCase
{
    public function testConstructsWithApiKey(): void
    {
        $client = new GeminiClient('test-key');
        $this->assertInstanceOf(GeminiClient::class, $client);
    }

    public function testConstructsWithCustomModel(): void
    {
        $client = new GeminiClient('test-key', 'gemini-2.5-pro');
        $this->assertInstanceOf(GeminiClient::class, $client);
    }

    public function testAnalyzeSentimentBatchReturnsEmptyForEmptyInput(): void
    {
        $client = new GeminiClient('dummy-key-no-call');
        $result = $client->analyzeSentimentBatch([]);
        $this->assertSame([], $result);
    }
}
