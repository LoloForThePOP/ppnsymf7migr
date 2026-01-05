<?php

namespace App\Controller\Admin;

use App\Repository\PPBaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PresentationsReviewController extends AbstractController
{
    #[Route('/admin/presentations/a-verifier', name: 'admin_presentations_to_review', methods: ['GET'])]
    public function __invoke(PPBaseRepository $repository): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $presentations = $repository->createQueryBuilder('p')
            ->andWhere('p.isAdminValidated = :validated')
            ->andWhere('p.isDeleted IS NULL OR p.isDeleted = :notDeleted')
            ->setParameter('validated', false)
            ->setParameter('notDeleted', false)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        return $this->render('admin/presentations_to_review.html.twig', [
            'presentations' => $presentations,
        ]);
    }
}
