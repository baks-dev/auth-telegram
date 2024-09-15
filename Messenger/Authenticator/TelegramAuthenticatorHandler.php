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
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Manufacture\Part\Telegram\Type\ManufacturePartDone;
use BaksDev\Telegram\Api\TelegramDeleteMessages;
use BaksDev\Telegram\Api\TelegramSendMessages;
use BaksDev\Telegram\Bot\Messenger\TelegramEndpointMessage\TelegramEndpointMessage;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Telegram\Request\Type\TelegramRequestCallback;
use BaksDev\Telegram\Request\Type\TelegramRequestIdentifier;
use BaksDev\Telegram\Request\Type\TelegramRequestMessage;
use Psr\Cache\CacheItemInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsMessageHandler(priority: 0)]
final class TelegramAuthenticatorHandler
{
    private LoggerInterface $logger;
    private TelegramSendMessages $telegramSendMessage;
    private AppCacheInterface $cache;
    private AccountTelegramEventInterface $accountTelegramEvent;
    private AccountTelegramAuthenticatorHandler $authenticatorHandler;

    public function __construct(
        LoggerInterface $authTelegramLogger,
        TelegramSendMessages $telegramSendMessage,
        AccountTelegramEventInterface $accountTelegramEvent,
        AccountTelegramAuthenticatorHandler $authenticatorHandler,
        AppCacheInterface $appCache,
    )
    {
        $this->logger = $authTelegramLogger;
        $this->telegramSendMessage = $telegramSendMessage;
        $this->cache = $appCache;
        $this->accountTelegramEvent = $accountTelegramEvent;
        $this->authenticatorHandler = $authenticatorHandler;
    }

    /**
     * Если отправлен QR-код с идентификатором авторизации
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
            $this->logger->warning('Проверочный код отсутствует либо его время действия истекло', [
                self::class.':'.__LINE__,
                'chat' => $TelegramRequest->getChatId(),
                'identifier' => $TelegramRequest->getIdentifier(),
                'data' => $cacheItem->get()
            ]);

            return;
        }

        $this
            ->telegramSendMessage
            ->chanel($TelegramRequest->getChatId());

        $AccountTelegramEvent = $this->accountTelegramEvent->findByChat($TelegramRequest->getChatId());

        if(!$AccountTelegramEvent)
        {
            $this
                ->telegramSendMessage
                ->delete([
                    $TelegramRequest->getLast(),
                    $TelegramRequest->getId(),
                ])
                ->message('Ошибка авторизации пользователя Telegram')
                ->send();

            $this->logger->info('Пользователь AccountTelegram не найден', [
                self::class.':'.__LINE__,
                'chat' => $TelegramRequest->getChatId()
            ]);

            return;
        }

        $AccountTelegramEventUid = new AccountTelegramEventUid($TelegramRequest->getIdentifier());
        $AuthenticatorDTO = new AccountTelegramAuthenticatorDTO($AccountTelegramEventUid);
        $AccountTelegramEvent->getDto($AuthenticatorDTO);

        if($AuthenticatorDTO->getStatus()->equals(AccountTelegramStatusBlock::class))
        {
            $this
                ->telegramSendMessage
                ->delete([
                    $TelegramRequest->getLast(),
                    $TelegramRequest->getId(),
                ])
                ->message('Ошибка авторизации пользователя Telegram')
                ->send();

            $this->logger->warning('Пользователь заблокирован!', [
                self::class.':'.__LINE__,
                'chat' => $TelegramRequest->getChatId()
            ]);

            return;
        }


        $handle = $this->authenticatorHandler->handle($AuthenticatorDTO);

        if(!$handle instanceof AccountTelegram)
        {
            $text = sprintf('%s: Ошибка авторизации пользователя Telegram', $handle);

            $this
                ->telegramSendMessage
                ->delete([
                    $TelegramRequest->getLast(),
                    $TelegramRequest->getId(),
                ])
                ->message($text)
                ->send();

            $this->logger->critical($text, [self::class.':'.__LINE__]);

            return;
        }


        /* Отправляем проверочный код в сообщении пользователю  */

        $message = 'НИКОМУ не сообщайте!';
        $message .= PHP_EOL;
        $message .= 'Проверочный код:  <b>'.$cacheItem->get().'</b>';
        $message .= PHP_EOL;
        $message .= 'Только МОШЕННИКИ запрашивают коды!';

        $this
            ->telegramSendMessage
            ->delete([
                $TelegramRequest->getLast(),
                $TelegramRequest->getId(),
            ])
            ->markup(null)
            ->message($message)
            ->send();


        /** Удаляем проверочный код от повторного использования QR */
        $cache->delete($TelegramRequest->getIdentifier());

        $this->logger->info('Пользователю отправлен проверочный код ', [
            self::class.':'.__LINE__,
            'chat' => $TelegramRequest->getChatId()
        ]);

    }
}

