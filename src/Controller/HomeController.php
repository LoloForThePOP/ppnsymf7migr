<?php

namespace App\Controller;

use App\Entity\PPBase;
use App\Entity\News;
use App\Entity\User;
use App\Form\NewsType;
use App\Repository\ArticleRepository;
use App\Repository\CommentRepository;
use App\Repository\FollowRepository;
use App\Repository\PPBaseRepository;
use App\Service\Recommendation\RecommendationEngine;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Homepage route
final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(
        PPBaseRepository $ppBaseRepository,
        ArticleRepository $articleRepository,
        CommentRepository $commentRepository,
        FollowRepository $followRepository,
        RecommendationEngine $recommendationEngine,
    ): Response {
        $presentations = $ppBaseRepository->findLatestPublished(50);
        $presentationIds = array_map(static fn (PPBase $pp) => $pp->getId(), $presentations);
        $presentationStats = $ppBaseRepository->getEngagementCountsForIds($presentationIds);
        $articles = $articleRepository->findBy([], ['createdAt' => 'DESC', 'id' => 'DESC']);

        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        $creatorPresentations = [];
        $creatorPresentationStats = [];
        $continuePresentation = null;
        $recommendedPresentations = [];
        $recommendedPresentationStats = [];
        $followedPresentations = [];
        $followedPresentationStats = [];
        $recentComments = [];
        $addNewsForm = null;

        if ($user) {
            $creatorPresentations = $ppBaseRepository->findLatestByCreator($user, 6);
            $creatorPresentationStats = $ppBaseRepository->getEngagementCountsForIds(
                array_map(static fn (PPBase $pp) => $pp->getId(), $creatorPresentations)
            );
            $continuePresentation = $creatorPresentations[0] ?? null;

            $recentComments = $commentRepository->findLatestForCreatorProjects($user, 5);

            $followedPresentations = $followRepository->findLatestFollowedPresentations($user, 6);
            $followedPresentationStats = $ppBaseRepository->getEngagementCountsForIds(
                array_map(static fn (PPBase $pp) => $pp->getId(), $followedPresentations)
            );

            if ($continuePresentation && $this->isGranted('edit', $continuePresentation)) {
                $addNewsForm = $this->createForm(
                    NewsType::class,
                    new News(),
                    [
                        'action' => $this->generateUrl('pp_create_news', [
                            'stringId' => $continuePresentation->getStringId(),
                        ]),
                        'method' => 'POST',
                    ]
                );
                $addNewsForm->get('presentationId')->setData($continuePresentation->getId());
            }
        }

        $excludeRecommendationIds = array_map(
            static fn (PPBase $pp): ?int => $pp->getId(),
            $followedPresentations
        );
        $excludeRecommendationIds = array_values(
            array_filter(
                $excludeRecommendationIds,
                static fn (?int $id): bool => $id !== null
            )
        );
        $viewer = $user instanceof User ? $user : null;
        $recommendationResult = $recommendationEngine->recommendHomepage(
            $viewer,
            6,
            $excludeRecommendationIds
        );
        $recommendedPresentations = $recommendationResult->getItems();
        $recommendedPresentationStats = $recommendationResult->getStats();

        return $this->render('home/homepage.html.twig', [
            'presentations' => $presentations,
            'presentationStats' => $presentationStats,
            'articles' => $articles,
            'creatorPresentations' => $creatorPresentations,
            'creatorPresentationStats' => $creatorPresentationStats,
            'continuePresentation' => $continuePresentation,
            'recommendedPresentations' => $recommendedPresentations,
            'recommendedPresentationStats' => $recommendedPresentationStats,
            'followedPresentations' => $followedPresentations,
            'followedPresentationStats' => $followedPresentationStats,
            'recentComments' => $recentComments,
            'addNewsForm' => $addNewsForm ? $addNewsForm->createView() : null,
        ]);
    }
}
