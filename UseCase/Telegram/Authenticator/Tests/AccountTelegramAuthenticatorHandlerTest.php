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

namespace BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\Tests;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusActive;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusNew;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorHandler;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Products\Product\Type\Id\ProductUid;
use BaksDev\Products\Product\Type\Offers\ConstId\ProductOfferConst;
use BaksDev\Products\Product\Type\Offers\Variation\ConstId\ProductVariationConst;
use BaksDev\Products\Product\Type\Offers\Variation\Modification\ConstId\ProductModificationConst;
use BaksDev\Products\Sign\Entity\ProductSign;
use BaksDev\Products\Sign\Repository\CurrentEvent\ProductSignCurrentEventInterface;
use BaksDev\Products\Sign\Type\Id\ProductSignUid;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\Collection\ProductSignStatusCollection;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusDone;
use BaksDev\Products\Sign\Type\Status\ProductSignStatus\ProductSignStatusNew;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\Code\ProductSignCodeDTO;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignDTO;
use BaksDev\Products\Sign\UseCase\Admin\NewEdit\ProductSignHandler;
use BaksDev\Users\Profile\UserProfile\Type\Id\UserProfileUid;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\Tests\AccountTelegramRegistrationHandlerTest;

/**
 * @group auth-telegram
 * @depends BaksDev\Auth\Telegram\UseCase\Telegram\Registration\Tests\AccountTelegramRegistrationHandlerTest::class
 */
#[When(env: 'test')]
final class AccountTelegramAuthenticatorHandlerTest extends KernelTestCase
{
    public function testUseCase(): void
    {
        /**
         * Инициируем статусы
         * @var AccountTelegramStatusCollection $status
         */
        $status = self::getContainer()->get(AccountTelegramStatusCollection::class);
        $status->cases();


        /** @var AccountTelegramEventInterface $AccountTelegramCurrentEvent */
        $AccountTelegramCurrentEvent = self::getContainer()->get(AccountTelegramEventInterface::class);
        $AccountTelegramEvent = $AccountTelegramCurrentEvent->findByUser(UserUid::TEST);

        $AuthenticatorDTO = new AccountTelegramAuthenticatorDTO($AccountTelegramEventUid = clone (new AccountTelegramEventUid()));
        $AccountTelegramEvent->getDto($AuthenticatorDTO);

        self::assertSame($AccountTelegramEventUid, $AuthenticatorDTO->getNew());

        self::assertTrue($AuthenticatorDTO->getAccount()->equals(UserUid::TEST));
        self::assertTrue($AuthenticatorDTO->getStatus()->equals(AccountTelegramStatusActive::class));
        self::assertNotNull($AuthenticatorDTO->getEvent());
        self::assertSame('12345', $AuthenticatorDTO->getChat());

        /** @var AccountTelegramAuthenticatorHandler $AuthenticatorHandler */
        $AuthenticatorHandler = self::getContainer()->get(AccountTelegramAuthenticatorHandler::class);
        $handle = $AuthenticatorHandler->handle($AuthenticatorDTO);

        self::assertTrue(($handle instanceof AccountTelegram), $handle.': Ошибка AccountTelegram');

    }

    public function testCompleteMain(): void
    {
        /** @var DBALQueryBuilder $dbal */
        $dbal = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal->createQueryBuilder(self::class);

        $dbal
            ->from(AccountTelegram::class)
            ->where('id = :id')
            ->setParameter('id', UserUid::TEST);

        self::assertTrue($dbal->fetchExist());

    }

    public function testComplete(): void
    {
        $dbal = self::getContainer()->get(DBALQueryBuilder::class);

        $dbal->createQueryBuilder(self::class);

        $dbal
            ->from(AccountTelegramEvent::class)
            ->where('id = :event')
            ->setParameter('event', AccountTelegramEventUid::TEST);

        self::assertTrue($dbal->fetchExist());
    }

    public static function tearDownAfterClass(): void
    {
        /**
         * Инициируем статусы
         * @var AccountTelegramStatusCollection $status
         */
        $status = self::getContainer()->get(AccountTelegramStatusCollection::class);
        $status->cases();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);


        $user = $em->getRepository(User::class)
            ->findOneBy(['id' => UserUid::TEST]);

        if($user)
        {
            $em->remove($user);
        }

        $main = $em->getRepository(AccountTelegram::class)
            ->findOneBy(['id' => UserUid::TEST]);

        if($main)
        {
            $em->remove($main);
        }

        $event = $em->getRepository(AccountTelegramEvent::class)
            ->findBy(['account' => UserUid::TEST]);

        foreach($event as $remove)
        {
            $em->remove($remove);
        }

        $em->flush();
        $em->clear();
    }
}