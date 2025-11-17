<?php

namespace App\Form\ProjectPresentation;

use App\Entity\Slide;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VideoSlideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 🎥 Video link (entity: youtubeUrl)
            ->add('youtubeUrl', TextType::class, [
                'label' => 'Lien de la vidéo YouTube',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex : https://www.youtube.com/watch?v=xxxx',
                    'spellcheck' => 'false',
                    'maxlength' => 255,
                    'aria-label' => 'Lien de la vidéo YouTube',
                ],
            ])

            // ✍️ Optional caption
            ->add('caption', TextType::class, [
                'label' => 'Légende / titre de la vidéo (facultatif)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Écrire ici la légende ou le titre de la vidéo',
                    'maxlength' => 255,
                    'spellcheck' => 'true',
                    'aria-label' => 'Légende ou titre de la vidéo',
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
