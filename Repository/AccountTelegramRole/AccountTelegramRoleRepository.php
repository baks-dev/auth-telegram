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

namespace BaksDev\Auth\Telegram\Repository\AccountTelegramRole;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\Profile\Group\BaksDevUsersProfileGroupBundle;
use BaksDev\Users\Profile\Group\Entity\Event\ProfileGroupEvent;
use BaksDev\Users\Profile\Group\Entity\ProfileGroup;
use BaksDev\Users\Profile\Group\Entity\Role\ProfileRole;
use BaksDev\Users\Profile\Group\Entity\Role\Voter\ProfileVoter;
use BaksDev\Users\Profile\Group\Entity\Users\ProfileGroupUsers;
use BaksDev\Users\Profile\UserProfile\Entity\Info\UserProfileInfo;
use BaksDev\Users\Profile\UserProfile\Entity\UserProfile;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\Status\UserProfileStatusActive;
use BaksDev\Users\Profile\UserProfile\Type\UserProfileStatus\UserProfileStatus;

final class AccountTelegramRoleRepository implements AccountTelegramRoleInterface
{
    private DBALQueryBuilder $DBALQueryBuilder;

    private ?UserProfileUid $profile = null;

    public function __construct(
        DBALQueryBuilder $DBALQueryBuilder,
    )
    {
        $this->DBALQueryBuilder = $DBALQueryBuilder;
    }

    public function profile(UserProfile|UserProfileUid|string $profile)
    {
        if(is_string($profile))
        {
            $profile = new UserProfileUid($profile);
        }

        if($profile instanceof UserProfile)
        {
            $profile = $profile->getId();
        }

        $this->profile = $profile;

        return $this;
    }

    /**
     * Метод возвращает всех пользователей Telegram, имеющие доступ к указанному профилю
     */
    public function fetchAll(string $role, ?UserProfileUid $profile = null): array|bool
    {
        if(!class_exists(BaksDevUsersProfileGroupBundle::class))
        {
            return false;
        }

        $dbal = $this->DBALQueryBuilder->createQueryBuilder(self::class);

        $dbal
            ->from(ProfileGroupUsers::class, 'usr');

        if($profile)
        {
            $dbal
                ->where('usr.profile = :profile OR usr.authority = :profile')
                ->setParameter('profile', $profile, UserProfileUid::TYPE);
        }

        $dbal
            ->join(
                'usr',
                ProfileGroup::class,
                'grp',
                'grp.prefix = usr.prefix'
            );

        $dbal
            ->join(
                'grp',
                ProfileRole::class,
                'role',
                'role.event = grp.event'
            );

        $dbal
            ->join(
                'role',
                ProfileVoter::class,
                'voter',
                'voter.role = role.id'
            );

        $dbal
            ->andWhere('(role.prefix = :role OR voter.prefix = :role)')
            ->setParameter('role', $role);


        /** UserProfile */

        $dbal
            ->join(
                'usr',
                UserProfileInfo::class,
                'profile_info',
                '
                profile_info.profile = usr.profile AND 
                profile_info.status = :profile_status AND 
                profile_info.active = true
            '
            )
            ->setParameter(
                'profile_status',
                UserProfileStatusActive::class,
                UserProfileStatus::TYPE
            );


        $dbal
            ->join(
                'profile_info',
                UserProfile::class,
                'profile',
                'profile.id = profile_info.profile'
            );


        /** Account Telegram */
        $dbal
            ->join(
                'profile_info',
                AccountTelegram::class,
                'account',
                'account.id = profile_info.usr'
            );

        /** Account Telegram */
        $dbal
            ->select('account_event.chat')
            ->groupBy('account_event.chat')
            ->leftJoin(
                'account',
                AccountTelegramEvent::class,
                'account_event',
                'account_event.id = account.event'
            );

        return $dbal
            ->enableCache('auth-telegram', 3600)
            ->fetchAllAssociative();
    }
}
