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

namespace BaksDev\Auth\Telegram\UseCase\Admin\Remove;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Core\Doctrine\ORMQueryBuilder;
use BaksDev\Users\User\Type\Id\UserUid;

final class AccountTelegramRemoveHandler
{
    private ORMQueryBuilder $ORMQueryBuilder;

    public function __construct(ORMQueryBuilder $ORMQueryBuilder)
    {
        $this->ORMQueryBuilder = $ORMQueryBuilder;
    }

    public function handle(AccountTelegramRemoveDTO $command) : void
    {
        $em = $this->ORMQueryBuilder->getEntityManager();

        $main = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $main
            ->select('main')
            ->from(AccountTelegram::class, 'main')
            ->where('main.id = :id')
            ->setParameter('id', $command->getUsr(), UserUid::TYPE)
            ->getOneOrNullResult();

        if($main)
        {
            $em->remove($main);
        }

        $events = $this->ORMQueryBuilder->createQueryBuilder(self::class);

        $events
            ->select('event')
            ->from(AccountTelegramEvent::class, 'event')
            ->where('event.account = :account')
            ->setParameter('account', $command->getUsr(), UserUid::TYPE)
            ->getResult();

        if($events)
        {
            foreach($events as $event)
            {
                $em->remove($event);
            }
        }

        $em->flush();
    }
}