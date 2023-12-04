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

namespace BaksDev\Auth\Telegram\Type\Status\Tests;

use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusInterface;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatusType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * @group auth-telegram
 */
#[When(env: 'test')]
final class AccountTelegramStatusTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /** @var AccountTelegramStatusCollection $AccountTelegramStatusCollection */
        $AccountTelegramStatusCollection = self::getContainer()->get(AccountTelegramStatusCollection::class);

        /** @var AccountTelegramStatusInterface $case */
        foreach($AccountTelegramStatusCollection->cases() as $case)
        {
            $AccountTelegramStatus = new AccountTelegramStatus($case->getValue());

            self::assertTrue($AccountTelegramStatus->equals($case::class)); // немспейс интерфейса
            self::assertTrue($AccountTelegramStatus->equals($case)); // объект интерфейса
            self::assertTrue($AccountTelegramStatus->equals($case->getValue())); // срока
            self::assertTrue($AccountTelegramStatus->equals($AccountTelegramStatus)); // объект класса

            $AccountTelegramStatusType = new AccountTelegramStatusType();
            $platform = $this->getMockForAbstractClass(AbstractPlatform::class);

            $convertToDatabase = $AccountTelegramStatusType->convertToDatabaseValue($AccountTelegramStatus, $platform);
            self::assertEquals($AccountTelegramStatus->getTelegramStatusValue(), $convertToDatabase);

            $convertToPHP = $AccountTelegramStatusType->convertToPHPValue($convertToDatabase, $platform);
            self::assertInstanceOf(AccountTelegramStatus::class, $convertToPHP);
            self::assertEquals($case, $convertToPHP->getTelegramStatus());

        }

    }
}