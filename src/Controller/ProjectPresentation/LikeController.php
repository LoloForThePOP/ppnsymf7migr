<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Like;
use App\Entity\PPBase;
use App\Repository\LikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LikeController extends AbstractController
{
    /**
     * Allow the user to like or unlike a project presentation (AJAX).
     */
    #[IsGranted('ROLE_USER')]
    #[Route('/project/{stringId}/like', name: 'ajax_like_pp', methods: ['POST'])]
    public function ajaxLikePP(
        Request $request,
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        EntityManagerInterface $manager,
        LikeRepository $likeRepo
    ): JsonResponse {

        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'error' => 'Authentication required',
            ], Response::HTTP_FORBIDDEN);
        }

        // CSRF token validation
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('like' . $presentation->getStringId(), $submittedToken)) {
            return new JsonResponse([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check like status
        $existingLike = $likeRepo->findOneBy([
            'projectPresentation' => $presentation,
            'user' => $user,
        ]);

        if ($existingLike) {
            // Unlike
            $manager->remove($existingLike);
            $manager->flush();

            return new JsonResponse([
                'code' => 200,
                'status' => 'success',
                'action' => 'removed',
                "likesCount" => $likeRepo->count(["projectPresentation" => $presentation]),
                'message' => 'You have unliked this presentation.',
            ]);
        }

        // Like
        $like = (new Like())
            ->setUser($user)
            ->setProjectPresentation($presentation);

        $manager->persist($like);
        $manager->flush();

        return new JsonResponse([
            'code' => 200,
            'status' => 'success',
            'action' => 'created',
            "likesCount" => $likeRepo->count(["projectPresentation" => $presentation]),
            'message' => 'You are now liking this presentation.',
        ]);
    }
}
