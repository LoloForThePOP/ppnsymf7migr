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
               
            ])

            // ──────────────── Email ────────────────
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['placeholder' => 'Écrire ici'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'adresse e-mail ne peut pas être vide.']),
                    new Assert\Email(['message' => 'Veuillez entrer une adresse e-mail valide.']),
                    new Assert\Length([
                        'max' => 180,
                        'maxMessage' => 'L\'adresse e-mail ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])

            // ──────────────── Password (single field) ────────────────
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Créez votre mot de passe',
                'mapped' => false,
                'attr' => ['placeholder' => 'Écrire ici', 'autocomplete' => 'new-password'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le mot de passe ne peut pas être vide.']),
                    new Assert\Length([
                        'min' => 8,
                        'max' => 255,
                        'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.',
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
