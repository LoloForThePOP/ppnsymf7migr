<?php

namespace App\Tests\Service;

use App\Service\UrlSafetyChecker;
use PHPUnit\Framework\TestCase;

final class UrlSafetyCheckerTest extends TestCase
{
    public function testRejectsUnsafeUrls(): void
    {
        $checker = new UrlSafetyChecker();

        self::assertFalse($checker->isAllowed('http://localhost'));
        self::assertFalse($checker->isAllowed('http://127.0.0.1'));
        self::assertFalse($checker->isAllowed('http://10.0.0.1'));
        self::assertFalse($checker->isAllowed('http://example.local'));
        self::assertFalse($checker->isAllowed('ftp://example.com'));
        self::assertFalse($checker->isAllowed('http://user:pass@example.com'));
        self::assertFalse($checker->isAllowed('https://192.168.0.1'));
    }

    public function testAllowsPublicIp(): void
    {
        $checker = new UrlSafetyChecker();

        self::assertTrue($checker->isAllowed('https://8.8.8.8'));
    }
}
