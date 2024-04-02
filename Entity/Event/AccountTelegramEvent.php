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

namespace BaksDev\Auth\Telegram\Entity\Event;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Modify\AccountTelegramModify;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Core\Entity\EntityEvent;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/* AccountTelegramEvent */

#[ORM\Entity]
#[ORM\Table(name: 'account_telegram_event')]
#[ORM\Index(columns: ['chat'])]
class AccountTelegramEvent extends EntityEvent
{
    public const TABLE = 'account_telegram_event';

    /**
     * Идентификатор События
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Id]
    #[ORM\Column(type: AccountTelegramEventUid::TYPE)]
    private AccountTelegramEventUid $id;

    /**
     * Идентификатор пользователя
     */
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[ORM\Column(type: UserUid::TYPE, nullable: false)]
    private ?UserUid $account = null;


    /**
     * Идентификатор чата
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: Types::STRING)]
    private string $chat;

    /**
     * Username
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $username = null;

    /**
     * FirstName
     */
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $firstname = null;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    #[ORM\Column(type: AccountTelegramStatus::TYPE)]
    private AccountTelegramStatus $status;

    /**
     * Модификатор
     */
    #[ORM\OneToOne(targetEntity: AccountTelegramModify::class, mappedBy: 'event', cascade: ['all'])]
    private AccountTelegramModify $modify;


    public function __construct()
    {
        $this->id = new AccountTelegramEventUid();
        $this->modify = new AccountTelegramModify($this);
    }

    public function __clone()
    {
        $this->id = clone $this->id;
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }


    public function getId(): AccountTelegramEventUid
    {
        return $this->id;
    }

    public function setId(AccountTelegramEventUid $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Идентификатор UserUid
     */

    public function setMain(AccountTelegram|UserUid $account): void
    {
        $this->account = $account instanceof AccountTelegram ? $account->getId() : $account;
    }

    public function getAccount(): ?UserUid
    {
        return $this->account;
    }

    /**
     * Username
     */
    public function getUsername(): string
    {
        return $this->firstname ?: $this->username;
    }


    public function getDto($dto): mixed
    {
        $dto = is_string($dto) && class_exists($dto) ? new $dto() : $dto;

        if($dto instanceof AccountTelegramEventInterface)
        {
            return parent::getDto($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }

    public function setEntity($dto): mixed
    {
        if($dto instanceof AccountTelegramEventInterface || $dto instanceof self)
        {
            return parent::setEntity($dto);
        }

        throw new InvalidArgumentException(sprintf('Class %s interface error', $dto::class));
    }
}
