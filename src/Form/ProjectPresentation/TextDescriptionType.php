<?php

namespace App\Form\ProjectPresentation;

use App\Entity\PPBase;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TextDescriptionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('textDescription', TextareaType::class, [
            'required' => false,
            'label' => false,
            'sanitize_html' => true,
            'sanitizer' => 'text_description',
            'attr' => [
                'rows' => 14,
                'class' => 'form-control text-description-field',
                'placeholder' => 'Décrivez votre projet en plusieurs paragraphes.',
                'data-live-field' => 'text-description',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PPBase::class,
        ]);
    }
}
