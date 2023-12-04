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

namespace BaksDev\Auth\Telegram\Repository\ExistAccountTelegram;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusActive;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class ExistAccountTelegram implements ExistAccountTelegramInterface
{

    private Connection $connection;

    public function __construct(
        Connection $connection,
    )
    {
        $this->connection = $connection;
    }

    /**
     * Метод проверяет, имеется ли зарегистрированный чат
     */
    public function isExistAccountTelegram(int $chat): bool
    {
        $qbExist = $this->connection->createQueryBuilder();

        $qbExist->select('1');

        $qbExist->from(AccountTelegramEvent::TABLE, 'event');

        $qbExist->join(
            'event',
            AccountTelegram::TABLE,
            'account',
            'account.event = event.id'
        );

        $qbExist->where('event.chat = :chat');
        $qbExist->setParameter('chat', $chat, ParameterType::INTEGER);

        $qbExist->andWhere('event.status = :status');
        $qbExist->setParameter('status', new AccountTelegramStatus(new AccountTelegramStatusActive()), AccountTelegramStatus::TYPE);


        $qb = $this->connection->createQueryBuilder();
        $qb->select(sprintf('EXISTS(%s)', $qbExist->getSQL()));
        $qb->setParameters($qbExist->getParameters());

        return $qb->fetchOne();
    }
}
