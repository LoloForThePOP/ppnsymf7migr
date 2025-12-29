<?php

namespace App\Controller\ProjectPresentation;

use App\Entity\News;
use App\Entity\PPBase;
use App\Form\NewsType;
use App\Form\ProjectPresentation\LogoType;
use App\Form\ProjectPresentation\WebsiteType;
use App\Form\ProjectPresentation\DocumentType;
use App\Form\ProjectPresentation\ImageSlideType;
use App\Form\ProjectPresentation\VideoSlideType;
use App\Form\ProjectPresentation\BusinessCardType;
use App\Form\ProjectPresentation\QuestionAnswerType;
use App\Form\ProjectPresentation\TextDescriptionType;
use App\Form\ProjectPresentation\CategoriesKeywordsType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

trait EditShowContextTrait
{
    /**
     * @param array<string, FormInterface|FormView> $overrides
     *
     * @return array<string, mixed>
     */
    private function buildEditShowContext(PPBase $presentation, array $overrides = []): array
    {
        $addNewsForm = $this->createForm(
            NewsType::class,
            new News(),
            [
                'action' => $this->generateUrl('pp_create_news', [
                    'stringId' => $presentation->getStringId(),
                ]),
                'method' => 'POST',
            ]
        );
        $addNewsForm->get('presentationId')->setData($presentation->getId());

        $forms = [
            'addLogoForm' => $this->createForm(LogoType::class, $presentation),
            'addWebsiteForm' => $this->createForm(WebsiteType::class),
            'addQuestionAnswerForm' => $this->createForm(QuestionAnswerType::class),
            'addImageSlideForm' => $this->createForm(ImageSlideType::class),
            'addVideoSlideForm' => $this->createForm(VideoSlideType::class),
            'addNewsForm' => $addNewsForm,
            'textDescriptionForm' => $this->createForm(TextDescriptionType::class, $presentation),
            'categoriesKeywordsForm' => $this->createForm(
                CategoriesKeywordsType::class,
                $presentation,
                ['validation_groups' => ['CategoriesKeywords']]
            ),
            'addDocumentForm' => $this->createForm(DocumentType::class),
            'addBusinessCardForm' => $this->createForm(BusinessCardType::class),
        ];

        $forms = array_merge($forms, $overrides);

        $context = [
            'presentation' => $presentation,
            'userPresenter' => true,
            'userAdmin' => $this->isGranted('ROLE_ADMIN'),
        ];

        foreach ($forms as $key => $form) {
            $context[$key] = $form instanceof FormInterface ? $form->createView() : $form;
        }

        return $context;
    }
}
