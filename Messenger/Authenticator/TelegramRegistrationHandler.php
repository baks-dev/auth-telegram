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

namespace BaksDev\Auth\Telegram\Messenger\Authenticator;

use BaksDev\Auth\Email\Repository\AccountEventActiveByEmail\AccountEventActiveByEmailInterface;
use BaksDev\Auth\Email\Type\Email\AccountEmail;
use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusBlock;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusNew;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorHandler;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\AccountTelegramRegistrationDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\AccountTelegramRegistrationHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Manufacture\Part\Telegram\Type\ManufacturePartDone;
use BaksDev\Telegram\Api\TelegramDeleteMessage;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Bundle\SecurityBundle\Security;

#[AsMessageHandler(priority: 0)]
final class TelegramRegistrationHandler
{
    private LoggerInterface $logger;
    private AppCacheInterface $cache;
    private AccountTelegramEventInterface $accountTelegramEvent;
    private AccountTelegramRegistrationHandler $telegramRegistrationHandler;


    public function __construct(
        LoggerInterface $authTelegramLogger,
        AccountTelegramEventInterface $accountTelegramEvent,
        AccountTelegramRegistrationHandler $telegramRegistrationHandler,
        AppCacheInterface $appCache,
    )
    {
        $this->logger = $authTelegramLogger;
        $this->cache = $appCache;
        $this->accountTelegramEvent = $accountTelegramEvent;
        $this->telegramRegistrationHandler = $telegramRegistrationHandler;
    }

    /**
     * Регистрируем нового пользователя если отправлен QR-код с идентификатором авторизации
     */
    public function __invoke(TelegramEndpointMessage $message): void
    {
        $TelegramRequest = $message->getTelegramRequest();

        if(!$TelegramRequest instanceof TelegramRequestIdentifier)
        {
            return;
        }

        /** Только в приватном чате с ботом возможна регистрация */
        if($TelegramRequest->getChat()->getType() !== 'private')
        {
            return;
        }

        /** @var CacheItemInterface $cacheItem */
        $cache = $this->cache->init();
        $cacheItem = $cache->getItem($TelegramRequest->getIdentifier());

        if(!$cacheItem->get())
        {
            return;
        }

        $AccountTelegramEvent = $this->accountTelegramEvent->findByChat($TelegramRequest->getChatId());

        if($AccountTelegramEvent)
        {
            return;
        }

        /**
         * Регистрируем нового пользователя
         */

        $AccountTelegramRegistrationDTO = new AccountTelegramRegistrationDTO($TelegramRequest->getChatId());
        $AccountTelegramRegistrationDTO
            ->setUsername($TelegramRequest->getUser()->getUsername())
            ->setFirstname($TelegramRequest->getUser()->getFirstName());

        $this->telegramRegistrationHandler->handle($AccountTelegramRegistrationDTO);

        $this->logger->info('Добавили нового пользователя AccountTelegram', [
            __FILE__.':'.__LINE__,
            'chat' => $TelegramRequest->getChatId()
        ]);
    }
}

