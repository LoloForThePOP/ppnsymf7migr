<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Follow;
use App\Entity\PPBase;
use App\Entity\User;
use App\Repository\FollowRepository;
use App\Service\AssessPPScoreService;
use App\Service\Recommendation\UserPreferenceUpdater;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FollowController extends AbstractController
{
    /**
     * Allow the user to follow or unfollow a presentation (AJAX).
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/project/{stringId}/follow', name: 'ajax_follow_pp', methods: ['POST'])]
    public function ajaxFollowPP(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        EntityManagerInterface $manager,
        FollowRepository $followRepo,
        AssessPPScoreService $scoreService,
        UserPreferenceUpdater $userPreferenceUpdater,
        #[Autowire(service: 'limiter.follow_toggle_user')] RateLimiterFactory $followLimiter,
    ): JsonResponse {

        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'error' => 'Authentication required',
            ], Response::HTTP_FORBIDDEN);
        }

        // CSRF token validation
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('follow' . $presentation->getStringId(), $submittedToken)) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $limit = $followLimiter->create($user->getUserIdentifier())->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse([
                'error' => 'Trop de requêtes. Veuillez réessayer plus tard.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Check follow status
        $existingFollow = $followRepo->findOneBy([
            'projectPresentation' => $presentation,
            'user' => $user,
        ]);

        if ($existingFollow) {
            // Unfollow
            $presentation->removeFollower($existingFollow);
            $manager->remove($existingFollow);
            $scoreService->scoreUpdate($presentation);
            $manager->flush();
            if ($user instanceof User) {
                $userPreferenceUpdater->recomputeForUser($user, true);
            }

            return new JsonResponse([
                'code' => 200,
                'status' => 'success',
                'action' => 'removed',
                'message' => 'You have unfollowed this presentation.',
            ]);
        }

        // Follow
        $follow = (new Follow())
            ->setUser($user);

        $presentation->addFollower($follow);
        $manager->persist($follow);
        $scoreService->scoreUpdate($presentation);
        $manager->flush();
        if ($user instanceof User) {
            $userPreferenceUpdater->recomputeForUser($user, true);
        }

        return new JsonResponse([
            'code' => 200,
            'status' => 'success',
            'action' => 'created',
            'message' => 'You are now following this presentation.',
        ]);
    }
}
