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

namespace BaksDev\Auth\Telegram\Controller\Admin;

use BaksDev\Auth\Email\Entity\Event\AccountEvent;
use BaksDev\Auth\Email\Repository\AccountEventActiveByEmail\AccountEventActiveByEmailInterface;
use BaksDev\Auth\Email\Repository\AccountEventByUser\AccountEventByUserInterface;
use BaksDev\Auth\Email\UseCase\Admin\ReplaceAccount\ReplaceAccountDTO;
use BaksDev\Auth\Email\UseCase\Admin\ReplaceAccount\ReplaceAccountHandler;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\UseCase\Admin\Attach\AttachAccountDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\Attach\AttachAccountForm;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;


/**
 * Присваивает идентификатор из аккаунта телеграм в аккаунт email
 */
#[AsController]
#[RoleSecurity('ROLE_ACCOUNT_TELEGRAM_EDIT')]
final class AttachAccountController extends AbstractController
{
    #[Route('/admin/account/telegram/attach/{id}', name: 'admin.attach', methods: ['GET', 'POST'])]
    public function edit(
        #[Target('authTelegramLogger')] LoggerInterface $logger,
        Request $request,
        #[MapEntity] AccountTelegramEvent $AccountTelegramEvent,
        AccountEventByUserInterface $accountEventByUserRepository,
        AccountEventActiveByEmailInterface $accountEventActiveByEmailRepository,
        ReplaceAccountHandler $editAccountHandler,
    ): Response
    {
        $AttachAccountDTO = new AttachAccountDTO();
        $AccountTelegramEvent->getDto($AttachAccountDTO);

        // Форма
        $form = $this->createForm(
            type: AttachAccountForm::class,
            data: $AttachAccountDTO,
            options: [
                'action' => $this->generateUrl('auth-telegram:admin.attach',
                    ['id' => $AttachAccountDTO->getEvent()]
                )
            ])
            ->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('attach_account'))
        {
            $this->refreshTokenForm($form);

            $account = $accountEventByUserRepository
                ->forUser($AttachAccountDTO->getAccount())
                ->find();


            if(true === $account instanceof AccountEvent)
            {
                $logger->warning(
                    'account_telegram уже присутствует в users_account',
                    [self::class.':'.__LINE__],
                );

                $this->addFlash
                (
                    'Объединение с E-mail аккаунтом',
                    'Телеграм аккаунт уже объединен с e-mail аккуратном '.$account->getEmail()->getValue(),
                );

                return $this->redirectToRoute('auth-telegram:admin.index');
            }

            $AccountEvent = $accountEventActiveByEmailRepository
                ->getAccountEvent($AttachAccountDTO->getEmail());

            if(false === $AccountEvent instanceof AccountEvent)
            {
                $this->addFlash
                (
                    'Объединение с E-mail аккаунтом',
                    'Не найден аккаунт по e-mail',
                );

                return $this->redirectToRoute('auth-telegram:admin.index');
            }


            $EditAccountDTO = new ReplaceAccountDTO();
            $AccountEvent->getDto($EditAccountDTO);


            if(true === $EditAccountDTO->getAccount()->equals($AttachAccountDTO->getAccount()))
            {
                $this->addFlash
                (
                    'Объединение с E-mail аккаунтом',
                    'Телеграм аккаунт уже объединен с e-mail аккуратном '.$account->getEmail()->getValue(),
                );

                return $this->redirectToRoute('auth-telegram:admin.index');
            }

            /**
             * Присваиваем email аккаунту id из telegram аккаунта
             */
            $EditAccountDTO->setAccount($AttachAccountDTO->getAccount());

            $handle = $editAccountHandler->handle($EditAccountDTO);

            $this->addFlash
            (
                'Объединение с E-mail аккаунтом',
                $handle
                    ? 'Успешно объединен с e-mail аккаунтом '.$AttachAccountDTO->getEmail()
                    : 'Ошибка при объединении с e-mail аккаунтом '.$AttachAccountDTO->getEmail()
            );

            return $this->redirectToRoute('auth-telegram:admin.index');
        }

        return $this->render(['form' => $form->createView()]);
    }
}
