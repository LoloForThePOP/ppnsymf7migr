<?php

namespace App\Form\ProjectPresentation;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\QuestionAnswerComponent;

class QuestionAnswerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('question', TextType::class, [
                'label' => 'Question',
                'attr' => [
                    'placeholder' => 'Composez votre question...',
                    'maxlength' => 2500,
                ],
            ])
            ->add('answer', TextareaType::class, [
                'label' => 'Réponse',
                'attr' => [
                    'placeholder' => 'Votre réponse…',
                    'rows' => 8,
                    'maxlength' => 5000,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QuestionAnswerComponent::class,
            'validation_groups' => ['input'],
        ]);
    }
}
