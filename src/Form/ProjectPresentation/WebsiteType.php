<?php

namespace App\Form\ProjectPresentation;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\WebsiteComponent;


class WebsiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre (facultatif)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Exemple : Site web officiel, Compte Instagram, etc.',
                    'maxlength' => 255, // HTML limit only
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Adresse du site',
                'default_protocol' => 'https',
                'required' => true,
                'attr' => [
                    'placeholder' => 'www.exemple.com',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WebsiteComponent::class,
            'validation_groups' => ['input'], // THIS IS IMPORTANT (form is binded to entity which have specific properties constraints)
        ]);
    }
}
