<?php

namespace App\Form\ProjectPresentation;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class QuestionAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ❓ Question field
            ->add('question', TextType::class, [
                'label' => 'Question fréquemment posée',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Une question que des personnes vous posent',
                    'spellcheck' => 'true',
                    'maxlength' => 2500,
                    'aria-label' => 'Question',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une question.',
                    ]),
                    new Length([
                        'min' => 5,
                        'max' => 2500,
                        'minMessage' => 'La question doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La question ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/[A-Za-zÀ-ÖØ-öø-ÿ0-9]/u',
                        'message' => 'La question doit contenir au moins un caractère lisible.',
                    ]),
                ],
            ])

            // 💬 Answer field
            ->add('answer', TextareaType::class, [
                'label' => 'Votre réponse',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Rédigez ici une réponse à cette question.',
                    'rows' => 8,
                    'spellcheck' => 'true',
                    'maxlength' => 5000,
                    'aria-label' => 'Réponse',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez saisir une réponse.',
                    ]),
                    new Length([
                        'min' => 10,
                        'max' => 5000,
                        'minMessage' => 'La réponse doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La réponse ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/[A-Za-zÀ-ÖØ-öø-ÿ0-9]/u',
                        'message' => 'La réponse doit contenir au moins un caractère lisible.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mapped' => false,
            'csrf_protection' => true,
        ]);
    }
}
