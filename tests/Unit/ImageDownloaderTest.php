<?php

namespace App\Tests\Unit;

use App\Service\ImageDownloader;
use App\Service\UrlSafetyChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ImageDownloaderTest extends TestCase
{
    public function testReturnsNullWhenUrlNotAllowed(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::never())->method('request');

        $downloader = new ImageDownloader($httpClient, new UrlSafetyChecker());

        self::assertNull($downloader->download('http://localhost/image.jpg'));
    }

    public function testReturnsNullForNonImageContentType(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->with(false)->willReturn([
            'content-type' => ['text/html'],
        ]);
        $response->method('getContent')->with(false)->willReturn('<html></html>');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with('GET', 'http://8.8.8.8/file', self::anything())
            ->willReturn($response);

        $downloader = new ImageDownloader($httpClient, new UrlSafetyChecker());

        self::assertNull($downloader->download('http://8.8.8.8/file'));
    }

    public function testReturnsNullWhenContentLengthTooLarge(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getHeaders')->with(false)->willReturn([
            'content-type' => ['image/png'],
            'content-length' => ['6000001'],
        ]);
        $response->expects(self::never())->method('getContent');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with('GET', 'http://8.8.8.8/large.png', self::anything())
            ->willReturn($response);

        $downloader = new ImageDownloader($httpClient, new UrlSafetyChecker());

        self::assertNull($downloader->download('http://8.8.8.8/large.png'));
    }
}
