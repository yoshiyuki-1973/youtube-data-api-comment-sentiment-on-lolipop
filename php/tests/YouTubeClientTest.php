<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class YouTubeClientTest extends TestCase
{
    // extractVideoId のテスト（外部API不要）

    public function testExtractsVideoIdFromWatchUrl(): void
    {
        $id = YouTubeClient::extractVideoId('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $id);
    }

    public function testExtractsVideoIdFromShortUrl(): void
    {
        $id = YouTubeClient::extractVideoId('https://youtu.be/dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $id);
    }

    public function testExtractsVideoIdFromShortsUrl(): void
    {
        $id = YouTubeClient::extractVideoId('https://www.youtube.com/shorts/dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $id);
    }

    public function testExtractsVideoIdFromEmbedUrl(): void
    {
        $id = YouTubeClient::extractVideoId('https://www.youtube.com/embed/dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $id);
    }

    public function testAcceptsRawVideoId(): void
    {
        $id = YouTubeClient::extractVideoId('dQw4w9WgXcQ');
        $this->assertSame('dQw4w9WgXcQ', $id);
    }

    public function testTrimsWhitespace(): void
    {
        $id = YouTubeClient::extractVideoId('  dQw4w9WgXcQ  ');
        $this->assertSame('dQw4w9WgXcQ', $id);
    }

    public function testThrowsOnInvalidInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        YouTubeClient::extractVideoId('not-a-valid-url');
    }

    public function testThrowsOnEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        YouTubeClient::extractVideoId('');
    }
}
