<?php

namespace App\Form;

use App\Entity\Profile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Vich\UploaderBundle\Form\Type\VichImageType;

final class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder

            ->add('imageFile', VichImageType::class, [
                'label' => 'Une photo, une image, ou un logo ?',
                'required' => false,
                'allow_delete' => true,
                'download_label' => false,
                'download_uri' => false,
                'image_uri' => false,
                'asset_helper' => true,
            ])

            ->add('description', TextareaType::class, [
                'label' => 'Ajouter des informations ou des remarques ?',
                'attr' => [
                    'placeholder' => "Exemple (cas d'une personne) : Aime la lecture, la musique, et la marche. Autre exemple (cas d'une organisation) : Leader dans le domaine de la construction écologique.",
                    'rows' => 4,
                ],
                'required' => false,
            ])

            ->add('website1', UrlType::class, [
                'label' => 'Réseau social ou site web',
                'attr' => ['placeholder' => 'Écrire une adresse web ici'],
                'required' => false,
            ])
            ->add('website2', UrlType::class, [
                'label' => 'Réseau social ou site web 2',
                'attr' => ['placeholder' => 'Écrire une adresse web ici'],
                'required' => false,
            ])
            ->add('website3', UrlType::class, [
                'label' => 'Réseau social ou site web 3',
                'attr' => ['placeholder' => 'Écrire ici'],
                'required' => false,
            ])

            ->add('postalMail', TextareaType::class, [
                'label' => 'Une adresse postale ?',
                'attr' => ['placeholder' => 'Écrire ici'],
                'required' => false,
            ])

            ->add('tel1', TelType::class, [
                'label' => 'Un téléphone ?',
                'attr' => ['placeholder' => 'Écrire ici'],
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Profile::class,
        ]);
    }
}
