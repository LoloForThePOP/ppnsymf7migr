<?php

namespace App\Form;

use App\Entity\Article;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class ArticleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                'title',
                TextType::class,
                [
                    'label' => "✒️ Quel est le titre de l'article ?",
                    'required' => true,
                    'attr' => [
                        'placeholder' => '',
                        'maxlength' => 255,
                    ],
                    'trim' => true,
                ]
            )
            ->add(
                'shortDescription',
                TextareaType::class,
                [
                    'label' => 'Résumé court (1-3 phrases)',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Ce qui donne envie de lire',
                        'rows' => 3,
                        'maxlength' => 500,
                    ],
                    'trim' => true,
                ]
            )
            ->add(
                'type',
                TextType::class,
                [
                    'label' => 'Catégorie interne (optionnel)',
                    'required' => false,
                    'attr' => [
                        'placeholder' => 'Ex : communiqué, billet, tutoriel…',
                        'maxlength' => 100,
                    ],
                    'trim' => true,
                ]
            )
            ->add(
                'slug',
                TextType::class,
                [
                    'label' => "🐌 Slug pour l'article ?",
                    'required' => false,
                    'empty_data' => '',
                    'help' => 'Laissez vide pour le générer automatiquement.',
                    'attr' => [
                        'placeholder' => 'ex: mon-article',
                        'maxlength' => 255,
                    ],
                    'trim' => true,
                ]
            )
            ->add('isValidated', CheckboxType::class, [
                'label' => 'Validation de l\'article (décocher la case si non validation).',
                'required' => false,
            ])
            ->add(
                'content',
                TextareaType::class,
                [
                    'label' => "✍️ Quel est le contenu de l'article ?",
                    'required' => false,
                    'attr' => [
                        'class' => 'tinymce',
                        'placeholder' => '',
                        'rows' => 10,
                    ],
                    'trim' => true,
                ]
            )
            ->add(
                'thumbnailFile',
                VichImageType::class,

                [
                    'label' => "🖼️ Vignette pour l'article :",

                    'attr' => [

                        'placeholder' => '',
                    ],

                    'required' => false,

                    'allow_delete' => false,
                    'download_label' => false,
                    'download_uri' => false,
                    'image_uri' => false,
                    'asset_helper' => true,
                ]
            
            )

        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Article::class,
        ]);
    }
}
