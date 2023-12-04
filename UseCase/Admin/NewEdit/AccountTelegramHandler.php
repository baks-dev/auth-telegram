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

namespace BaksDev\Auth\Telegram\UseCase\Admin\NewEdit;


use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Messenger\AccountTelegramMessage;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Users\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AccountTelegramHandler
{
    private EntityManagerInterface $entityManager;

    private ValidatorInterface $validator;

    private LoggerInterface $logger;

    private MessageDispatchInterface $messageDispatch;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        MessageDispatchInterface $messageDispatch
    )
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
        $this->messageDispatch = $messageDispatch;

    }

    /** @see AccountTelegram */
    public function handle(
        AccountTelegramDTO $command,
    ): string|AccountTelegram
    {
        /**
         *  Валидация AccountTelegramDTO
         */
        $errors = $this->validator->validate($command);

        if(count($errors) > 0)
        {
            $uniqid = uniqid('', false);
            $errorsString = (string)$errors;
            $this->logger->error($uniqid.': '.$errorsString);
            return $uniqid;
        }


        if($command->getEvent())
        {
            $EventRepo = $this->entityManager->getRepository(AccountTelegramEvent::class)->find(
                $command->getEvent()
            );

            if($EventRepo === null)
            {
                $uniqid = uniqid('', false);
                $errorsString = sprintf(
                    'Not found %s by id: %s',
                    AccountTelegramEvent::class,
                    $command->getEvent()
                );
                $this->logger->error($uniqid.': '.$errorsString);

                return $uniqid;
            }

            $EventRepo->setEntity($command);
            $EventRepo->setEntityManager($this->entityManager);
            $Event = $EventRepo->cloneEntity();
        }
        else
        {
            $Event = new AccountTelegramEvent();
            $Event->setEntity($command);
            $this->entityManager->persist($Event);
        }

//        $this->entityManager->clear();
//        $this->entityManager->persist($Event);


        /** @var AccountTelegram $Main */
        if($Event->getAccount())
        {
            $Main = $this->entityManager
                ->getRepository(AccountTelegram::class)
                ->findOneBy(['event' => $command->getEvent()]);

            if(empty($Main))
            {
                $uniqid = uniqid('', false);
                $errorsString = sprintf(
                    'Not found %s by event: %s',
                    AccountTelegram::class,
                    $command->getEvent()
                );
                $this->logger->error($uniqid.': '.$errorsString);

                return $uniqid;
            }
        }

        /** Если не передан идентификатор пользователя - создаем нового */
        if(!$Event->getAccount())
        {
            $usr = new User();
            $this->entityManager->persist($usr);
            $Main = new AccountTelegram($usr);
            $this->entityManager->persist($Main);
        }
        else
        {
            $Main = $this->entityManager->getRepository(AccountTelegram::class)->find($Event->getAccount());

            if(empty($Main))
            {
                $usr = $Event->getAccount();
                $Main = new AccountTelegram($usr);
                $this->entityManager->persist($Main);
            }
        }

        /* присваиваем событие корню */
        $Event->setAccount($Main);
        $Main->setEvent($Event);


        /**
         * Валидация Event
         */

        $errors = $this->validator->validate($Event);

        if(count($errors) > 0)
        {
            /** Ошибка валидации */
            $uniqid = uniqid('', false);
            $this->logger->error(sprintf('%s: %s', $uniqid, $errors), [__FILE__.':'.__LINE__]);

            return $uniqid;
        }


        /**
         * Валидация Main
         */
        $errors = $this->validator->validate($Main);

        if(count($errors) > 0)
        {
            $uniqid = uniqid('', false);
            $errorsString = (string)$errors;
            $this->logger->error($uniqid.': '.$errorsString);
            return $uniqid;
        }


        $this->entityManager->flush();

        /* Отправляем сообщение в шину */
        $this->messageDispatch->dispatch(
            message: new AccountTelegramMessage($Main->getId(), $Main->getEvent(), $command->getEvent()),
            transport: 'auth-telegram'
        );

        // 'account_telegram_high'
        return $Main;
    }
}
