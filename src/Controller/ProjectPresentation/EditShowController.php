<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\News;
use App\Entity\PPBase;
use App\Form\NewsType;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ProjectPresentation\LogoType;
use App\Form\ProjectPresentation\WebsiteType;
use Symfony\Component\HttpFoundation\Request;
use App\Form\ProjectPresentation\DocumentType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ProjectPresentation\ImageSlideType;
use App\Form\ProjectPresentation\VideoSlideType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use App\Form\ProjectPresentation\BusinessCardType;
use App\Form\ProjectPresentation\QuestionAnswerType;
use App\Form\ProjectPresentation\TextDescriptionType;
use App\Form\ProjectPresentation\CategoriesKeywordsType;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class EditShowController extends AbstractController
{
    use EditShowContextTrait;

    #[Route(
        '/{stringId}',
        name: 'edit_show_project_presentation',
        priority: -1
    )]
    #[IsGranted('view', subject: 'presentation')]
    public function editShow(
        #[MapEntity(mapping: ['stringId' => 'stringId'])] PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
    ): Response
    {

        if ($this->isGranted('edit', $presentation)) {
            return $this->render(
                'project_presentation/edit_show/origin.html.twig',
                $this->buildEditShowContext($presentation)
            );
        }

        // Count a view once per session
        $session = $request->getSession();
        $viewed = $session->get('pp_viewed_ids', []);
        $id = $presentation->getId();
        if ($id !== null && !in_array($id, $viewed, true)) {
            $presentation->getExtra()->incrementViews();
            $em->flush();
            $viewed[] = $id;
            $session->set('pp_viewed_ids', $viewed);
        }

        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
            'userPresenter' => false, //flaging whether user can edit presentation
            'userAdmin' => $this->isGranted('ROLE_ADMIN'), //flagging whether user is an admin
        ]);

    }


}
