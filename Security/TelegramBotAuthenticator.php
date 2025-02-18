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

use BaksDev\Auth\Telegram\Messenger\RegistrationEmail\TelegramRegistrationEmailMessage;
use BaksDev\Auth\Telegram\Repository\ActiveUserTelegramAccount\ActiveUserTelegramAccountInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\TelegramBotSettingsInterface;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Users\User\Repository\GetUserById\GetUserByIdInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Translation\LocaleSwitcher;

final class TelegramBotAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        #[Target('telegramLogger')] private readonly LoggerInterface $logger,
        private readonly TelegramRequest $telegramRequest,
        private readonly TelegramBotSettingsInterface $telegramBotSettings,
        private readonly ActiveUserTelegramAccountInterface $ActiveUserTelegramAccount,
        private readonly GetUserByIdInterface $userById,
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly MessageDispatchInterface $messageDispatch,
    ) {}

    public function supports(Request $request): ?bool
    {
        /** Проверяем авторизацию телеграмм-бота */
        $this->telegramBotSettings->settings();
        return $this->telegramBotSettings->equalsSecret($request->headers->get('X-Telegram-Bot-Api-Secret-Token'));
    }

    public function authenticate(Request $request): Passport
    {
        /** Получаем паспорт телеграм-бота */
        return new SelfValidatingPassport(
            new UserBadge('telegram_bot_authenticator', function() {

                $TelegramRequest = $this->telegramRequest->request();

                if(!$TelegramRequest)
                {
                    $this->logger->warning('Нераспознанный запрос Request');
                    return null;
                }

                /**
                 * Авторизуем пользователя по идентификатору чата
                 * Пользователь должен быть зарегистрирован через телеграм-бот
                 */

                $UserUid = $this->ActiveUserTelegramAccount
                    ->findByChat($TelegramRequest->getChatId());

                if($UserUid === null)
                {
                    $this->logger->warning('Идентификатор авторизованного пользователя не найден');
                    $this->logger->warning('Проверьте, заполнен ли пользователем профиль');
                    return null;
                }

                $this->logger->info(sprintf('Пользователь c идентификатором %s авторизован', $UserUid));

                /* Устанавливаем локаль согласно чату */
                $this->localeSwitcher->setLocale($TelegramRequest->getLocale()->getLocalValue());

                return $this->userById->get($UserUid);

            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }


    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if($this->telegramRequest->request())
        {
            /** Отправляем сообщение для регистрации */
            $this->messageDispatch->dispatch(
                new TelegramRegistrationEmailMessage($this->telegramRequest->request()),
                transport: 'auth-telegram'
            );
        }

        return null;

        /** Если принимаем запросы только от авторизованных пользователей */
        // return new JsonResponse(['Authentication' => 'failure']);
    }
}
