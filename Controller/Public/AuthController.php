<?php
/*
 *  Copyright 2025.  Baks.dev <admin@baks.dev>
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

namespace BaksDev\Auth\Telegram\Controller\Public;


use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\UseCase\User\Auth\TelegramAuthDTO;
use BaksDev\Auth\Telegram\UseCase\User\Auth\TelegramAuthForm;
use BaksDev\Barcode\Writer\BarcodeFormat;
use BaksDev\Barcode\Writer\BarcodeType;
use BaksDev\Barcode\Writer\BarcodeWrite;
use BaksDev\Core\Cache\AppCacheInterface;
use BaksDev\Core\Controller\AbstractController;
use BaksDev\Core\Form\Search\SearchDTO;
use BaksDev\Core\Form\Search\SearchForm;
use BaksDev\Core\Listeners\Event\Security\RoleSecurity;
use BaksDev\Telegram\Bot\Repository\UsersTableTelegramSettings\TelegramBotSettingsInterface;
use chillerlan\QRCode\QRCode;
use DateInterval;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
final class AuthController extends AbstractController
{
    #[Route('/telegram/auth', name: 'public.auth', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        AppCacheInterface $appCache,
        TelegramBotSettingsInterface $settings,
        BarcodeWrite $BarcodeWrite,
        int $page = 0,
    ): Response
    {
        if($this->getUsr())
        {
            /* Редирект на главную страницу */
            return $this->redirectToRoute('core:public.homepage');
        }

        $form = $this->createForm(
            TelegramAuthForm::class,
            new TelegramAuthDTO(),
            ['action' => $this->generateUrl('auth-telegram:public.auth'),]
        );

        $Session = $request->getSession();
        $key = $request->getClientIp();
        $code = $Session->get($key);


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

        $BarcodeWrite
            ->text($code['qr'])
            ->type(BarcodeType::QRCode)
            ->format(BarcodeFormat::SVG)
            ->generate();

        if(false === $BarcodeWrite)
        {
            /**
             * Проверить права на исполнение
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Writer/Generate
             * chmod +x /home/bundles.baks.dev/vendor/baks-dev/barcode/Reader/Decode
             * */
            throw new RuntimeException('Barcode write error');
        }

        $QRCode = $BarcodeWrite->render();
        $BarcodeWrite->remove();

        return $this->render(
            [
                'form' => $form->createView(),
                'qrcode' => $QRCode,
                'lifetime' => ($code['lifetime'] - time()),
                'url' => $settings->settings() ? $settings->settings()->getUrl() : '#', // ссылка на Telegram Bot

            ]
        );
    }
}
