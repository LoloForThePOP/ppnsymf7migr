<?php

namespace App\Form\ProjectPresentation;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichFileType;

class DocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', VichFileType::class, [
                'label' => 'Choisir un fichier à importer',
                'required' => $options['file_required'],
                'allow_delete' => false,
                'download_label' => false,
                'download_uri' => false,
                'asset_helper' => true,
                'attr' => [
                    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.txt',
                    'aria-label' => 'Fichier à téléverser',
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre du document',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex : Manifeste, Compte rendu de réunion',
                    'maxlength' => 255,
                    'spellcheck' => 'true',
                    'aria-label' => 'Titre du document',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Document::class,
            'file_required' => true,
        ]);
    }
}
