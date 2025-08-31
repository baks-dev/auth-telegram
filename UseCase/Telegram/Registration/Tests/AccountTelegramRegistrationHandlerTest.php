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

namespace BaksDev\Auth\Telegram\UseCase\Telegram\Registration\Tests;

use BaksDev\Auth\Telegram\Entity\AccountTelegram;
use BaksDev\Auth\Telegram\Entity\Event\AccountTelegramEvent;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusNew;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\Collection\AccountTelegramStatusCollection;
use BaksDev\Auth\Telegram\UseCase\Telegram\Authenticator\AccountTelegramAuthenticatorDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\AccountTelegramRegistrationDTO;
use BaksDev\Auth\Telegram\UseCase\Telegram\Registration\AccountTelegramRegistrationHandler;
use BaksDev\Core\Doctrine\DBALQueryBuilder;
use BaksDev\Users\User\Entity\User;
use BaksDev\Users\User\Type\Id\UserUid;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DependsOnClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[Group('auth-telegram')]
final class AccountTelegramRegistrationHandlerTest extends KernelTestCase
{
    public static function setUpBeforeClass(): void
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


    public function testUseCase(): void
    {
        /** @see AccountTelegramAuthenticatorDTO */

        $AccountTelegramRegistrationDTO = new AccountTelegramRegistrationDTO(12345);
        self::assertSame('12345', $AccountTelegramRegistrationDTO->getChat());

        //$AuthenticatorDTO = new AccountTelegramRegistrationDTO($AccountTelegramEventUid = clone (new AccountTelegramEventUid()));
        //self::assertSame($AccountTelegramEventUid, $AuthenticatorDTO->getNew());

        $AccountTelegramRegistrationDTO->setUsername('username');
        self::assertSame('username', $AccountTelegramRegistrationDTO->getUsername());

        $AccountTelegramRegistrationDTO->setFirstname('firstname');
        self::assertSame('firstname', $AccountTelegramRegistrationDTO->getFirstname());

        //$AuthenticatorDTO->setChat(12345);

        self::assertNull($AccountTelegramRegistrationDTO->getEvent());
        self::assertTrue($AccountTelegramRegistrationDTO->getStatus()->equals(AccountTelegramStatusNew::class));


        /** @var AccountTelegramRegistrationHandler $AccountTelegramRegistrationHandler */
        $AccountTelegramRegistrationHandler = self::getContainer()->get(AccountTelegramRegistrationHandler::class);
        $handle = $AccountTelegramRegistrationHandler->handle($AccountTelegramRegistrationDTO);

        self::assertTrue(($handle instanceof AccountTelegram), $handle.': Ошибка AccountTelegram');

    }

    public function testComplete(): void
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
}