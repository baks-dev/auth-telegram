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

namespace BaksDev\Auth\Telegram\Entity;

use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Id\AccountTelegramUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


/* AccountTelegram */

#[ORM\Entity]
#[ORM\Table(name: 'account_telegram')]
class AccountTelegram
{
    /** ID */
    #[ORM\Id]
    #[ORM\Column(type: UserUid::TYPE)]
    private UserUid $id;

    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: AccountTelegramEventUid::TYPE, unique: true)]
    private AccountTelegramEventUid $event;

    public function __construct(User|UserUid $usr)
    {
        $this->id = $usr instanceof User ? $usr->getId() : $usr;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    /**
     * Идентификатор
     */
    public function getId(): UserUid
    {
        return $this->id;
    }

    /**
     * Идентификатор События
     */
    public function getEvent(): AccountTelegramEventUid
    {
        return $this->event;
    }

    public function setEvent(AccountTelegramEventUid|AccountTelegramEvent $event): void
    {
        $this->event = $event instanceof AccountTelegramEvent ? $event->getId() : $event;
    }
}
