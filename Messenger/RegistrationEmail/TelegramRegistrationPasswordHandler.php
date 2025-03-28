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

namespace BaksDev\Auth\Telegram\Messenger\RegistrationEmail;

use BaksDev\Auth\Email\Repository\CurrentAccountEvent\CurrentAccountEventInterface;
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusActive;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusNew;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Auth\Telegram\UseCase\Admin\Remove\AccountTelegramRemoveDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\Remove\AccountTelegramRemoveHandler;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
final readonly class TelegramRegistrationPasswordHandler
{
    public function __construct(
        #[Target('authTelegramLogger')] private LoggerInterface $logger,
        private TelegramSendMessages $sendMessage,
        private AccountTelegramEventInterface $accountTelegramEvent,
        private UserPasswordHasherInterface $passwordHasher,
        private CurrentAccountEventInterface $currentAccountEvent,
        private AccountTelegramHandler $accountTelegramHandler,
        private AccountTelegramRemoveHandler $accountTelegramRemoveHandler,
        private AccountTelegramStatusCollection $accountTelegramStatusCollection,
    ) {}

    public function __invoke(TelegramRegistrationEmailMessage $message): void
    {
        $TelegramRequest = $message->getTelegramRequest();

        if(!$TelegramRequest instanceof TelegramRequestMessage)
        {
            return;
        }

        if($TelegramRequest->getText() === '/start')
        {
            return;
        }

        /** Только в приватном чате с ботом возможна регистрация */
        if($TelegramRequest->getChat()->getType() !== 'private')
        {
            return;
        }

        if(filter_var($TelegramRequest->getText(), FILTER_VALIDATE_EMAIL) !== false)
        {
            $this->logger->info('Не проверяем пароль: передан Email', [self::class.':'.__LINE__]);
            return;
        }

        $AccountTelegramEvent = $this->accountTelegramEvent->findByChat($TelegramRequest->getChatId());

        if(!$AccountTelegramEvent)
        {
            $this->logger->info('Не проверяем пользователя: AccountTelegram не найден', [
                self::class.':'.__LINE__,
                'chat' => $TelegramRequest->getChatId()
            ]);

            //            $this
            //                ->sendMessage
            //
            //                /** При регистрации всегда удаляем сообщение пользователя из чата */
            //                ->delete([
            //                    $TelegramRequest->getLast(),
            //                    $TelegramRequest->getSystem(),
            //                    $TelegramRequest->getId(),
            //                ])
            //
            //                ->chanel($TelegramRequest->getChatId())
            //                ->message('Не удалось убедиться, что этот аккаунт принадлежит Вам')
            //                ->send();

            return;
        }

        $this->accountTelegramStatusCollection->cases();
        $AccountTelegramDTO = new AccountTelegramDTO();
        $AccountTelegramEvent->getDto($AccountTelegramDTO);

        if(false === $AccountTelegramDTO->getStatus()->equals(AccountTelegramStatusNew::class))
        {
            $this->logger->info('Не регистрируем пользователя: AccountTelegram не имеет статуса NEW «Новый»', [self::class.':'.__LINE__]);
            return;
        }

        $AccountEvent = $this->currentAccountEvent->getByUser($AccountTelegramEvent->getAccount());

        if(!$AccountEvent)
        {
            /** Удаляем аккаунт Telegram если не найден аккаунт Email */
            $AccountTelegramRemoveDTO = new AccountTelegramRemoveDTO($AccountTelegramEvent->getAccount());
            $this->accountTelegramRemoveHandler->handle($AccountTelegramRemoveDTO);

            $this->logger->warning('AccountEvent не найден. Удаляем AccountTelegram', [
                self::class.':'.__LINE__,
                'UserUid' => $AccountTelegramEvent->getAccount()
            ]);

            return;
        }


        /* Проверяем пароль */
        $passValid = $this->passwordHasher->isPasswordValid($AccountEvent, $TelegramRequest->getText());

        if($passValid === false)
        {
            /* Ошибка авторизации, удаляем аккаунт Telegram */
            $this
                ->sendMessage
                ->delete([
                    //$TelegramRequest->getLast(),
                    $TelegramRequest->getSystem(),
                    $TelegramRequest->getId(),
                ])
                ->chanel($TelegramRequest->getChatId())
                ->message('Ошибка авторизации! Повторная попытка через 30 сек...')
                ->send();


            $AccountTelegramRemoveDTO = new AccountTelegramRemoveDTO($AccountTelegramEvent->getAccount());
            $this->accountTelegramRemoveHandler->handle($AccountTelegramRemoveDTO);

            $this->logger->warning('Неверный пароль. Ошибка авторизации пользователя!', [self::class.':'.__LINE__]);

            return;
        }


        /** Активируем и сохраняем аккаунт Telegram */
        $AccountTelegramDTO->setStatus(AccountTelegramStatusActive::class);
        $this->accountTelegramHandler->handle($AccountTelegramDTO);
        $this->logger->warning('Пользователь успешно авторизован', [self::class.':'.__LINE__]);

        $this
            ->sendMessage
            ->delete([
                $TelegramRequest->getLast(),
                $TelegramRequest->getSystem(),
                $TelegramRequest->getId(),
            ])
            ->chanel($TelegramRequest->getChatId())
            ->message('Вы успешно авторизованы')
            ->send();


    }

}

