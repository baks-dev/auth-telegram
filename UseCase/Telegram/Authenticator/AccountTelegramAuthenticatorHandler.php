<?php
/*
 *  Copyright 2024.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Messenger\AccountTelegramMessage;
use BaksDev\Core\Entity\AbstractHandler;
use DomainException;

final class AccountTelegramAuthenticatorHandler extends AbstractHandler
{
    /** @see AccountTelegram */
    public function handle(
        AccountTelegramAuthenticatorDTO $command
    ): string|AccountTelegram
    {
        /** Валидация DTO  */
        $this->validatorCollection->add($command);

        $this->main = new AccountTelegram($command->getAccount());
        $this->event = new AccountTelegramEvent();

        try
        {
            $this->preUpdate($command, true);
        }
        catch(DomainException $errorUniqid)
        {
            return $errorUniqid->getMessage();
        }

        /** Валидация всех объектов */
        if($this->validatorCollection->isInvalid())
        {
            return $this->validatorCollection->getErrorUniqid();
        }


        /** Присваиваем идентификатор авторизации */
        $this->entityManager->clear();

        $this->main = $this->entityManager->getRepository(AccountTelegram::class)->findOneBy(['event' => $command->getEvent()]);
        $this->main->setEvent($command->getNew());

        $this->event->setId($command->getNew());
        $this->entityManager->persist($this->event);

        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new AccountTelegramMessage($this->main->getId(), $this->main->getEvent(), $command->getEvent()),
            transport: 'auth-telegram'
        );

        return $this->main;
    }
}