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
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;

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
                'constraints' => [
                    new Length([
                        'max' => 100,
                        'maxMessage' => 'Le nom ou la fonction ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    // Optional: prevent only spaces or non-visible chars
                    new Regex([
                        'pattern' => '/\S+/',
                        'message' => 'Ce champ ne peut pas être vide ou contenir uniquement des espaces.',
                    ]),
                ],
            ])

            // 📧 Email
            ->add('email1', EmailType::class, [
                'label' => 'Adresse e-mail',
                'required' => false,
                'attr' => [
                    'placeholder' => 'exemple@entreprise.com',
                ],
                'constraints' => [
                    new Email([
                        'message' => 'Veuillez saisir une adresse e-mail valide.',
                    ]),
                    new Length([
                        'max' => 180,
                        'maxMessage' => 'L’adresse e-mail ne peut pas dépasser {{ limit }} caractères.',
                    ]),
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
                'constraints' => [
                    new Length([
                        'min' => 6,
                        'max' => 20,
                        'minMessage' => 'Le numéro de téléphone doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                    new Regex([
                        'pattern' => '/^[0-9+\s().-]{6,20}$/',
                        'message' => 'Veuillez saisir un numéro de téléphone valide (chiffres et caractères autorisés : +, ., -, espace, parenthèses).',
                    ]),
                ],
            ])

            // 🌐 Primary website or social
            ->add('website1', UrlType::class, [
                'label' => 'Site web ou réseau social',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : https://www.entreprise.com',
                ],
                'constraints' => [
                    new Url([
                        'message' => 'Veuillez saisir une adresse web valide.',
                    ]),
                    new Length([
                        'max' => 255,
                        'maxMessage' => 'L’URL ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])

            // 🌐 Secondary website or social
            ->add('website2', UrlType::class, [
                'label' => 'Autre site web ou réseau social',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex : https://linkedin.com/in/nom',
                ],
                'constraints' => [
                    new Url([
                        'message' => 'Veuillez saisir une adresse web valide.',
                    ]),
                    new Length([
                        'max' => 255,
                    ]),
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
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'L’adresse postale ne peut pas dépasser {{ limit }} caractères.',
                    ]),
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
                'constraints' => [
                    new Length([
                        'max' => 500,
                        'maxMessage' => 'Les remarques ne peuvent pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
