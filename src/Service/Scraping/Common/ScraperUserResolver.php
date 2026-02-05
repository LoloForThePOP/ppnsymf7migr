<?php

namespace App\Service\Scraping\Common;

use App\Entity\User;
use App\Repository\UserRepository;

/**
 * Resolves the dedicated scraper/bot user for attribution.
 *
 * Rationale:
 * - Access control is handled by ScraperAccessVoter (who can run the tools).
 * - Attribution should remain stable and not depend on the logged-in operator
 *   (e.g. SUPER_ADMIN triggering a run should not become the creator).
 * - CLI commands have no authenticated user, so we need a deterministic resolver.
 *
 * The resolver returns a user only when exactly one account matches the role.
 */
class ScraperUserResolver
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly string $scraperUserRole,
    ) {
    }

    public function resolve(?string $roleOverride = null): ?User
    {
        $role = $roleOverride ?: $this->scraperUserRole;
        $matches = [];

        foreach ($this->userRepository->findAll() as $user) {
            if (in_array($role, $user->getRoles(), true)) {
                $matches[] = $user;
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        return null;
    }

    public function getRole(?string $roleOverride = null): string
    {
        return $roleOverride ?: $this->scraperUserRole;
    }
}
