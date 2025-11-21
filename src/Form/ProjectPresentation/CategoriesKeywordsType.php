<?php

namespace App\Form\ProjectPresentation;

use App\Entity\Category;
use App\Entity\PPBase;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoriesKeywordsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categories', EntityType::class, [
                'class' => Category::class,
                'choice_label' => fn (Category $category) => $category->getLabel() ?? $category->getUniqueName(),
                'choice_value' => 'uniqueName',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
            ])
            ->add('keywords', TextType::class, [
                'label' => 'Mots-clés',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PPBase::class,
            'translation_domain' => 'forms',
        ]);
    }
}
