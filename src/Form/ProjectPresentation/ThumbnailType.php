<?php

namespace App\Form\ProjectPresentation;

use App\Entity\PPBase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ThumbnailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 🖼️ Custom thumbnail (presentation cover)
            ->add('customThumbnailFile', VichImageType::class, [
                'label' => '🖼️ Vignette de la présentation',
                'required' => false,
                'allow_delete' => false,
                'download_label' => false,
                'download_uri' => false,
                'image_uri' => false,
                'asset_helper' => true,
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp,image/avif',
                    'aria-label' => 'Choisir une image pour la vignette de la présentation',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PPBase::class,
            // CSRF protection automatically enabled for mapped forms
        ]);
    }
}
