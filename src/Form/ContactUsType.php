<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints as Assert;

class ContactUsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('authorEmail', EmailType::class, [
                'label' => 'Votre adresse e-mail',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Merci de renseigner votre email.'),
                    new Assert\Email(message: 'Merci de renseigner un email valide.'),
                    new Assert\Length(max: 180),
                ],
                'attr' => [
                    'placeholder' => 'votre@email.fr',
                    'autocomplete' => 'email',
                ],
            ])
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Merci de renseigner un sujet.'),
                    new Assert\Length(max: 200),
                ],
                'attr' => [
                    'placeholder' => 'Sujet du message',
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu du message',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(message: 'Merci de renseigner un message.'),
                    new Assert\Length(max: 5000),
                ],
                'attr' => [
                    'placeholder' => "Écrire ici",
                    'rows' => '7',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
