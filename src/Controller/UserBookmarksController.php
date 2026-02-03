<?php

namespace App\Controller;

use App\Repository\BookmarkRepository;
use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class UserBookmarksController extends AbstractController
{
    #[Route('/my-bookmarks', name: 'user_bookmarks_index', methods: ['GET'])]
    public function index(
        BookmarkRepository $bookmarkRepository,
        PPBaseRepository $ppBaseRepository,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $bookmarks = $bookmarkRepository->findLatestForUser($user, 300);
        $presentations = [];

        foreach ($bookmarks as $bookmark) {
            $presentation = $bookmark->getProjectPresentation();
            if ($presentation === null) {
                continue;
            }
            $presentations[] = $presentation;
        }

        $presentationStats = $ppBaseRepository->getEngagementCountsForIds(
            array_map(static fn ($pp) => $pp->getId(), $presentations)
        );

        return $this->render('user/bookmarks/index.html.twig', [
            'presentations' => $presentations,
            'presentationStats' => $presentationStats,
        ]);
    }
}
