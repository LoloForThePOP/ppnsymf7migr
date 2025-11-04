<?php

namespace App\Form\Password;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\EmailType;


class ForgottenPasswordEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Votre adresse e-mail',
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
            
        ]);
    }
}
