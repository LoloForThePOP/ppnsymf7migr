<?php

namespace App\Controller;

use App\Entity\PPBase;
use App\Entity\News;
use App\Entity\User;
use App\Form\NewsType;
use App\Repository\CommentRepository;
use App\Repository\PPBaseRepository;
use App\Service\HomeFeed\HomeFeedAssembler;
use App\Service\HomeFeed\HomeFeedContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

// Homepage route
final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function index(
        Request $request,
        PPBaseRepository $ppBaseRepository,
        CommentRepository $commentRepository,
        HomeFeedAssembler $homeFeedAssembler,
        #[Autowire('%app.home_feed.cards_per_block%')] int $cardsPerBlock,
        #[Autowire('%app.home_feed.max_blocks.logged%')] int $maxBlocksLogged,
        #[Autowire('%app.home_feed.max_blocks.anon%')] int $maxBlocksAnon,
        #[Autowire('%app.home_feed.creator_cap.enabled%')] bool $creatorCapEnabled,
        #[Autowire('%app.home_feed.creator_cap.per_block%')] int $creatorCapPerBlock,
    ): Response {
        /** @var \App\Entity\User|null $user */
        $user = $this->getUser();
        $continuePresentation = null;
        $recentComments = [];
        $addNewsForm = null;
        $viewer = $user instanceof User ? $user : null;

        if ($user) {
            $creatorPresentations = $ppBaseRepository->findLatestByCreator($user, 6);
            $continuePresentation = $creatorPresentations[0] ?? null;

            $recentComments = $commentRepository->findLatestForCreatorProjects($user, 5);

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

        $anonCategoryHints = $viewer ? [] : $this->extractAnonCategoryHints($request);
        $locationHint = $this->extractHomepageLocationHint($request);
        $feedContext = new HomeFeedContext(
            viewer: $viewer,
            cardsPerBlock: $cardsPerBlock,
            maxBlocks: $viewer ? $maxBlocksLogged : $maxBlocksAnon,
            anonCategoryHints: $anonCategoryHints,
            locationHint: $locationHint,
            creatorCapEnabled: $creatorCapEnabled,
            creatorCapPerBlock: $creatorCapPerBlock
        );
        $feedBlocks = $homeFeedAssembler->build($feedContext);

        return $this->render('home/homepage.html.twig', [
            'continuePresentation' => $continuePresentation,
            'recentComments' => $recentComments,
            'feedBlocks' => $feedBlocks,
            'hasHomepageLocationHint' => $locationHint !== null,
            'addNewsForm' => $addNewsForm ? $addNewsForm->createView() : null,
        ]);
    }

    /**
     * @return string[]
     */
    private function extractAnonCategoryHints(Request $request): array
    {
        $raw = (string) $request->cookies->get('anon_pref_categories', '');
        if ($raw === '') {
            return [];
        }

        $parts = preg_split('/[,|]+/', $raw) ?: [];
        $hints = [];

        foreach ($parts as $part) {
            $token = strtolower(trim($part));
            if ($token === '' || !preg_match('/^[a-z0-9_-]{1,40}$/', $token)) {
                continue;
            }

            $hints[$token] = true;
        }

        return array_slice(array_keys($hints), 0, 8);
    }

    /**
     * @return array{lat: float, lng: float, radius: float}|null
     */
    private function extractHomepageLocationHint(Request $request): ?array
    {
        $raw = trim((string) $request->cookies->get('search_pref_location', ''));
        if ($raw === '') {
            return null;
        }

        $decoded = rawurldecode($raw);
        $parts = preg_split('/[|,]+/', $decoded) ?: [];
        if (count($parts) < 2) {
            return null;
        }

        $lat = filter_var($parts[0], FILTER_VALIDATE_FLOAT);
        $lng = filter_var($parts[1], FILTER_VALIDATE_FLOAT);
        $radius = count($parts) >= 3
            ? filter_var($parts[2], FILTER_VALIDATE_FLOAT)
            : 10.0;

        if ($lat === false || $lng === false) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            return null;
        }

        if ($radius === false) {
            $radius = 10.0;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'radius' => max(1.0, min(200.0, (float) $radius)),
        ];
    }
}
