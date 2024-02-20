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

namespace BaksDev\Auth\Telegram\Repository\ActiveProfileByAccountTelegram;


use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\Status\UserProfileStatusActive;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\UserProfileStatus;

final class ActiveProfileByAccountTelegram implements ActiveProfileByAccountTelegramInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    /**
     * Метод возвращает активный идентификатор пользователя идентификатора чата
     */

    public function findByChat(int|string $chat): ?UserProfileUid
    {
        $qb = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $qb->select('profile.id');
        $qb->from(AccountTelegramEvent::TABLE, 'telegram_event');

        $qb->where('telegram_event.chat = :chat');
        $qb->setParameter('chat', (string) $chat);

        $qb->andWhere('telegram_event.status = :telegram_status');
        $qb->setParameter(
            'telegram_status',
            new AccountTelegramStatus(new AccountTelegramStatus\AccountTelegramStatusActive()),
            AccountTelegramStatus::TYPE);

        $qb->join(
            'telegram_event',
            UserProfileInfo::TABLE,
            'profile_info',
            '
                profile_info.usr = telegram_event.account AND 
                profile_info.status = :profile_status AND 
                profile_info.active = true
            ');

        $qb->setParameter('profile_status', new UserProfileStatus(UserProfileStatusActive::class), UserProfileStatus::TYPE);

        $qb->join(
            'profile_info',
            UserProfile::TABLE,
            'profile',
            'profile.id = profile_info.profile');


        

       $profile =  $qb->enableCache('auth-telegram', 86400)->fetchOne();

       return $profile ? new UserProfileUid($profile) : null;

    }
}