<?php

namespace App\Tests\Unit;

use App\Service\ImageCandidateFetcher;
use App\Service\UrlSafetyChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ImageCandidateFetcherTest extends TestCase
{
    public function testFetchReturnsEmptyWhenUrlBlocked(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects(self::never())->method('request');

        $fetcher = new ImageCandidateFetcher($httpClient, new UrlSafetyChecker());

        self::assertSame([], $fetcher->fetch('http://localhost'));
    }

    public function testExtractFromHtmlResolvesRelativeUrls(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $fetcher = new ImageCandidateFetcher($httpClient, new UrlSafetyChecker());

        $html = <<<HTML
<html>
  <head>
    <meta property="og:image" content="https://cdn.example.com/a.jpg">
  </head>
  <body>
    <img src="/img/one.png">
    <img src="sub/two.jpg">
    <img src="https://cdn.example.com/a.jpg">
  </body>
</html>
HTML;

        $results = $fetcher->extractFromHtml($html, 'https://example.com/path/page.html');

        self::assertSame([
            ['url' => 'https://cdn.example.com/a.jpg', 'source' => 'meta'],
            ['url' => 'https://example.com/img/one.png', 'source' => 'img'],
            ['url' => 'https://example.com/path/sub/two.jpg', 'source' => 'img'],
        ], $results);
    }
}
