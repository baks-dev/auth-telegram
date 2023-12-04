<?php
/*
 *  Copyright 2023.  Baks.dev <admin@baks.dev>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

declare(strict_types=1);

namespace BaksDev\Auth\Telegram\Bot\Login\Email;

use BaksDev\Auth\Email\Repository\AccountEventActiveByEmail\AccountEventActiveByEmailInterface;
use BaksDev\Auth\Email\Type\Email\AccountEmail;
use BaksDev\Auth\Telegram\Bot\Login\Email\Steps\LoginStepAuth;
use BaksDev\Auth\Telegram\Bot\Login\Email\Steps\LoginStepError;
use BaksDev\Auth\Telegram\Bot\Login\Email\Steps\LoginStepStart;
use BaksDev\Auth\Telegram\Bot\Login\Email\Steps\LoginStepSuccess;
use BaksDev\Auth\Telegram\Repository\AuthTelegram\AuthTelegramRepositoryInterface;
use BaksDev\Telegram\Api\TelegramSendMessage;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class LoginTelegram
{
    private AuthTelegramRepositoryInterface $telegramRepository;

    private TelegramSendMessage $sendMessage;

    private AccountEventActiveByEmailInterface $accountEventActiveByEmail;

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        AuthTelegramRepositoryInterface $telegramRepository,
        TelegramSendMessage $sendMessage,
        AccountEventActiveByEmailInterface $accountEventActiveByEmail,
        UserPasswordHasherInterface $passwordHasher,
    )
    {
        $this->telegramRepository = $telegramRepository;
        $this->sendMessage = $sendMessage;
        $this->accountEventActiveByEmail = $accountEventActiveByEmail;
        $this->passwordHasher = $passwordHasher;
    }


    /**
     * Идентификатор чата
     */
    private ?int $chat = null;

    /**
     * Состояние, в котором находится процесс авторизации
     */
    private ?LoginStep $step = null;


    /**
     * Идентификатор чата
     */
    public function chat(int|string $chat): self
    {

        if(is_string($chat))
        {
            $chat = (int) filter_var($chat, FILTER_SANITIZE_NUMBER_INT);
        }

        $this->chat = $chat;
        return $this;
    }

    public function getChat(): ?int
    {
        return $this->chat;
    }

    /**
     * Состояние, в котором находится процесс авторизации
     */
    public function getStep(): ?LoginStep
    {
        return $this->step;
    }


    public function getSendMessage(): ?TelegramSendMessage
    {
        return $this->sendMessage->getMessage() ? $this->sendMessage : null;
    }


    /**
     * Применяем полученные данные к состоянию
     */
    public function handle(mixed $data = null): self
    {
        $this->step = $this->telegramRepository->getAuthTelegram($this);

        if($this->step === null)
        {
            $this->step = new LoginStepStart();
        }

        $this->step->setContext($this);
        $this->step->next($data);

        return $this;
    }

    public function transitionTo(LoginStep $loginStep): void
    {
        /** Получаем сообщение следующего шага */
        $class = $loginStep->getNextStep();

        $nextStep = new $class();

        /** Если следующий шаг FINISH - Завершение авторизации */
        if($nextStep instanceof LoginStepAuth)
        {

            /* Получаем активный аккаунт по Email */
            try
            {
                $account = $this->accountEventActiveByEmail->getAccountEvent(new AccountEmail($loginStep->getEmail()));

            } catch(InvalidArgumentException $exception)
            {
                $account = null;
            }

            if($account === null)
            {
                /** Ошибка авторизации */
                $nextStep = new LoginStepError();
                $loginStep = new LoginStepError();
            }
            else
            {
                /* Проверяем пароль */
                $passValid = $this->passwordHasher->isPasswordValid($account, $loginStep->getPassword());

                if($passValid === false)
                {
                    /** Ошибка авторизации */
                    $nextStep = new LoginStepError();
                    $loginStep = new LoginStepError();
                }
                else
                {
                    $nextStep = new LoginStepSuccess();

                    $loginStep = new LoginStepSuccess();
                    $loginStep->setUsr($account->getAccount());
                }
            }

        }
        
        /** Присваиваем сообщение для отправки в Telegram */
        if($nextStep->message())
        {
            $this->sendMessage->chanel($this->chat)->message($nextStep->message());

            if($nextStep->markup())
            {
                $this->sendMessage->markup($nextStep->markup());
            }
        }

        /** Сохраняем следующий шаг */
        $this->step = $loginStep;
        $this->telegramRepository->setAuthTelegram($this);
    }
}