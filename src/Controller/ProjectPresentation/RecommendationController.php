<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Entity\PresentationNeighbor;
use App\Service\AI\PresentationEmbeddingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Vich\UploaderBundle\Templating\Helper\UploaderHelper;

final class RecommendationController extends AbstractController
{
    #[Route('/project/{stringId}/recommendations', name: 'pp_recommendations', methods: ['GET'])]
    #[IsGranted('view', subject: 'presentation')]
    public function recommendations(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
        PresentationEmbeddingService $embeddingService,
        UploaderHelper $uploaderHelper,
    ): JsonResponse {
        $limit = (int) $request->query->get('limit', 8);
        $limit = max(1, min($limit, 20));

        $model = $embeddingService->getModel();

        $qb = $em->createQueryBuilder()
            ->select('DISTINCT n', 'neighbor', 'category')
            ->from(PresentationNeighbor::class, 'n')
            ->join('n.neighbor', 'neighbor')
            ->leftJoin('neighbor.categories', 'category')
            ->where('n.presentation = :presentation')
            ->andWhere('n.model = :model')
            ->andWhere('neighbor.isPublished = true')
            ->andWhere('(neighbor.isDeleted IS NULL OR neighbor.isDeleted = false)')
            ->orderBy('n.rank', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('presentation', $presentation)
            ->setParameter('model', $model);

        /** @var PresentationNeighbor[] $neighbors */
        $neighbors = $qb->getQuery()->getResult();

        $payload = array_map(function (PresentationNeighbor $neighbor) use ($uploaderHelper) {
            $pp = $neighbor->getNeighbor();
            $thumbnail = null;
            if ($pp->getExtra()?->getCacheThumbnailUrl()) {
                $thumbnail = $pp->getExtra()->getCacheThumbnailUrl();
            } elseif ($pp->getLogo()) {
                $thumbnail = $uploaderHelper->asset($pp, 'logoFile');
            }

            $categories = [];
            foreach ($pp->getCategories() as $category) {
                $categories[] = [
                    'uniqueName' => $category->getUniqueName(),
                    'label' => $category->getLabel() ?? $category->getUniqueName(),
                ];
            }

            return [
                'id' => $pp->getId(),
                'stringId' => $pp->getStringId(),
                'title' => $pp->getTitle(),
                'goal' => $pp->getGoal(),
                'score' => $pp->getScore(),
                'thumbnail' => $thumbnail,
                'categories' => $categories,
                'similarity' => $neighbor->getScore(),
                'rank' => $neighbor->getRank(),
                'url' => $pp->getStringId()
                    ? $this->generateUrl('edit_show_project_presentation', ['stringId' => $pp->getStringId()])
                    : null,
            ];
        }, $neighbors);

        return $this->json([
            'presentationId' => $presentation->getId(),
            'count' => count($payload),
            'limit' => $limit,
            'model' => $model,
            'results' => $payload,
        ]);
    }
}
