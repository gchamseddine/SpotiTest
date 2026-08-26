<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'attr' => [
                    'autocomplete' => 'username',
                    'minlength' => 3,
                    'maxlength' => 30,
                    'pattern' => '[A-Za-z0-9_.\-]+',
                    'title' => 'Only letters, numbers, underscores, hyphens, and periods are allowed.',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a username.',
                    ),
                    new Length(
                        min: 3,
                        max: 30,
                        minMessage: 'Your username should be at least {{ limit }} characters.',
                        maxMessage: 'Your username cannot be longer than {{ limit }} characters.',
                    ),
                    new Regex(
                        pattern: '/^[A-Za-z0-9_.-]+$/',
                        message: 'Your username can only contain letters, numbers, underscores, hyphens, and periods.',
                    ),
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'minlength' => 8,
                    'pattern' => '(?=.*[A-Za-z])(?=.*[0-9]).{8,}',
                    'title' => 'Password must be at least 8 characters and contain at least one letter and one number.',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter a password',
                    ),
                    new Length(
                        min: 8,
                        minMessage: 'Your password should be at least {{ limit }} characters',
                        max: 4096,
                    ),
                    new Regex(
                        pattern: '/[A-Za-z]/',
                        message: 'Your password must contain at least one letter.',
                    ),
                    new Regex(
                        pattern: '/[0-9]/',
                        message: 'Your password must contain at least one number.',
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
