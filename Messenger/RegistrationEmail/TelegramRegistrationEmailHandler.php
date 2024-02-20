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

use BaksDev\Auth\Email\Repository\AccountEventActiveByEmail\AccountEventActiveByEmailInterface;
use BaksDev\Auth\Email\Type\Email\AccountEmail;
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Telegram\Api\TelegramDeleteMessage;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler(priority: 999)]
final class TelegramRegistrationEmailHandler
{
    private AccountTelegramEventInterface $accountTelegramEvent;
    private AccountEventActiveByEmailInterface $accountEventActiveByEmail;
    private AccountTelegramHandler $accountTelegramHandler;
    private TelegramSendMessage $sendMessage;
    private TelegramDeleteMessage $deleteMessage;
    private LoggerInterface $logger;
    private AccountTelegramStatusCollection $accountTelegramStatusCollection;
    //private CacheInterface $cache;

    public function __construct(
        LoggerInterface $authTelegramLogger,
        TelegramSendMessage $sendMessage,
        TelegramDeleteMessage $deleteMessage,
        AccountTelegramEventInterface $accountTelegramEvent,
        AccountEventActiveByEmailInterface $accountEventActiveByEmail,
        AccountTelegramHandler $accountTelegramHandler,
        AccountTelegramStatusCollection $accountTelegramStatusCollection,
        //AppCacheInterface $appCache
    )
    {
        $this->accountTelegramEvent = $accountTelegramEvent;
        $this->accountEventActiveByEmail = $accountEventActiveByEmail;
        $this->accountTelegramHandler = $accountTelegramHandler;
        $this->sendMessage = $sendMessage;
        $this->deleteMessage = $deleteMessage;
        $this->logger = $authTelegramLogger;
        $this->accountTelegramStatusCollection = $accountTelegramStatusCollection;
        //$this->cache = $appCache->init('telegram');
    }

    public function __invoke(TelegramRegistrationEmailMessage $message): void
    {
        $TelegramRequest = $message->getTelegramRequest();

        if(!$TelegramRequest instanceof TelegramRequestMessage)
        {
            return;
        }

        /** Только в приватном чате с ботом возможна регистрация */
        if($TelegramRequest->getChat()->getType() !== 'private')
        {
            return;
        }

        /** При регистрации всегда удаляем сообщение пользователя из чата */
//        $this->deleteMessage
//            ->chanel($TelegramRequest->getChatId())
//            ->delete($TelegramRequest->getId())
//            ->send();

//        /** Удаляем предыдущее сообщение */
//        if($TelegramRequest->getLast())
//        {
//            $this->deleteMessage
//                ->chanel($TelegramRequest->getChatId())
//                ->delete($TelegramRequest->getLast())
//                ->send();
//        }

//        /** Удаляем предыдущее системное сообщение */
//        if($TelegramRequest->getSystem())
//        {
//            $this->deleteMessage
//                ->chanel($TelegramRequest->getChatId())
//                ->delete($TelegramRequest->getSystem())
//                ->send();
//        }

        $this->accountTelegramStatusCollection->cases();

        $AccountTelegramEvent = $this->accountTelegramEvent->findByChat($TelegramRequest->getChatId());

        if($AccountTelegramEvent !== null)
        {
            $this->logger->info('Пользователь уже заполнил Email', [__FILE__.':'.__LINE__]);
            return;
        }

        /** Если чат найден - отправляем запрос на мыло либо пароль */

        if(filter_var($TelegramRequest->getText(), FILTER_VALIDATE_EMAIL) !== false)
        {
            /** Поиск пользователя по указанному email */
            $AccountEvent = $this->accountEventActiveByEmail
                ->getAccountEvent(new AccountEmail($TelegramRequest->getText()));

            if(!$AccountEvent)
            {
                $this->logger->warning('Пользователь с указанным Email не найден', [__FILE__.':'.__LINE__, $TelegramRequest->getText()]);
                return;
            }

            $TelegramUserDTO = $TelegramRequest->getUser();

            /** Создаем Telegram аккаунт со статусом NEW «Новый» */
            $AccountTelegramDTO = new AccountTelegramDTO();
            $AccountTelegramDTO
                ->setAccount($AccountEvent->getAccount())
                ->setChat($TelegramRequest->getChatId())
                ->setFirstname($TelegramUserDTO->getFirstName());

            if($TelegramUserDTO->getUsername())
            {
                $AccountTelegramDTO->setUsername($TelegramRequest->getUser()->getUsername());
            }

            $this->accountTelegramHandler->handle($AccountTelegramDTO);

            $response = $this
                ->sendMessage
                ->delete([
                    $TelegramRequest->getLast(),
                    $TelegramRequest->getSystem(),
                    $TelegramRequest->getId(),
                ])
                ->chanel($TelegramRequest->getChatId())
                ->message('Ведите свой пароль')
                ->send();

//            /** Сохраняем идентификатор системного сообщения */
//            if(isset($response['result']['message_id']))
//            {
//                $this->saveSystemMessage($TelegramRequest->getChatId(), $response['result']['message_id']);
//            }


            $this->logger->info('Добавили AccountTelegram с указанным Email для ввода пароля', [__FILE__.':'.__LINE__, $TelegramRequest->getText()]);
            return;
        }

        $response = $this
            ->sendMessage
            ->delete([
                $TelegramRequest->getLast(),
                $TelegramRequest->getSystem(),
                $TelegramRequest->getId(),
            ])
            ->chanel($TelegramRequest->getChatId())
            ->message('Отправьте свой E-mail, с которого Вы регистрировались')
            ->send();

//        /** Сохраняем идентификатор системного сообщения */
//        if(isset($response['result']['message_id']))
//        {
//            $this->saveSystemMessage($TelegramRequest->getChatId(), $response['result']['message_id']);
//        }

        $this->logger->info('Отправили сообщение с требованием E-mail', [__FILE__.':'.__LINE__, $TelegramRequest->getText()]);
    }

//    private function saveSystemMessage(int $chat, int $id): void
//    {
//        $systemItem = $this->cache->getItem('system-'.$chat);
//        $systemItem->set($id);
//        $this->cache->save($systemItem);
//    }

}

