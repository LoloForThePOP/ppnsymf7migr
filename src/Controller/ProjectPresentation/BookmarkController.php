<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Bookmark;
use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\BookmarkRepository;
use App\Service\Recommendation\UserPreferenceUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class BookmarkController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/project/{stringId}/bookmark', name: 'ajax_bookmark_pp', methods: ['POST'])]
    public function ajaxBookmarkPP(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        EntityManagerInterface $manager,
        BookmarkRepository $bookmarkRepo,
        UserPreferenceUpdater $userPreferenceUpdater,
        #[Autowire(service: 'limiter.bookmark_toggle_user')] RateLimiterFactory $bookmarkLimiter,
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'error' => 'Authentication required',
            ], Response::HTTP_FORBIDDEN);
        }

        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('bookmark' . $presentation->getStringId(), $submittedToken)) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $limit = $bookmarkLimiter->create($user->getUserIdentifier())->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse([
                'error' => 'Trop de requêtes. Veuillez réessayer plus tard.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $existingBookmark = $bookmarkRepo->findOneBy([
            'projectPresentation' => $presentation,
            'user' => $user,
        ]);

        if ($existingBookmark) {
            $presentation->removeBookmark($existingBookmark);
            $manager->remove($existingBookmark);
            $manager->flush();
            if ($user instanceof User) {
                $userPreferenceUpdater->recomputeForUser($user, true);
            }

            return new JsonResponse([
                'code' => 200,
                'status' => 'success',
                'action' => 'removed',
                'bookmarksCount' => $bookmarkRepo->count(['projectPresentation' => $presentation]),
                'message' => 'Bookmark removed.',
            ]);
        }

        $bookmark = (new Bookmark())->setUser($user);
        $presentation->addBookmark($bookmark);
        $manager->persist($bookmark);
        $manager->flush();
        if ($user instanceof User) {
            $userPreferenceUpdater->recomputeForUser($user, true);
        }

        return new JsonResponse([
            'code' => 200,
            'status' => 'success',
            'action' => 'created',
            'bookmarksCount' => $bookmarkRepo->count(['projectPresentation' => $presentation]),
            'message' => 'Bookmarked.',
        ]);
    }
}
