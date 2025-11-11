<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use App\Enum\ProjectStatuses;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProjectStatusesController extends AbstractController
{
    #[Route('/project/{id}/statuses/update', name: 'ajax_project_statuses_update', methods: ['POST'])]
    #[IsGranted('edit', 'project')] // security voter protection
    public function update(
        PPBase $project,
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
            'statuses' => $project->getStatuses(),
            'remarks' => $project->getStatusRemarks(),
        ]);
    }

}
