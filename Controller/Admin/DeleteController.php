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


use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\UseCase\Admin\Delete\AccountTelegramDeleteDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\Delete\AccountTelegramDeleteForm;
use BaksDev\Auth\Telegram\UseCase\Admin\Delete\AccountTelegramDeleteHandler;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
#[RoleSecurity('ROLE_ACCOUNT_TELEGRAM_DELETE')]
final class DeleteController extends AbstractController
{
    #[Route('/admin/account/telegram/delete/{id}', name: 'admin.delete', methods: ['GET', 'POST'])]
    public function delete(
        Request $request,
        #[MapEntity] AccountTelegramEvent $AccountTelegramEvent,
        AccountTelegramDeleteHandler $AccountTelegramDeleteHandler,
    ): Response
    {
        $AccountTelegramDeleteDTO = new AccountTelegramDeleteDTO();
        $AccountTelegramEvent->getDto($AccountTelegramDeleteDTO);

        $form = $this->createForm(AccountTelegramDeleteForm::class, $AccountTelegramDeleteDTO, [
            'action' => $this->generateUrl('auth-telegram:admin.delete', ['id' => $AccountTelegramDeleteDTO->getEvent()]),
        ]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid() && $form->has('account_telegram_delete'))
        {
            $handle = $AccountTelegramDeleteHandler->handle($AccountTelegramDeleteDTO);

            $this->addFlash
            (
                'page.delete',
                $handle instanceof AccountTelegram ? 'success.delete' : 'danger.delete',
                'auth-telegram.admin',
                $handle
            );

            return $this->redirectToRoute('auth-telegram:admin.index');
        }

        return $this->render([
            'form' => $form->createView(),
            'name' => $AccountTelegramEvent->getUsername(),
        ]);
    }
}
