<?php

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => false,
                'required' => true,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Votre commentaire...',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le commentaire ne peut pas être vide.'),
                    new Length(max: 2000, maxMessage: 'Le commentaire ne peut dépasser {{ limit }} caractères.'),
                ],
            ])

            ->add(
                
                'presentationId', 

                HiddenType::class,
                
                [

                    'empty_data' => '',
                    'required'   => false,
                    "mapped" => false,
                ]
                
            )

            ->add(
                
                'newsId', 

                HiddenType::class,
                
                [

                    'empty_data' => '',
                    'required'   => false,
                    "mapped" => false,
                ]
                
            )



            
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}
