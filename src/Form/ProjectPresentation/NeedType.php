<?php

namespace App\Form\ProjectPresentation;

use App\Entity\Need;
use App\Enum\NeedPaidStatus;
use App\Enum\NeedType as NeedTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class NeedType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'type', 
                EnumType::class,
                [
                    'label' => 'Type du besoin',
                    'enum_class' => NeedTypeEnum::class,
                    'choice_label' => static function (?NeedTypeEnum $choice): string {
                        return match ($choice) {
                            NeedTypeEnum::Skill => 'Une compétence (ex : un développeur; un électricien)',
                            NeedTypeEnum::Task => 'Un service ponctuel (ex : préparer un repas; créer deux dessins)',
                            NeedTypeEnum::Material => 'Un objet, un outil, du matériel (ex : une perceuse)',
                            NeedTypeEnum::Area => 'Un local, un terrain, une surface',
                            NeedTypeEnum::Advice => 'Un conseil',
                            NeedTypeEnum::Money => "Une somme d'argent",
                            NeedTypeEnum::Other => 'Autre',
                            default => '',
                        };
                    },
                    'placeholder' => 'Choisir une option',
                    'required' => false,
                ]
            )
            ->add(
                'title', 
                TextType::class,
                [
                    'label' => 'Quel est le titre de ce besoin ?',
                    'required' => true,
                    'attr' => [
                        'placeholder'    => "Exemple : Un local à Paris",
                    ],
                ]
            )
            ->add(
                'paymentStatus', 
                EnumType::class,
                [
                    'label' => 'Est-ce payé ?',
                    'enum_class' => NeedPaidStatus::class,
                    'choice_label' => static function (?NeedPaidStatus $choice): string {
                        return match ($choice) {
                            NeedPaidStatus::Maybe => 'Peut-être, à voir',
                            NeedPaidStatus::Yes => 'Oui',
                            NeedPaidStatus::No => 'Non',
                            default => '',
                        };
                    },
                    'placeholder' => 'Choisir une option',
                    'required' => false,
                ]
            )
            ->add(
                'description', 
                TextareaType::class,
                [
                    'label' => 'Décrivez votre besoin',
                    'required' => false,
                    'attr' => [
                        'placeholder'    => "Exemple : Nous recherchons un local pour pouvoir développer notre projet. L'idéal serait 30 m² au minimum, si possible à une distance raisonnable du centre ville.",
                        'rows' => '7',
                    ],
                ]
            )
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Need::class,
        ]);
    }
}
