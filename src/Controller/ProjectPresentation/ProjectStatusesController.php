<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Enum\ProjectStatuses;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProjectStatusesController extends AbstractController
{
    #[Route('/project/{stringId}/statuses/update', name: 'ajax_project_statuses_update', methods: ['POST'])]
    #[IsGranted('edit', 'project')] // security voter protection
    public function update(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $project,
        Request $request,
        EntityManagerInterface $em
    ): JsonResponse {

        // Parse JSON payload from JS

        $data = json_decode($request->getContent(), true);

        if (!isset($data['statuses']) || !is_array($data['statuses'])) {
            return new JsonResponse(['error' => 'Invalid data format'], 400);
        }

        $remarks = $data['remarks'] ?? null;
        $project->setStatusRemarks($remarks);

        // Filter only valid statuses
        $validStatuses = array_filter($data['statuses'], fn ($s) => ProjectStatuses::get($s) !== null);

        // Update the project entity
        $project->setStatuses($validStatuses);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'enumStatuses' => $project->getStatuses(),
            'statusRemarks' => $project->getStatusRemarks(),
        ]);
    }

}
