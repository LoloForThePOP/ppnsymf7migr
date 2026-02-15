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
        $guard = $this->guardBookmarkMutation($request, $presentation, $bookmarkLimiter);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }
        $user = $guard;

        $existingBookmark = $bookmarkRepo->findOneBy([
            'projectPresentation' => $presentation,
            'user' => $user,
        ]);

        if ($existingBookmark) {
            $this->removeBookmark($presentation, $existingBookmark, $manager, $user, $userPreferenceUpdater);

            return $this->successResponse($bookmarkRepo, $presentation, 'removed', 'Bookmark removed.');
        }

        $this->addBookmark($presentation, $manager, $user, $userPreferenceUpdater);

        return $this->successResponse($bookmarkRepo, $presentation, 'created', 'Bookmarked.');
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/project/{stringId}/bookmark/add', name: 'ajax_bookmark_pp_add', methods: ['POST'])]
    public function ajaxAddBookmarkPP(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        EntityManagerInterface $manager,
        BookmarkRepository $bookmarkRepo,
        UserPreferenceUpdater $userPreferenceUpdater,
        #[Autowire(service: 'limiter.bookmark_toggle_user')] RateLimiterFactory $bookmarkLimiter,
    ): JsonResponse {
        $guard = $this->guardBookmarkMutation($request, $presentation, $bookmarkLimiter);
        if ($guard instanceof JsonResponse) {
            return $guard;
        }
        $user = $guard;

        $existingBookmark = $bookmarkRepo->findOneBy([
            'projectPresentation' => $presentation,
            'user' => $user,
        ]);
        if ($existingBookmark) {
            return $this->successResponse($bookmarkRepo, $presentation, 'already_exists', 'Already bookmarked.');
        }

        $this->addBookmark($presentation, $manager, $user, $userPreferenceUpdater);

        return $this->successResponse($bookmarkRepo, $presentation, 'created', 'Bookmarked.');
    }

    private function guardBookmarkMutation(
        Request $request,
        PPBase $presentation,
        RateLimiterFactory $bookmarkLimiter,
    ): User|JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
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

        return $user;
    }

    private function addBookmark(
        PPBase $presentation,
        EntityManagerInterface $manager,
        User $user,
        UserPreferenceUpdater $userPreferenceUpdater,
    ): void {
        $bookmark = (new Bookmark())->setUser($user);
        $presentation->addBookmark($bookmark);
        $manager->persist($bookmark);
        $manager->flush();
        $userPreferenceUpdater->recomputeForUser($user, true);
    }

    private function removeBookmark(
        PPBase $presentation,
        Bookmark $bookmark,
        EntityManagerInterface $manager,
        User $user,
        UserPreferenceUpdater $userPreferenceUpdater,
    ): void {
        $presentation->removeBookmark($bookmark);
        $manager->remove($bookmark);
        $manager->flush();
        $userPreferenceUpdater->recomputeForUser($user, true);
    }

    private function successResponse(
        BookmarkRepository $bookmarkRepo,
        PPBase $presentation,
        string $action,
        string $message,
    ): JsonResponse {
        return new JsonResponse([
            'code' => 200,
            'status' => 'success',
            'action' => $action,
            'bookmarksCount' => $bookmarkRepo->count(['projectPresentation' => $presentation]),
            'message' => $message,
        ]);
    }
}
