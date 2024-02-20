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

namespace BaksDev\Auth\Telegram\Controller\User;


use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\UseCase\User\Auth\TelegramAuthDTO;
use BaksDev\Auth\Telegram\UseCase\User\Auth\TelegramAuthForm;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use chillerlan\QRCode\QRCode;
use DateInterval;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BaksDev\Core\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
final class AuthController extends AbstractController
{
    #[Route('/telegram/auth', name: 'user.auth', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AppCacheInterface $appCache,
        int $page = 0,
    ): Response
    {

        if($this->getUsr())
        {
            /* Редирект на главную страницу */
            return $this->redirectToRoute('core:user.homepage');
        }


        $form = $this->createForm(
            TelegramAuthForm::class,
            new TelegramAuthDTO(),
            ['action' => $this->generateUrl('auth-telegram:user.auth'),]
        );

        $Session = $request->getSession();
        $key = $request->getClientIp();
        $code = $Session->get($key);


        //dd($form->isSubmitted());

        //        if($form->isSubmitted() && $form->isValid() && $form->has('telegram_auth'))
        //        {
        //            if(!$code || $code['lifetime'] < time() || $code['code'] !== $TelegramAuthDTO->getCode())
        //            {
        //                $this->addFlash
        //                (
        //                    'page.index',
        //                    'danger.code',
        //                    'auth-telegram.user'
        //                );
        //
        //                return $this->redirectToReferer();
        //            }
        //
        //            /** Удаляем проверочный код */
        //            $Session->invalidate();
        //            $cache->delete((string) $code['code']);
        //
        //            $this->addFlash
        //            (
        //                'page.index',
        //                'success.code',
        //                'auth-telegram.user'
        //            );
        //
        //            return $this->redirectToReferer();
        //        }


        if(!$code || $code['lifetime'] < time())
        {
            $Session->invalidate();

            $qr = (string) new AccountTelegramEventUid();
            $number = (string) random_int(1000, 9999);

            $Session->set($key, [
                'qr' => $qr,
                'code' => $number,
                'lifetime' => (time() + 60) // срок действия 1 минута
            ]);

            /** @var ApcuAdapter $cache */
            $cache = $appCache->init();
            $cacheItem = $cache->getItem($qr);
            $cacheItem->set($number);
            $cacheItem->expiresAfter(DateInterval::createFromDateString('1 minutes + 10 seconds'));
            $cache->save($cacheItem);

            $code = $Session->get($key);
        }


        ///** @var ApcuAdapter $cache */

        //$cache = $appCache->init($request->getClientIp());
        //$cacheItem = $cache->getItem($request->getClientIp());


        //$cacheItem = $cache->get($request->getClientIp());


        // Фильтр
        // $filter = new ProductsStocksFilterDTO($request, $ROLE_ADMIN ? null : $this->getProfileUid());
        // $filterForm = $this->createForm(ProductsStocksFilterForm::class, $filter);
        // $filterForm->handleRequest($request);

        // Получаем список
        //$TelegtamAccount = $allTelegtamAccount->fetchAllTelegtamAccountAssociative($search);


        //        $cacheItem = $cache->getItem($code['qr']);
        //        dump($cacheItem->get());
               dump($code);

        return $this->render(
            [
                'form' => $form->createView(),
                'qrcode' => (new QRCode())->render($code['qr']),
                'lifetime' => ($code['lifetime'] - time())

            ]
        );
    }
}
