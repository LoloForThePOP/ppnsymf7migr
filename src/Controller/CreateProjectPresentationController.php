<?php

namespace App\Controller;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Service\SlugService;
use App\Service\ImageResizerService;
use App\Service\AssessPPScoreService;
use App\Service\CacheThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ProjectPresentation\ProjectPresentationCreationType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CreateProjectPresentationController extends AbstractController
{

    public function __construct(
        private EntityManagerInterface $em,
        private SlugService $slugger,
        private AssessPPScoreService $assessPPScore, 
        private CacheThumbnailService $cacheThumbnail,
        private ImageResizerService $imageResizer,
        
    ) {}

    #[Route(
        '/step-by-step-project-presentation/{position?0}/{stringId?}',
        name: 'project_presentation_helper',
        methods: ['GET', 'POST']
    )]
    public function origin(
        Request $request,
        ?string $stringId = null,
        int $position = 0, // Step position in the creation wizard
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Retrieve a project presentation if stringId exists otherwise create a new project presentation
        $presentation = $stringId ? $this->em->getRepository(PPBase::class)->findOneBy(['stringId' => $stringId])
            : new PPBase();

        if ($stringId && !$presentation) { //case a stringId is provided in url but no matching presentation
            throw $this->createNotFoundException("Présentation non trouvée.");
        }

        if ($stringId) { // If stringId exists we make sure user has right to edit matching presentation
            $this->denyAccessUnlessGranted('edit', $presentation);
        }

        // Create / update a Project Presentation, Form presented step by step in front-end
        $form = $this->createForm(ProjectPresentationCreationType::class, $presentation);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project_presentation/create/origin.html.twig', [
                'form' => $form->createView(),
                'stringId' => $stringId,
                'position' => $position,
            ]);
        }

        // Case stringId is null we create and save a new project presentation in DB
        if ($stringId === null) {
            $goal = $form->get('goal')->getData();
            $presentation->setGoal($goal);
            $presentation->setCreator($this->getUser());

            $this->em->persist($presentation);
            $this->em->flush();

            return $this->redirectToRoute('project_presentation_helper', [
                'stringId' => $presentation->getStringId(),
                'position' => 1,
            ]);
        }

        // Case stringId not null we check position in step by step form

        $nextPosition = $form->get('nextPosition')->getData();
        $helperType = $form->get('helperItemType')->getData();

        // Case Final step: whole process done
        if ($nextPosition === null) {
            $this->assessPPScore->scoreUpdate($presentation);

            // mark presentation as completed for later cleanup
            $presentation->isCreationFormCompleted(true);

            $this->addFlash('success fs-4', <<<HTML
                ✅ Votre page de présentation est prête.<br>
                Apportez-lui toutes les modifications que vous désirez.<br>
                🙋 Si vous avez besoin d'aide, utilisez le bouton d'aide rapide en bas de page.
            HTML);

            return $this->redirectToRoute('show_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        // Handle Form logic
        switch ($helperType) {

            case 'title':
                $title = $form->get('title')->getData();
                if (!empty($title)) {
                    $presentation->setTitle($title);

                    $this->em->flush();
                }
                break;

           case 'imageSlide':
                /** @var Slide|null $slide */
                $slide = $form->get('imageSlide')->getData();

                if (!$slide) {
                    break;
                }

                $slide->setType('image');
                $presentation->addSlide($slide);

                // VichUploader handles moving the file
                if ($slide->getImageFile()) {
                    $this->imageResizer->edit($slide); 
                }
                
                // checked: check if file name is manage by Vitch and manage it as unique.
                // to do: check if thumbnail is updated
                // to do: check if image is resized

                $this->cacheThumbnail->updateThumbnail($presentation);

                $this->em->persist($slide);
                
                break;

            case 'textDescription':
                $text = nl2br($form->get('textDescription')->getData() ?? '');
                $presentation->setTextDescription($text);
                $this->em->flush();
                break;
        }

        // Go to next step
        return $this->redirectToRoute('project_presentation_helper', [
            'stringId' => $presentation->getStringId(),
            'position' => $nextPosition,
        ]);


    }
    

} 
