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

namespace BaksDev\Auth\Telegram\UseCase\Admin\NewEdit;

use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Symfony\Component\Validator\Constraints as Assert;

/** @see AccountTelegramEvent */
final class AccountTelegramDTO implements AccountTelegramEventInterface
{

    /**
     * Идентификатор события
     */
    #[Assert\Uuid]
    private ?AccountTelegramEventUid $id = null;


    /**
     * Идентификатор пользователя
     */
    #[Assert\Uuid]
    private ?UserUid $account = null;


    /**
     * Идентификатор чата
     */
    #[Assert\NotBlank]
    private string $chat;

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
    private AccountTelegramStatus $status;


    public function __construct()
    {
        $this->status = new AccountTelegramStatus(new AccountTelegramStatus\AccountTelegramStatusActive());
    }

    public function getEvent(): ?AccountTelegramEventUid
    {
        return $this->id;
    }

    /**
     * Account
     */
    public function getAccount(): ?UserUid
    {
        return $this->account;
    }

    public function setAccount(User|UserUid $account): void
    {
        $this->account = $account instanceof User ? $account->getId() : $account;
    }


    /**
     * Chat
     */
    public function getChat(): string
    {
        return $this->chat;
    }

    public function setChat(int|string $chat): void
    {
        $this->chat = (string) $chat;
    }


    /**
     * Username
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }


    /**
     * Firstname
     */
    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }


    /**
     * Status
     */
    public function getStatus(): AccountTelegramStatus
    {
        return $this->status;
    }

    public function setStatus(AccountTelegramStatus $status): void
    {
        $this->status = $status;
    }

}