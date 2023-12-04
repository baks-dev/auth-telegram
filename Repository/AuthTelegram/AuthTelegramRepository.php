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

namespace BaksDev\Auth\Telegram\Repository\AuthTelegram;


use BaksDev\Auth\Telegram\Bot\Login\Email\LoginTelegram;
use BaksDev\Core\Cache\AppCacheInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;


final class AuthTelegramRepository implements AuthTelegramRepositoryInterface
{

    private int $ttl = 300;

    private Serializer $serializer;

    private CacheInterface $cache;

    public function __construct(AppCacheInterface $cache)
    {
        $this->cache = $cache->init('auth-telegram');
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }


    /**
     * Метод получает объект авторизации
     */
    public function getAuthTelegram(LoginTelegram $telegram)
    {
        $key = md5((string) $telegram->getChat());

        $data = $this->cache->get($key, function(ItemInterface $item) use ($telegram) {
            $item->expiresAfter($this->ttl);
            return $telegram->getStep() ? $this->serializer->serialize($telegram->getStep(), 'json') : null;
        });

        if($data === null)
        {
            return $telegram->getStep();
        }


        $class = json_decode($data, false)->nextStep;
        $result = $this->serializer->deserialize($data, $class, 'json');
        $result->setContext($telegram);

        return $result;
    }

    /** Метод Перезаписывает объект авторизации */
    public function setAuthTelegram(LoginTelegram $telegram)
    {
        $key = md5((string) $telegram->getChat());

        $this->cache->delete($key);

        $this->cache->get($key, function(ItemInterface $item) use ($telegram) {
            $item->expiresAfter($this->ttl);
            return $this->serializer->serialize($telegram->getStep(), 'json');
        });

    }
}