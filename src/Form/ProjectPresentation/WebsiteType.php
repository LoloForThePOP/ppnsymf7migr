<?php

namespace App\Form\ProjectPresentation;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Constraints\Length;

class WebsiteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('description', TextType::class, [
                'label' => 'Titre (facultatif)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Exemple : Site web officiel, Instagram, etc.',
                    'maxlength' => 255, // HTML side
                ],
                'constraints' => [
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'Adresse du site',
                'required' => true,
                'attr' => [
                    'placeholder' => 'www.exemple.com',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Ce champ ne peut être vide',
                    ]),
                    new Url([
                        'message' => 'Vous devez utiliser une adresse web valide',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
