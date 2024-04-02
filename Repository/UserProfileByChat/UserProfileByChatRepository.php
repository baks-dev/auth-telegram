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

namespace BaksDev\Auth\Telegram\Repository\UserProfileByChat;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\Personal\UserProfilePersonal;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;

final class UserProfileByChatRepository implements UserProfileByChatInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Возвращает Username профиля пользователя по идентификатору чата
     */
    public function getUserProfileNameByChat(string $chat): mixed
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->select('personal.username');

        $qb->from(AccountTelegramEvent::TABLE, 'chat_event');

        $qb->join(
            'chat_event',
            AccountTelegram::TABLE,
            'chat',
            'chat.event = chat_event.id'
        );

        $qb->leftJoin(
            'chat_event',
            UserProfileInfo::TABLE,
            'info',
            'info.usr = chat_event.account
            ');

        $qb->leftJoin(
            'info',
            UserProfile::TABLE,
            'profile',
            'profile.id = info.profile
            ');

        $qb->leftJoin(
            'profile',
            UserProfilePersonal::TABLE,
            'personal',
            'personal.event = profile.event'
        );

        $qb->where('chat_event.chat = :chat')
            ->setParameter('chat', $chat);

        return $qb
            ->enableCache('auth-telegram')
            ->fetchOne();

    }
}