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

namespace BaksDev\Auth\Telegram\Repository\AccountTelegramAdmin;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\Group\BaksDevUsersProfileGroupBundle;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\Group\Type\Prefix\Group\GroupPrefix;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;

final class AccountTelegramAdminRepository implements AccountTelegramAdminInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }


    /**
     * Метод возвращает идентификатор чата системного администратора
     */
    public function find(): ?string
    {
        if(!class_exists(BaksDevUsersProfileGroupBundle::class))
        {
            return null;
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        //$dbal->select('*');
        $dbal
            ->from(ProfileGroupUsers::class, 'group_profile')
            ->where('group_profile.prefix = :prefix')
            ->setParameter('prefix', new GroupPrefix('ROLE_ADMIN'), GroupPrefix::TYPE);


        $dbal->join(
            'group_profile',
            UserProfileInfo::class,
            'profile',
            'profile.profile = group_profile.profile'
        );

        $dbal->join(
            'profile',
            AccountTelegram::class,
            'telegram',
            'telegram.id = profile.usr'
        );

        $dbal
            ->join(
                'telegram',
                AccountTelegramEvent::class,
                'telegram_event',
                'telegram_event.id = telegram.event'
            );


        $dbal->select('telegram_event.chat');


        return (string) $dbal
            ->enableCache('auth-telegram', 86400)
            ->fetchOne() ?: null;
    }
}