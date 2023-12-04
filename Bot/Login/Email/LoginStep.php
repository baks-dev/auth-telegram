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

namespace BaksDev\Auth\Telegram\Bot\Login\Email;

use BaksDev\Users\User\Type\Id\UserUid;

abstract class LoginStep
{

    protected LoginTelegram $context;

    /**
     * UserUid
     */
    private UserUid $usr;


    /**
     * Email
     */
    private ?string $email = null;

    /**
     * Password
     */
    private ?string $password = null;

    /**
     * Переводит текущее состояние к следующему шагу
     */
    abstract public function next(mixed $data): void;


    /**
     * Возвращает класс следующего шага
     */
    abstract public function getNextStep(): string;

    /**
     * Сообщение для отправили в Telegram
     */
    abstract public function message(): ?string;

    /**
     * Блок кнопок для отправили в Telegram
     */
    abstract public function markup(): ?array;


    public function setContext(LoginTelegram $context): void
    {
        $this->context = $context;
    }

    /**
     * Email
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }


    /**
     * Password
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    /**
     * User
     */
    public function getUsr(): UserUid
    {
        return $this->usr;
    }

    public function setUsr(UserUid $usr): void
    {
        $this->usr = $usr;
    }

}
