<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class ProductTourService
{
    public const TOUR_THEME_SELECTOR = 'theme-selector-intro';
    public const TOUR_PP_EDIT_INTRO = 'pp-edit-intro';

    public function __construct(
        private UserExtraService $userExtraService,
        private EntityManagerInterface $em,
    ) {
    }

    public function shouldShowAfterVisits(
        Request $request,
        ?User $user,
        string $tourId,
        string $version,
        int $minVisits = 1,
    ): bool {
        $minVisits = max(1, $minVisits);

        if ($user instanceof User && $user->getProfile() !== null) {
            $profile = $user->getProfile();
            $tours = $this->userExtraService->get($profile, 'product_tours', []);
            if (!is_array($tours)) {
                $tours = [];
            }
            if (($tours[$tourId] ?? null) === $version) {
                return false;
            }

            $visits = $this->userExtraService->get($profile, 'product_tour_visits', []);
            if (!is_array($visits)) {
                $visits = [];
            }

            $current = (int) ($visits[$tourId] ?? 0);
            if ($current >= $minVisits) {
                return true;
            }

            $current++;
            $visits[$tourId] = $current;
            $this->userExtraService->set($profile, 'product_tour_visits', $visits);
            $this->em->flush();

            return $current >= $minVisits;
        }

        $session = $request->getSession();
        $visits = $session->get('product_tour_visits', []);
        if (!is_array($visits)) {
            $visits = [];
        }

        $current = (int) ($visits[$tourId] ?? 0);
        if ($current < $minVisits) {
            $current++;
            $visits[$tourId] = $current;
            $session->set('product_tour_visits', $visits);
        }

        return $current >= $minVisits;
    }
}
