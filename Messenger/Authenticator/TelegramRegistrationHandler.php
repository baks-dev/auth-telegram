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
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\AccountTelegramRegistrationDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\AccountTelegramRegistrationHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Manufacture\Part\Telegram\Type\ManufacturePartDone;
use BaksDev\Telegram\Api\TelegramDeleteMessages;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use BaksDev\Users\Profile\TypeProfile\Type\Id\TypeProfileUid;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler(priority: 1)]
final class TelegramRegistrationHandler
{
    private LoggerInterface $logger;
    private AppCacheInterface $cache;
    private AccountTelegramEventInterface $accountTelegramEvent;
    private AccountTelegramRegistrationHandler $telegramRegistrationHandler;
    private TelegramSendMessages $telegramSendMessage;
    private UrlGeneratorInterface $urlGenerator;


    public function __construct(
        LoggerInterface $authTelegramLogger,
        AccountTelegramEventInterface $accountTelegramEvent,
        AccountTelegramRegistrationHandler $telegramRegistrationHandler,
        AppCacheInterface $appCache,
        TelegramSendMessages $telegramSendMessage,
        UrlGeneratorInterface $urlGenerator
    )
    {
        $this->logger = $authTelegramLogger;
        $this->cache = $appCache;
        $this->accountTelegramEvent = $accountTelegramEvent;
        $this->telegramRegistrationHandler = $telegramRegistrationHandler;
        $this->telegramSendMessage = $telegramSendMessage;
        $this->urlGenerator = $urlGenerator;
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


        /* Отправляем сообщение пользователю для заполнения профиля */

        $message = '<b>Регистрация нового пользователя</b>';
        $message .= PHP_EOL;
        $message .= PHP_EOL;
        $message .= 'После проверки проверочного кода и авторизации ОБЯЗАТЕЛЬНО необходимо заполнить свой профиль для дальнейшей работы: ';
        $message .= PHP_EOL;

        $menu[] = [
            'text' => 'Добавить профиль',
            'url' => $this->urlGenerator->generate('users-profile-user:user.index', referenceType: UrlGenerator::ABSOLUTE_URL)
        ];

        $markup = json_encode([
            'inline_keyboard' => array_chunk($menu, 1),
        ]);

        $this
            ->telegramSendMessage
            ->chanel($TelegramRequest->getChatId())
            ->markup($markup)
            ->message($message)
            ->send();


        $this->logger->info('Добавили нового пользователя AccountTelegram', [
            self::class.':'.__LINE__,
            'chat' => $TelegramRequest->getChatId()
        ]);
    }
}

