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

namespace BaksDev\Auth\Telegram\UseCase\Telegram\Registration;

use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusNew;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see AccountTelegramEvent */
final class AccountTelegramRegistrationDTO implements AccountTelegramEventInterface
{
    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private readonly ?AccountTelegramEventUid $id;

    /**
     * Идентификатор чата
     */
    #[Assert\NotBlank]
    private readonly string $chat;

    /**
     * Username
     */
    private ?string $username = null;

    /**
     * FirstName
     */
    private ?string $firstname = null;

    /**
     * Статус
     */
    #[Assert\NotBlank]
    private readonly AccountTelegramStatus $status;


    public function __construct(int|string $chat)
    {
        $this->id = null;
        $this->status = new AccountTelegramStatus(AccountTelegramStatusNew::class);
        $this->chat = (string) $chat;
    }


    /**
     * Идентификатор события
     */
    public function getEvent(): ?AccountTelegramEventUid
    {
        return $this->id;
    }

    /**
     * Chat
     */
    public function getChat(): string
    {
        return $this->chat;
    }

    /**
     * Status
     */
    public function getStatus(): AccountTelegramStatus
    {
        return $this->status;
    }

    /**
     * Username
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * Firstname
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(?string $firstname): self
    {
        $this->firstname = $firstname;
        return $this;
    }
}