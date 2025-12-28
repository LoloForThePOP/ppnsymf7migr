<?php

namespace App\Tests\Unit;

use App\Service\UrlSafetyChecker;
use PHPUnit\Framework\TestCase;

final class UrlSafetyCheckerTest extends TestCase
{
    public function testRejectsInvalidSchemes(): void
    {
        $checker = new UrlSafetyChecker();

        self::assertFalse($checker->isAllowed('ftp://example.com'));
        self::assertFalse($checker->isAllowed('file:///etc/passwd'));
        self::assertFalse($checker->isAllowed('data:text/plain,hello'));
    }

    public function testRejectsCredentialsInUrl(): void
    {
        $checker = new UrlSafetyChecker();

        self::assertFalse($checker->isAllowed('http://user:pass@example.com'));
    }

    public function testRejectsLocalAndPrivateHosts(): void
    {
        $checker = new UrlSafetyChecker();

        $blocked = [
            'http://localhost',
            'http://example.local',
            'http://service.internal',
            'http://127.0.0.1',
            'http://10.0.0.1',
            'http://192.168.0.1',
            'http://169.254.1.1',
        ];

        foreach ($blocked as $url) {
            self::assertFalse($checker->isAllowed($url), $url . ' should be blocked');
        }
    }

    public function testAllowsPublicIp(): void
    {
        $checker = new UrlSafetyChecker();

        self::assertTrue($checker->isAllowed('http://8.8.8.8'));
    }
}
