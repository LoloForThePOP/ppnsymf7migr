<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait SafeRefererRedirectTrait
{
    /**
     * Redirect to the Referer only when it stays on the same origin.
     */
    private function redirectToSafeReferer(
        Request $request,
        string $fallbackRoute,
        array $fallbackRouteParameters = []
    ): Response {
        $referer = trim((string) $request->headers->get('referer', ''));

        if ($this->isSafeReferer($request, $referer)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute($fallbackRoute, $fallbackRouteParameters);
    }

    private function isSafeReferer(Request $request, string $referer): bool
    {
        if ($referer === '') {
            return false;
        }

        if (str_starts_with($referer, '/') && !str_starts_with($referer, '//')) {
            return true;
        }

        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return false;
        }

        $refererHost = strtolower((string) ($parts['host'] ?? ''));
        $refererScheme = strtolower((string) ($parts['scheme'] ?? ''));
        if ($refererHost === '' || $refererScheme === '') {
            return false;
        }

        if ($refererHost !== strtolower($request->getHost())) {
            return false;
        }

        if ($refererScheme !== strtolower($request->getScheme())) {
            return false;
        }

        if (isset($parts['port']) && (int) $parts['port'] !== $request->getPort()) {
            return false;
        }

        return true;
    }
}

