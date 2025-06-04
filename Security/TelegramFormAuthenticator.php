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

use BaksDev\Auth\Telegram\Repository\ActiveUserTelegramAccount\ActiveUserTelegramAccountInterface;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\UseCase\User\Auth\TelegramAuthDTO;
use BaksDev\Auth\Telegram\UseCase\User\Auth\TelegramAuthForm;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Messenger\MessageDispatchInterface;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\TelegramBotSettingsInterface;
use BaksDev\Telegram\Request\TelegramRequest;
use BaksDev\Users\User\Repository\GetUserById\GetUserByIdInterface;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TelegramFormAuthenticator extends AbstractAuthenticator
{
    private const string LOGIN_ROUTE = 'auth-telegram:public.auth';

    private const string SUCCESS_REDIRECT = 'core:public.homepage';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ActiveUserTelegramAccountInterface $ActiveUserTelegramAccount,
        private readonly GetUserByIdInterface $userById,
        private readonly FormFactoryInterface $form,
        private readonly TranslatorInterface $translator,
    ) {}

    public function supports(Request $request): ?bool
    {
        /** Проверяем, если авторизация через форму */
        return $request->isMethod('POST') && $this->getAuthFormUrl() === $request->getPathInfo();
    }

    public function authenticate(Request $request): Passport
    {
        $TelegramAuthDTO = new TelegramAuthDTO();
        $form = $this->form->create(TelegramAuthForm::class, $TelegramAuthDTO);
        $form->handleRequest($request);

        $Session = $request->getSession();
        $key = $request->getClientIp();
        $code = $Session->get($key);

        if(!$code || $code['lifetime'] < time() || $code['code'] !== $TelegramAuthDTO->getCode())
        {
            return new SelfValidatingPassport(
                new UserBadge('error', function() {
                    return null;
                }),
            );
        }

        /** Получаем паспорт */
        return new SelfValidatingPassport(
            new UserBadge($code['qr'], function() use ($form, $code, $Session) {

                if($form->isSubmitted() && $form->isValid() && $form->has('telegram_auth'))
                {
                    /**  Авторизуем пользователя по идентификатору события */
                    $AccountTelegramEventUid = new AccountTelegramEventUid((string) $code['qr']);

                    $UserUid = $this->ActiveUserTelegramAccount
                        ->findByEvent($AccountTelegramEventUid);

                    if(false === ($UserUid instanceof UserUid))
                    {
                        return null;
                    }

                    /** Удаляем авторизацию доверенности пользователя */

                    $Session->remove('Authority');
                    return $this->userById->get($UserUid);
                }

                return null;
            }),
            [
                new CsrfTokenBadge(
                    'authenticate',
                    ($request->get('telegram_auth_form'))['_token'],
                ),
            ],
        );

    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        /* если форма отправлена AJAX */
        if($request->isXmlHttpRequest())
        {
            // редирект на страницу refresh
            return new JsonResponse(['status' => 302, 'redirect' => '/refresh']);
        }

        if($targetPath = $request->getSession()->get('_security.'.$firewallName.'.target_path'))
        {
            return new RedirectResponse($targetPath);
        }

        /* Редирект на главную страницу после успешной авторизации */
        return new RedirectResponse($this->urlGenerator->generate(self::SUCCESS_REDIRECT));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if($request->isXmlHttpRequest())
        {
            return new JsonResponse(
                [
                    'header' => $this->translator->trans(
                        'page.index',
                        domain: 'auth-telegram.user',
                    ),
                    'message' => $this->translator->trans(
                        'danger.code',
                        domain: 'auth-telegram.user',
                    ),
                ],
                401,
            );
        }

        return new RedirectResponse($this->getAuthFormUrl());
    }

    protected function getAuthFormUrl(): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
