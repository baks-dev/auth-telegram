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

namespace BaksDev\Auth\Telegram\Security;

use BaksDev\Auth\Telegram\Bot\Login\Email\LoginTelegram;
use BaksDev\Auth\Telegram\Bot\Login\Email\Steps\LoginStepSuccess;
use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Repository\ActiveAccountEventByChat\ActiveAccountEventByChatInterface;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Telegram\Api\TelegramDeleteMessage;
use BaksDev\Telegram\Api\TelegramSendMessage;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\GetTelegramBotSettingsInterface;
use BaksDev\Users\User\Repository\GetUserById\GetUserByIdInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Cache\CacheInterface;

final class TelegramBotAuthenticator extends AbstractAuthenticator
{
    private GetTelegramBotSettingsInterface $telegramBotSettings;
    private ActiveAccountEventByChatInterface $activeAccountEventByChat;
    private GetUserByIdInterface $userById;
    private LoginTelegram $loginTelegram;
    private LocaleSwitcher $localeSwitcher;
    private AccountTelegramHandler $accountTelegramHandler;
    private TelegramSendMessage $sendMessage;
    private TelegramDeleteMessage $deleteMessage;

    /**
     * Идентификатор чата
     */
    private ?string $chat = null;

    /**
     * Текст полученного сообщения
     */
    private ?string $text = null;

    /**
     * Идентификатор полученного сообщения
     */
    private ?int $id = null;

    /**
     * Идентификатор предыдущего сообщения (для удаления)
     */
    private mixed $last = null;

    /**
     * Кешированные данные
     */
    private ?array $data = null;

    private LoggerInterface $logger;

    private CacheInterface $cache;


    public function __construct(
        GetTelegramBotSettingsInterface $telegramBotSettings,
        ActiveAccountEventByChatInterface $activeAccountEventByChat,
        GetUserByIdInterface $userById,
        LoginTelegram $loginTelegram,
        LocaleSwitcher $localeSwitcher,
        AccountTelegramHandler $accountTelegramHandler,
        TelegramSendMessage $sendMessage,
        TelegramDeleteMessage $deleteMessage,
        LoggerInterface $logger,
        AppCacheInterface $cache
    )
    {

        $this->telegramBotSettings = $telegramBotSettings;
        $this->activeAccountEventByChat = $activeAccountEventByChat;
        $this->userById = $userById;
        $this->loginTelegram = $loginTelegram;
        $this->localeSwitcher = $localeSwitcher;
        $this->accountTelegramHandler = $accountTelegramHandler;
        $this->sendMessage = $sendMessage;
        $this->deleteMessage = $deleteMessage;
        $this->logger = $logger;
        $this->cache = $cache->init('telegram-bot');
    }

    public function supports(Request $request): ?bool
    {
        $this->telegramBotSettings->settings();
        return $this->telegramBotSettings->equalsSecret($request->headers->get('X-Telegram-Bot-Api-Secret-Token'));
    }


    public function authenticate(Request $request): Passport
    {

        /** Получаем паспорт */
        return new SelfValidatingPassport(
            new UserBadge('telegram_bot_authenticator', function() use ($request) {


                $content = json_decode($request->getContent(), true);

                if(!$content)
                {
                    return null;
                }


                if(isset($content['callback_query']))
                {
                    if(
                        !isset(
                            $content['callback_query']['from']['id'], // идентификатор чата
                            $content['callback_query']['message']['message_id'] // идентификатор сообщения
                        ) || $content['callback_query']['from']["is_bot"] // если бот
                    )
                    {
                        return null;
                    }

                    $language = $content['callback_query']['from']['language_code'] ?? 'ru';

                    $this->chat = (string) $content['callback_query']['from']['id'];
                    $this->id = $content['callback_query']['message']['message_id'];
                    $this->text = $content['callback_query']['data'];

                }

                else
                {
                    if(
                        !isset(
                            $content['message']['from']['id'], // идентификатор чата
                            $content['message']['message_id'] // идентификатор сообщения
                        ) || $content['message']['from']["is_bot"]
                    )
                    {
                        return null;
                    }

                    $language = $content['message']['from']['language_code'] ?? 'ru';

                    $this->chat = (string) $content['message']['from']['id']; // идентификатор чата
                    $this->id = $content['message']['message_id']; // идентификатор сообщения
                    $this->text = $content['message']['text'] ?? null; // текст полученного сообщения
                }


                $this->last = ($this->cache->getItem('last-'.$this->chat))->get();


                /** Указываем установки подключения бота */
                $this->sendMessage
                    ->token($this->telegramBotSettings->getToken())
                    ->chanel($this->chat);

                $this->deleteMessage
                    ->token($this->telegramBotSettings->getToken())
                    ->chanel($this->chat);



                /* Всегда удаляем сообщение пользователя из чата */
                $this->deleteMessage
                    ->delete($this->id)
                    ->send();


                /* Всегда удаляем предыдущее системное сообщение из чата */
                if($this->last)
                {
                    $this->deleteMessage
                        ->delete($this->last)
                        ->send();
                }

                /* Сохраняем сообщение пользователя для последующего удаления */
                $lastMessage = $this->cache->getItem('last-'.$this->chat);
                $lastMessage->set($this->id);
                $this->cache->save($lastMessage);


                /* Устанавливаем локаль согласно чату */
                $this->localeSwitcher->setLocale($language);

                /* Получаем активный идентификатор пользователя по идентификатору чата */
                $UserUid = $this->activeAccountEventByChat->getActiveAccountOrNullResultByChat($this->chat);

                if($UserUid === null)
                {
                    $this->logger->critical('Ошибка авторизации пользователя!', $content);
                    return null;
                }

                return $this->userById->get($UserUid);
            })
        );
    }


    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    /**
     * Авторизация пользователя
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if(!$this->chat)
        {

            return new JsonResponse(['Authentication' => 'failure']);
        }

        $content = json_decode($request->getContent(), true);

        $this->loginTelegram
            ->chat($this->chat)
            ->handle($this->text);

        /** Сохраняем пользователя в случае успешной авторизации */
        if($this->loginTelegram->getStep() instanceof LoginStepSuccess)
        {
            $AccountTelegramDTO = new AccountTelegramDTO();
            $AccountTelegramDTO->setAccount($this->loginTelegram->getStep()->getUsr());
            $AccountTelegramDTO->setChat($this->chat);

            if(isset($content['message']['chat']['username']))
            {
                $AccountTelegramDTO->setUsername($content['message']['chat']['username']);
            }

            if(isset($content['message']['chat']['first_name']))
            {
                $AccountTelegramDTO->setFirstname($content['message']['chat']['first_name']);
            }

            if(empty($AccountTelegramDTO->getFirstname()) && empty($AccountTelegramDTO->getUsername()))
            {
                $this->sendMessageError('Unknown user');
                return new JsonResponse(['Authentication' => 'failure']);
            }

            $AccountTelegram = $this->accountTelegramHandler->handle($AccountTelegramDTO);

            if(!$AccountTelegram instanceof AccountTelegram)
            {
                $this->sendMessageError($AccountTelegram);
                return new JsonResponse(['Authentication' => 'failure']);
            }

        }


        /**
         * Отправляем сообщение авторизации пользователю
         */
        $LoginTelegramSendMessage = $this->loginTelegram->getSendMessage();

        if($LoginTelegramSendMessage)
        {
            $response = $LoginTelegramSendMessage->send(false);

            if($response)
            {
                /** Сохраняем последнее сообщение */
                $lastMessage = $this->cache->getItem('last-'.$this->chat);
                $lastMessage->set($response['result']['message_id']);
                $this->cache->save($lastMessage);

            }
        }

        return new JsonResponse(['Authentication' => 'failure']);
    }

    /**
     * Сообщение об ошибке авторизации
     */
    public function sendMessageError(string $code): void
    {
        $this->sendMessage
            ->message(sprintf('%s: Ошибка авторизации', $code))
            ->send();
    }

}