<?php

namespace App\Form\ProjectPresentation;

use App\Entity\PPBase;
use App\Entity\Category;
use Symfony\Component\Form\AbstractType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Form\ProjectPresentation\ImageSlideWithoutVichHelperType;
use Symfony\Component\Form\Extension\Core\Type\{
    TextType,
    TextareaType,
    HiddenType
};

/**
 * Form type used during the project presentation creation wizard.
 */
class ProjectPresentationCreationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
        
            ->add('goal', TextType::class, [
                'label' => "Quel est l'objectif du projet ?",
                'attr' => [
                    'placeholder' => "Écrire ici l'objectif du projet",
                ],
                'required' => true,
            ])

            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                'attr' => [
                    'placeholder' => 'Écrire ici le titre',
                ],
                'required' => false,
            ])

            ->add('textDescription', TextareaType::class, [
                'label' => 'Votre réponse',
                'attr' => [
                    'placeholder' => 'Écrire ici',
                    'rows' => 5,
                    'autofocus' => true,
                ],
                'required' => false,
            ])

            // Project initial status (ex: idea or production)

            ->add('initialStatus', HiddenType::class, ['mapped' => false])


            // Image slide upload (custom form type, not using Vich Upload Type as imageSlide is not mapped in PPBase entity)
            ->add('imageSlide', ImageSlideWithoutVichHelperType::class, [
                'mapped' => false,
                'label' => false,
            ])

            // Categories & keywords (user may override AI suggestions)
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => fn (Category $category) => $category->getLabel() ?? $category->getUniqueName(),
                'choice_value' => 'uniqueName',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
            ])
            ->add('keywords', TextType::class, [
                'label' => 'Mots-clés',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: mobilité, solaire, inclusion',
                ],
            ])

            // Helper hidden fields for step-by-step workflow
            ->add('helperItemType', HiddenType::class, ['mapped' => false])
            ->add('currentPosition', HiddenType::class, ['mapped' => false])
            ->add('nextPosition', HiddenType::class, ['mapped' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PPBase::class,
            'translation_domain' => 'forms', // optional but recommended
        ]);
    }
}
