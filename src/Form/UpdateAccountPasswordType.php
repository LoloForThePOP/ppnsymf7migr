<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class UpdateAccountPasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('oldPassword', PasswordType::class, [
                'label' => 'Ancien mot de passe',
                'attr' => [
                    'placeholder' => 'Votre ancien mot de passe',
                    'autocomplete' => 'current-password',
                ],
                'required' => true,
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'required' => true,
                'invalid_message' => 'Les deux champs doivent correspondre.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => [
                        'placeholder' => 'Écrire ici',
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => [
                        new NotBlank([
                            'message' => 'Ce champ ne peut être vide',
                        ]),
                        new Length([
                            'min' => 8,
                            'max' => 250,
                            'minMessage' => 'Votre mot de passe doit comporter au moins {{ limit }} caractères.',
                            'maxMessage' => 'Votre mot de passe ne peut pas dépasser {{ limit }} caractères.',
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => [
                        'placeholder' => 'Écrire ici',
                        'autocomplete' => 'new-password',
                    ],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
