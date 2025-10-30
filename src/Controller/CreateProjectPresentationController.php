<?php

namespace App\Controller;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Service\SlugService;
use App\Service\AssessQuality;
use App\Service\CacheThumbnail;
use App\Form\PresentationHelperType;
use App\Service\ImageResizerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CreateProjectPresentationController extends AbstractController
{/* 
    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugger,
        private CacheThumbnail $cacheThumbnail,
        private ImageResizerService $imageResizer,
        private AssessQuality $assessQuality,
    ) {}

    #[Route(
        '/step-by-step-project-presentation/{position?0}/{stringId?}/{repeatInstance}',
        name: 'project_presentation_helper',
        defaults: ['repeatInstance' => 'false'],
        methods: ['GET', 'POST']
    )]
    public function origin(
        Request $request,
        ?string $stringId = null,
        int $position = 0,
        string $repeatInstance = 'false'
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // ───────────── Retrieve or create presentation ─────────────
        $presentation = $stringId
            ? $this->em->getRepository(PPBase::class)->findOneBy(['stringId' => $stringId])
            : new PPBase();

        if ($stringId && !$presentation) {
            throw $this->createNotFoundException("Présentation non trouvée.");
        }

        if ($stringId) {
            $this->denyAccessUnlessGranted('edit', $presentation);
        }

        // ───────────── Build and handle form ─────────────
        $form = $this->createForm(PresentationHelperType::class, $presentation);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project_presentation/create/origin.html.twig', [
                'form' => $form->createView(),
                'stringId' => $stringId,
                'position' => $position,
            ]);
        }

        // ───────────── Case 1: new presentation (first step) ─────────────
        if ($stringId === null) {
            $goal = $form->get('goal')->getData();
            $presentation->setGoal($goal);
            $presentation->setCreator($this->getUser());

            $this->em->persist($presentation);
            $this->em->flush();

            return $this->redirectToRoute('project_presentation_helper', [
                'stringId' => $presentation->getStringId(),
                'position' => 1,
                'repeatInstance' => $repeatInstance,
            ]);
        }

        // ───────────── Case 2: updating an existing presentation ─────────────
        $nextPosition = $form->get('nextPosition')->getData();
        $helperType = $form->get('helperItemType')->getData();

        // Final step: all done
        if ($nextPosition === null) {
            $this->assessQuality->assessQuality($presentation);

            // (Optional future improvement) mark presentation as completed for later cleanup
            $presentation->markAsCompleted(); // if such a method exists

            $this->addFlash('success fs-4', <<<HTML
                ✅ Votre page de présentation est prête.<br>
                Apportez-lui toutes les modifications que vous désirez.<br>
                🙋 Si vous avez besoin d'aide, utilisez le bouton d'aide rapide en bas de page.
            HTML);

            return $this->redirectToRoute('show_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        // ───────────── Handle helper type logic ─────────────
        switch ($helperType) {
            case 'title':
                $title = $form->get('title')->getData();
                if (!empty($title)) {
                    $presentation->setTitle($title);

                    // Generate slug
                    $slug = $this->slugger->slugInput($title);

                    // Ensure uniqueness
                    $twin = $this->em->getRepository(PPBase::class)->findOneBy(['stringId' => $slug]);
                    if ($twin) {
                        $slug .= '-' . $presentation->getId();
                    }

                    $presentation->setStringId($slug);
                    $this->em->flush();
                }
                break;

            case 'imageSlide':
                /** @var Slide|null $slide */
               /* $slide = $form->get('imageSlide')->getData();

                if ($slide) {
                    $slide->setType('image');
                    $presentation->addSlide($slide);

                    $this->em->persist($slide);
                    $this->em->flush();

                    // Post-processing
                    $this->imageResizer->edit($slide);
                    $this->cacheThumbnail->cacheThumbnail($presentation);
                }
                break;

            case 'textDescription':
                $text = nl2br($form->get('textDescription')->getData() ?? '');
                $presentation->setTextDescription($text);
                $this->em->flush();
                break;
        }

        // ───────────── Go to next step ─────────────
        return $this->redirectToRoute('project_presentation_helper', [
            'stringId' => $presentation->getStringId(),
            'position' => $nextPosition,
            'repeatInstance' => $repeatInstance,
        ]);
    }*/
} 
