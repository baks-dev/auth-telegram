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

namespace BaksDev\Auth\Telegram\Repository\ActiveUserTelegramAccount;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusActive;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusBlock;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\UserProfile\Entity\Event\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\Status\UserProfileStatusActive;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\UserProfileStatus;
use BaksDev\Users\User\Type\Id\UserUid;

final class ActiveUserTelegramAccountRepository implements ActiveUserTelegramAccountInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Метод возвращает идентификатор пользователя UserUid по идентификатору чата
     */
    public function findByChat(int|string $chat): ?UserUid
    {

        $chat = (string) $chat;

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('event.account')
            ->from(AccountTelegramEvent::class, 'event');

        $dbal->where('event.chat = :chat')
            ->setParameter('chat', $chat);

        $dbal
            ->andWhere('event.status = :telegram_status')
            ->setParameter(
                'telegram_status',
                new AccountTelegramStatus(AccountTelegramStatusActive::class),
                AccountTelegramStatus::TYPE
            );

        $dbal->join(
            'event',
            AccountTelegram::class,
            'main',
            'main.event = event.id'
        );

        $exist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $exist->select('profile_info.profile');
        $exist->from(UserProfileInfo::class, 'profile_info');
        $exist->where('profile_info.usr = event.account');
        $exist->andWhere('profile_info.status = :profile_status');
        $exist->andWhere('profile_info.active = true');

        $exist->join(
            'profile_info',
            UserProfile::class,
            'profile',
            'profile.id = profile_info.profile'
        );


        $dbal->setParameter(
            'profile_status',
            UserProfileStatusActive::class,
            UserProfileStatus::TYPE
        );


        $dbal->andWhere('EXISTS('.$exist->getSQL().')');

        $result = $dbal->enableCache('auth-telegram', 3600)->fetchOne();

        return $result ? new UserUid($result) : null;
    }


    /**
     * Метод возвращает идентификатор пользователя по активному идентификатору события
     */
    public function findByEvent(AccountTelegramEventUid|string $event): ?UserUid
    {
        if(is_string($event))
        {
            $event = new AccountTelegramEventUid($event);
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('event.account')
            ->from(AccountTelegramEvent::class, 'event');

        $dbal->where('event.id = :event')
            ->setParameter('event', $event, AccountTelegramEventUid::TYPE);

        $dbal->andWhere('event.status != :telegram_status')
            ->setParameter(
                'telegram_status',
                new AccountTelegramStatus(new AccountTelegramStatusBlock()),
                AccountTelegramStatus::TYPE
            );

        $dbal->join(
            'event',
            AccountTelegram::class,
            'main',
            'main.event = event.id'
        );

        /**  Проверяем, что имеется активный профиль */
        //        $exist = $this->DBALQueryBuilder->createQueryBuilder(self::class);
        //
        //        $exist->select('1');
        //        $exist->from(UserProfileInfo::class, 'profile_info');
        //        $exist->where('profile_info.usr = event.account');
        //        $exist->andWhere('profile_info.status = :profile_status');
        //        $exist->andWhere('profile_info.active = true');
        //
        //        $exist->join(
        //            'profile_info',
        //            UserProfile::class,
        //            'profile',
        //            'profile.id = profile_info.profile');
        //
        //
        //        $dbal->setParameter('profile_status',
        //            new UserProfileStatus(new UserProfileStatusActive()),
        //            UserProfileStatus::TYPE);
        //
        //        $dbal->andWhere('EXISTS('.$exist->getSQL().')');


        $result = $dbal->enableCache('auth-telegram', 3600)->fetchOne();

        return $result ? new UserUid($result) : null;
    }

}
