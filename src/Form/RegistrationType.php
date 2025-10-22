<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\{
    EmailType,
    PasswordType,
    TextType,
    CheckboxType,
    SubmitType
};
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ──────────────── Username ────────────────
            ->add('username', TextType::class, [
                'label' => 'Choisir un nom d\'utilisateur',
                'attr' => ['placeholder' => 'Exemples : Lolo123 ; AviaCorp ; Jean RIVOIRE ; etc.'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Username cannot be empty.']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 40,
                        'minMessage' => 'Username must be at least {{ limit }} characters long.',
                        'maxMessage' => 'Username cannot exceed {{ limit }} characters.',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-Z0-9._-]+$/',
                        'message' => 'Username may only contain letters, numbers, dots, underscores, and dashes.',
                    ]),
                ],
            ])

            // ──────────────── Email ────────────────
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['placeholder' => 'Écrire ici'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Email cannot be empty.']),
                    new Assert\Email(['message' => 'Please enter a valid email.']),
                    new Assert\Length(['max' => 180]),
                ],
            ])

            // ──────────────── Password (single field) ────────────────
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Créez votre mot de passe',
                'mapped' => false,
                'attr' => ['placeholder' => 'Écrire ici', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Password cannot be empty.']),
                    new Assert\Length([
                        'min' => 8,
                        'max' => 255,
                        'minMessage' => 'Password must be at least {{ limit }} characters long.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
