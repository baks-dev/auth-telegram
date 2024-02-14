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

namespace BaksDev\Auth\Telegram\Repository\ActiveAccountEventByChat;

use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\Status\UserProfileStatusActive;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\UserProfileStatus;
use BaksDev\Users\User\Type\Id\UserUid;

final class ActiveAccountEventByChat implements ActiveAccountEventByChatInterface
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
    public function getActiveAccountOrNullResultByChat(int|string $chat): ?UserUid
    {
        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->select('telegram_event.account')
            ->from(AccountTelegramEvent::TABLE, 'telegram_event');

        $dbal->where('telegram_event.chat = :chat')
            ->setParameter('chat', (string) $chat);

        $dbal->andWhere('telegram_event.status = :telegram_status')
            ->setParameter(
                'telegram_status',
                new AccountTelegramStatus(new AccountTelegramStatus\AccountTelegramStatusActive()),
                AccountTelegramStatus::TYPE);


        $exist = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $exist->select('1');
        $exist->from(UserProfileInfo::TABLE, 'profile_info');
        $exist->where('profile_info.usr = telegram_event.account');
        $exist->andWhere('profile_info.status = :profile_status');
        $exist->andWhere('profile_info.active = true');

        $exist->join(
            'profile_info',
            UserProfile::TABLE,
            'profile',
            'profile.id = profile_info.profile');

        $dbal->setParameter('profile_status',
            new UserProfileStatus(UserProfileStatusActive::class),
            UserProfileStatus::TYPE);

        $dbal->andWhere('EXISTS('.$exist->getSQL().')');

        $result = $dbal->enableCache('auth-telegram', 3600)->fetchOne();

        return $result ? new UserUid($result) : null;
    }
}
