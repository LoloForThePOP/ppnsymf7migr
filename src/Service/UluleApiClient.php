<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class UluleApiClient
{
    private const BASE_URL = 'https://api.ulule.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function searchProjects(array $query): array
    {
        return $this->requestJson('/search/projects', $query);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function getProject(int $projectId, array $query = []): array
    {
        return $this->requestJson(sprintf('/projects/%d', $projectId), $query);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function getProjectImages(int $projectId, array $query = []): array
    {
        return $this->requestJson(sprintf('/projects/%d/images', $projectId), $query);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function requestJson(string $path, array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $path, [
                'query' => $query,
                'timeout' => 20,
            ]);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Erreur réseau Ulule: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            $body = $response->getContent(false);
            throw new \RuntimeException(sprintf('Erreur Ulule (%d): %s', $status, $body));
        }

        return $response->toArray(false);
    }
}
