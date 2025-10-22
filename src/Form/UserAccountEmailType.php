<?php

namespace App\Form;

use Assert\Email;
use Assert\Length;
use App\Entity\User;
use Assert\NotBlank;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

use Symfony\Component\Validator\Constraints as Assert;


class UserAccountEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail',
                'attr' => ['placeholder' => 'Écrire ici'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'adresse e-mail ne peut être vide.']),
                    new Assert\Email(['message' => 'VEuillez entrer une adresse e-mail valide.']),
                    new Assert\Length(['max' => 180]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
