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
use App\Service\PresentationEventLogger;
use App\Service\ProductTourService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Entity\PresentationEvent;

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
        ProductTourService $productTourService,
        PresentationEventLogger $eventLogger,
    ): Response
    {
        $showThemeSelectorTour = $productTourService->shouldShowAfterVisits(
            $request,
            $this->getUser(),
            ProductTourService::TOUR_THEME_SELECTOR,
            'v1',
            2,
        );

        if ($this->isGranted('edit', $presentation)) {
            $showPPEditIntroTour = $productTourService->shouldShowAfterVisits(
                $request,
                $this->getUser(),
                ProductTourService::TOUR_PP_EDIT_INTRO,
                'v1',
                1,
            );

            return $this->render(
                'project_presentation/edit_show/origin.html.twig',
                $this->buildEditShowContext($presentation, [
                    'showThemeSelectorTour' => $showThemeSelectorTour,
                    'showPPEditIntroTour' => $showPPEditIntroTour,
                ])
            );
        }

        // Count a view once per session
        $session = $request->getSession();
        $viewed = $session->get('pp_viewed_ids', []);
        $id = $presentation->getId();
        $needsFlush = false;
        if ($id !== null && !in_array($id, $viewed, true)) {
            $presentation->getExtra()->incrementViews();
            $eventLogger->log($presentation, PresentationEvent::TYPE_VIEW);
            $needsFlush = true;
            $viewed[] = $id;
            $session->set('pp_viewed_ids', $viewed);
        }
        if ($needsFlush) {
            $em->flush();
        }

        return $this->render('project_presentation/edit_show/origin.html.twig', [
            'presentation' => $presentation,
            'userPresenter' => false, //flaging whether user can edit presentation
            'userAdmin' => $this->isGranted('ROLE_ADMIN'), //flagging whether user is an admin
            'showThemeSelectorTour' => $showThemeSelectorTour,
        ]);

    }


}
