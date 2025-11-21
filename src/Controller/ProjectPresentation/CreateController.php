<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\Slide;
use App\Entity\PPBase;
use App\Enum\SlideType;
use App\Enum\ProjectStatuses;
use App\Service\AssessPPScoreService;
use App\Service\AI\ProjectTaggingService;
use App\Service\CacheThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Form\ProjectPresentation\ProjectPresentationCreationType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CreateController extends AbstractController
{
    private const HELPER_ORDER = ['goal', 'textDescription', 'initialStatus', 'imageSlide', 'title', 'categoriesKeywords'];

    public function __construct(
        private EntityManagerInterface $em,
        private AssessPPScoreService $assessPPScore, 
        private CacheThumbnailService $cacheThumbnail,
        private ProjectTaggingService $taggingService,
        
    ) {}

    #[Route(
        '/create-project-presentation/{position?0}/{stringId?}',
        name: 'create_project_presentation',
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

        $currentHelperType = self::HELPER_ORDER[$position] ?? null;

        // Pre-fill AI suggestions when landing on the categories step and nothing set yet (before form creation so the form reflects the data).
        if ($currentHelperType === 'categoriesKeywords'
            && $presentation->getCategories()->count() === 0
            && empty($presentation->getKeywords())
        ) {
            $this->taggingService->suggestAndApply($presentation);
            $this->em->flush();
        }

        // Create / update a Project Presentation, Form presented step by step in front-end
        $form = $this->createForm(ProjectPresentationCreationType::class, $presentation);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('project_presentation/create/origin.html.twig', [
                'form' => $form->createView(),
                'stringId' => $stringId,
                'position' => $position,
                'helperItems' => array_map(static fn ($type) => ['type' => $type], self::HELPER_ORDER),
            ]);
        }

        // Case stringId is null we create and save a new project presentation in DB
        if ($stringId === null) {
            $goal = $form->get('goal')->getData();
            $presentation->setGoal($goal);
            $presentation->setCreator($this->getUser());

            $this->em->persist($presentation);
            $this->em->flush();

            return $this->redirectToRoute('create_project_presentation', [
                'stringId' => $presentation->getStringId(),
                'position' => 1,
            ]);
        }

        // Case stringId not null we check position in step by step form

        $nextPosition = $form->get('nextPosition')->getData();
        $helperType = $form->get('helperItemType')->getData();

        // Case Final step: whole process done
        if ($nextPosition === null) {
            //applying presentation categories and keywords with AI service
            $this->taggingService->suggestAndApply($presentation);
            $this->assessPPScore->scoreUpdate($presentation);

            // mark presentation as completed for later cleanup
            $presentation->isCreationFormCompleted(true);
            $this->em->flush();

            $this->addFlash('success fs-4', <<<HTML
                ✅ Votre page de présentation est prête.<br>
                Apportez-lui toutes les modifications que vous désirez.<br>
                🙋 Si vous avez besoin d'aide, utilisez le bouton d'aide rapide en bas de page.
            HTML);

            return $this->redirectToRoute('edit_show_project_presentation', [
                'stringId' => $presentation->getStringId(),
            ]);
        }

        $allowedSteps = self::HELPER_ORDER;

        if (!in_array($helperType, $allowedSteps, true)) {
            throw $this->createAccessDeniedException('Invalid helper operation.');
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

            case 'initialStatus':

                $chosen = $form->get('initialStatus')->getData();

                // Security: validate against allowed enumerated statuses
                $allowed = ProjectStatuses::allKeys();

                if (!in_array($chosen, $allowed, true)) {
                    throw $this->createAccessDeniedException('Invalid status submitted.');
                }

                if ($chosen) {
                    $presentation->setStatuses([$chosen]); // reset + apply chosen
                    $this->em->flush();
                }
                break;

           case 'imageSlide':
                /** @var Slide|null $slide */
                $slide = $form->get('imageSlide')->getData();

                if (!$slide) {
                    break;
                }

                $slide->setType(SlideType::IMAGE);
                $presentation->addSlide($slide);

                // VichUploader handles moving the file
                if ($slide->getImageFile()) {
                   // to do $this->imageResizer->edit($slide); 
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

            case 'categoriesKeywords':
                $categories = $form->get('categories')->getData();
                $keywords = $form->get('keywords')->getData();

                foreach ($presentation->getCategories() as $existing) {
                    $presentation->removeCategory($existing);
                }

                foreach ($categories as $category) {
                    $presentation->addCategory($category);
                }

                $presentation->setKeywords($keywords ?: null);
                $this->em->flush();
                break;
        }

        // Go to next step
        return $this->redirectToRoute('create_project_presentation', [
            'stringId' => $presentation->getStringId(),
            'position' => $nextPosition,
        ]);


    }
    

} 
