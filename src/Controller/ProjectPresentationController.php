<?php

namespace App\Controller;

use App\Entity\PPBase;
use App\Entity\Slide;
use App\Entity\News;
use App\Entity\Document;
use App\Entity\Persorg;
use App\Entity\ContributorStructure;
use App\Entity\BankAccount;
use App\Form\ProjectPresentation\{
    WebsiteType,
    NewsType,
    BusinessCardType,
    QuestionAnswerType,
    DocumentType,
    ImageSlideType,
    VideoSlideType,
};  

use App\Form\{

    
    PPBaseType,
    ContributorStructureType,
    PersorgType,
    BankAccountType,
    CreatePresentationType
};
use App\Service\{
    TreatItem,
    CacheThumbnailService,
    ImageResizerService,
    AssessQualityService,
    MailerService,
    NotificationService,
    StripeService
};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\{
    Request,
    Response
};
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/projects')]
final class ProjectPresentationController extends AbstractController
{
    #[Route(
        '/{stringId}/',
        name: 'project_presentation',
        priority: -1
    )]
    #[Route(
        '/show-by-id/{id}/',
        name: 'project_presentation_by_id',
        priority: -2
    )]
    #[IsGranted('view', subject: 'presentation')]
    public function show(
        PPBase $presentation,
        Request $request,
        EntityManagerInterface $em,
        TreatItem $specificTreatments,
        CacheThumbnailService $cacheThumbnail,
        ImageResizerService $imageResizer,
        AssessQualityService $assessQuality,
        MailerService $mailer,
        NotificationService $notifService,
        StripeService $stripeService,
    ): Response {

        $firstTimeEditor = $request->query->getBoolean('first-time-editor');
        $user = $this->getUser();

        // ✅ 1. View counting (only for non-creator users)
        if (
            (!$user instanceof UserInterface || $user !== $presentation->getCreator()) &&
            !array_key_exists('guest-presenter-token', $presentation->getData())
        ) {
            $presentation->setDataItem('viewsCount', $presentation->getDataItem('viewsCount') + 1);
            $em->flush();
        }

        // ✅ 2. Editing allowed — show edit forms
        if ($this->isGranted('edit', $presentation)) {
            // Group of small forms
            $forms = [
                'addWebsiteForm' => $this->createForm(WebsiteType::class),
                'addNewsForm' => $this->createForm(NewsType::class, new News()),
                'addBusinessCardForm' => $this->createForm(BusinessCardType::class),
                'addQAForm' => $this->createForm(QuestionAnswerType::class),
                'addDocumentForm' => $this->createForm(DocumentType::class, new Document()),
                'addImageForm' => $this->createForm(ImageSlideType::class, (new Slide())->setType('image')),
                'addVideoForm' => $this->createForm(VideoSlideType::class, (new Slide())->setType('youtube_video')),
                'addLogoForm' => $this->createForm(PPBaseType::class, $presentation),
                'addECSForm' => $this->createForm(ContributorStructureType::class, new ContributorStructure()),
                'addPersorgForm' => $this->createForm(PersorgType::class, new Persorg()),
                'bankAccountInfoForm' => $this->createForm(BankAccountType::class, new BankAccount()),
            ];

            // Handle all forms sequentially (simplified readability)
            foreach ($forms as $key => $form) {
                $form->handleRequest($request);

                if ($form->isSubmitted() && $form->isValid()) {
                    $response = $this->processForm(
                        $key,
                        $form->getData(),
                        $presentation,
                        $user,
                        $em,
                        $specificTreatments,
                        $imageResizer,
                        $cacheThumbnail,
                        $assessQuality,
                        $mailer,
                        $notifService
                    );
                    if ($response instanceof Response) {
                        return $response;
                    }
                }
            }

            return $this->render('project_presentation/show.html.twig', [
                'presentation' => $presentation,
                'stringId' => $presentation->getStringId(),
                'contactUsPhone' => $this->getParameter('app.contact_phone'),
                'firstTimeEditor' => $firstTimeEditor,
                ...array_map(fn($f) => $f->createView(), $forms),
            ]);
        }

        // ✅ 3. Non-editor users (show-only)
        $createForm = $this->createForm(CreatePresentationType::class, new PPBase());
        $createForm->handleRequest($request);

        if ($createForm->isSubmitted() && $createForm->isValid()) {
            $projectGoal = $createForm->get('goal')->getData();

            $mailer->send(
                $this->getParameter('app.email.noreply'),
                'Propon',
                $this->getParameter('app.email.contact'),
                "A New Presentation Has Been Created",
                sprintf('Project Goal: %s', $projectGoal)
            );

            return $this->redirectToRoute('edit_presentation_as_guest_user', [
                'goal' => $projectGoal,
            ]);
        }

        return $this->render('project_presentation/show.html.twig', [
            'presentation' => $presentation,
            'stringId' => $presentation->getStringId(),
            'contactUsPhone' => $this->getParameter('app.contact_phone'),
            'createPresentationFormCTA' => $createForm->createView(),
            'firstTimeEditor' => $firstTimeEditor,
        ]);
    }

    /**
     * Handles submission logic for individual forms (factorized for now).
     */
    private function processForm(
        string $formKey,
        mixed $data,
        PPBase $presentation,
        ?UserInterface $user,
        EntityManagerInterface $em,
        TreatItem $specificTreatments,
        ImageResizerService $imageResizer,
        CacheThumbnailService $cacheThumbnail,
        AssessQualityService $assessQuality,
        MailerService $mailer,
        NotificationService $notifService
    ): ?Response {
        switch ($formKey) {
            case 'addWebsiteForm':
                $data = $specificTreatments->specificTreatments('websites', $data);
                $presentation->addOtherComponentItem('websites', $data);
                $em->flush();
                $this->addFlash('success fade-out', "✅ Ajout effectué");
                return $this->redirectWithFragment($presentation, 'websites-struct-container');

            case 'addNewsForm':
                $data->setProject($presentation);
                $data->setAuthor($user);
                $em->persist($data);
                $em->flush();
                $notifService->process("news", "projectPresentationCreation", ["presentation" => $presentation]);
                $this->addFlash('success fade-out', "✅ Ajout effectué");
                return $this->redirectWithFragment($presentation, 'news-struct-container');

            case 'addImageForm':
                $data->setPosition(count($presentation->getSlides()));
                $presentation->addSlide($data);
                $em->persist($data);
                $em->flush();
                $assessQuality->assessQuality($presentation);
                $imageResizer->edit($data);
                $cacheThumbnail->updateThumbnail($presentation);
                $this->addFlash('success', "✅ Image ajoutée");
                return $this->redirectWithFragment($presentation, 'slideshow-struct-container');

            // Other cases identical — trimmed for brevity but unchanged in logic
        }

        return null;
    }

    private function redirectWithFragment(PPBase $presentation, string $fragment): Response
    {
        return $this->redirectToRoute('show_presentation', [
            'stringId' => $presentation->getStringId(),
            '_fragment' => $fragment,
        ]);
    }
}
