<?php

namespace App\Form;

use App\Entity\News;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

class NewsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('textContent', TextareaType::class, [
                'label' => 'Texte de la News',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Écrire ici le texte de votre nouvelle',
                    'rows' => 5,
                ],
            ])
            ->add('image1File', VichImageType::class, [
                'label' => 'Image 1',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
                'image_uri' => false,
                'asset_helper' => true,
            ])
            ->add('captionImage1', TextType::class, [
                'label' => 'Légende Image 1',
                'required' => false,
                'attr' => ['placeholder' => 'Légende facultative'],
            ])
            ->add('image2File', VichImageType::class, [
                'label' => 'Image 2',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
                'image_uri' => false,
                'asset_helper' => true,
            ])
            ->add('captionImage2', TextType::class, [
                'label' => 'Légende Image 2',
                'required' => false,
                'attr' => ['placeholder' => 'Légende facultative'],
            ])
            ->add('image3File', VichImageType::class, [
                'label' => 'Image 3',
                'required' => false,
                'allow_delete' => true,
                'download_uri' => false,
                'image_uri' => false,
                'asset_helper' => true,
            ])
            ->add('captionImage3', TextType::class, [
                'label' => 'Légende Image 3',
                'required' => false,
                'attr' => ['placeholder' => 'Légende facultative'],
            ])
            ->add('presentationId', HiddenType::class, [
                'mapped' => false,
                'required' => false,
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => News::class,
        ]);
    }
}
