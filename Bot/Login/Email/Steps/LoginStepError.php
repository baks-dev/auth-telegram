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

namespace BaksDev\Auth\Telegram\Bot\Login\Email\Steps;

use BaksDev\Auth\Telegram\Bot\Login\Email\LoginStep;

final class LoginStepError extends LoginStep
{
    private string $message = 'Ошибка авторизации!  Повторная попытка через 30 сек...';

    public string $nextStep = self::class;

    public function next(mixed $data): void
    {
        $this->context->transitionTo($this);
    }

    /**
     * Сообщение для отправили в Telegram
     */
    public function message(): ?string
    {
        return $this->message;
    }

    /**
     * Блок кнопок для отправили в Telegram
     */
    public function markup(): ?array
    {
        return null;
    }

    /**
     * Класс следующего состояния
     */
    public function getNextStep(): string
    {
        return $this->nextStep;
    }
}