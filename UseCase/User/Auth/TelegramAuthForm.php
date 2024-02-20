<?php

namespace BaksDev\Auth\Telegram\UseCase\User\Auth;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TelegramAuthForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'code',
            NumberType::class,['attr' => ['autocomplete' => 'off'],]
        );

        /* Применить ******************************************************/
        $builder->add
        (
            'telegram_auth',
            SubmitType::class,
            ['label' => 'Login', 'label_html' => true]
        );
    }


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
                'data_class' => TelegramAuthDTO::class,
                'method' => 'POST',
                'csrf_token_id' => 'authenticate',
            ]
        );
    }

}
