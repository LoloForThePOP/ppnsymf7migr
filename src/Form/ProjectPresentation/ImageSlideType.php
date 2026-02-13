<?php

namespace App\Form\ProjectPresentation;

use App\Entity\Slide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ImageSlideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 🖼️ Image upload (Vich handles mapping + validation)
            ->add('imageFile', VichImageType::class, [
                'label' => '🖼️ Choisir une image (facultatif si vous souhaitez conserver l’actuelle)',
                'required' => false,
                'allow_delete' => false,
                'download_uri' => false,
                'download_label' => false,
                'image_uri' => false,
                'asset_helper' => true,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif,image/avif',
                    'aria-label' => 'Fichier image à téléverser',
                ],
            ])

            // ✍️ Optional caption
            ->add('caption', TextType::class, [
                'label' => '✍️ Légende / titre de l\'image (facultatif)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Écrire ici la légende ou le titre de l\'image',
                    'maxlength' => 400,
                    'spellcheck' => 'true',
                    'aria-label' => 'Légende ou titre de l\'image',
                ],
            ])

            // © Licence or credit
            ->add('licence', TextType::class, [
                'label' => '© Crédits ou droits d\'utilisation (facultatif)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Image Wikipedia CC BY-SA 4.0',
                    'maxlength' => 255,
                    'spellcheck' => 'true',
                    'aria-label' => 'Crédits ou droits d\'utilisation de l\'image',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Slide::class,
        ]);
    }
}
