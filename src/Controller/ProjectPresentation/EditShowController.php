<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\PPBase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;




class EditShowController extends AbstractController
{
    #[Route(
        '/{stringId}/',
        name: 'edit_show_project_presentation',
        priority: -1
    )]
    #[IsGranted('view', subject: 'presentation')]
    public function EditShow(#[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation): Response
    {
        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
        ]);
    }
}