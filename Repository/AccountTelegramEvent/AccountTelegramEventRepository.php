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

namespace BaksDev\Auth\Telegram\Repository\AccountTelegramEvent;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Users\User\Type\Id\UserUid;

final class AccountTelegramEventRepository implements AccountTelegramEventInterface
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    /**
     * Метод получает событие по идентификатору чата
     */
    public function findByChat(int|string $chat): ?AccountTelegramEvent
    {
        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $qb
            ->select('event')
            ->from(AccountTelegramEvent::class, 'event')
            ->where('event.chat = :chat')
            ->setParameter('chat', (string) $chat);

        $qb->join(
            AccountTelegram::class,
            'main',
            'WITH',
            'main.event = event.id'
        );

        return $qb->enableCache('auth-telegram', 86400)->getOneOrNullResult();
    }

    /**
     * Метод получает активное событие по идентификатору пользователя
     */
    public function findByUser(UserUid|string $usr): ?AccountTelegramEvent
    {
        if(is_string($usr))
        {
            $usr = new UserUid($usr);
        }

        $qb = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $qb
            ->from(AccountTelegram::class, 'main')
            ->where('main.id = :usr')
            ->setParameter('usr', $usr, UserUid::TYPE);

        $qb
            ->select('event')
            ->join(
                AccountTelegramEvent::class,
                'event',
                'WITH',
                'event.id = main.event'
            );

        return $qb->enableCache('auth-telegram', 86400)->getOneOrNullResult();
    }


}