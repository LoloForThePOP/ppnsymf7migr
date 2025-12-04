<?php

namespace App\Form\ProjectPresentation;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Embeddables\PPBase\OtherComponentsModels\BusinessCardComponent;

class BusinessCardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // 🧍‍♂️ Title or contact name
            ->add('title', TextType::class, [
                'label' => 'Nom ou fonction de la personne à contacter',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Laurent Dupond, Responsable Communication',
                    'maxlength' => 100,
                ],
            ])

            // 📧 Email
            ->add('email1', EmailType::class, [
                'label' => 'Adresse e-mail',
                'required' => false,
                'attr' => [
                    'placeholder' => 'exemple@entreprise.com',
                ],
            ])

            // ☎️ Phone number
            ->add('tel1', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : +33 6 12 34 56 78',
                    'pattern' => '^[0-9+\s().-]{6,20}$',
                    'maxlength' => 20,
                ],
            ])

            // 🌐 Primary website or social
            ->add('website1', UrlType::class, [
                'label' => 'Site web ou réseau social',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : https://www.entreprise.com',
                ],
            ])

            // 🌐 Secondary website or social
            ->add('website2', UrlType::class, [
                'label' => 'Autre site web ou réseau social',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : https://linkedin.com/in/nom',
                ],
            ])

            // 📬 Postal address
            ->add('postalMail', TextareaType::class, [
                'label' => 'Adresse postale',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : 24 rue de Rivoli, 75004 Paris, France',
                    'rows' => 3,
                    'maxlength' => 500,
                ],
            ])

            // 📝 Additional remarks
            ->add('remarks', TextareaType::class, [
                'label' => 'Informations ou remarques supplémentaires',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : Horaires d’ouverture, personne de contact secondaire…',
                    'rows' => 3,
                    'maxlength' => 500,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BusinessCardComponent::class,
            'validation_groups' => ['Default', 'input'],
        ]);
    }
}
