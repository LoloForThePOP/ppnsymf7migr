<?php

namespace App\Form;

use App\Entity\Slide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

/**
 * This form is used in ProjectPresentationCreationType which is mapped to PPBase entity, not Slide entity. I think I used File Type instead of VichFileType here to avoid conflicts with embedding this form and VichUploader.
 */
class ImageSlideWithoutVichHelperType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Image file upload (temporary, non-Vich)
            ->add('file', FileType::class, [
                'label' => 'Cliquer pour sélectionner une image',
                'required' => false,
            ])

            // Optional licence / credits field
            ->add('licence', TextType::class, [
                'label' => "Crédits ou droits d’utilisation de l’image – ©",
                'attr' => [
                    'placeholder' => "Ex. : Image Wikipedia CC BY-SA 4.0",
                ],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Slide::class,
            'translation_domain' => 'forms',
        ]);
    }
}
