<?php

namespace BaksDev\Auth\Telegram\Messenger\AccountEmail;

use BaksDev\Auth\Email\Messenger\AccountMessage;
use BaksDev\Auth\Email\Repository\CurrentAccountEvent\CurrentAccountEventInterface;
use BaksDev\Auth\Email\Type\EmailStatus\Status\EmailStatusBlock;
use BaksDev\Auth\Email\UseCase\Admin\NewEdit\AccountDTO;
use BaksDev\Auth\Telegram\Repository\AccountTelegramEvent\AccountTelegramEventInterface;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus\AccountTelegramStatusBlock;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramDTO;
use BaksDev\Auth\Telegram\UseCase\Admin\NewEdit\AccountTelegramHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class BanTelegramAccountHandler
{
    public function __construct(
        #[Target('authTelegramLogger')] private LoggerInterface $logger,
        private CurrentAccountEventInterface $currentAccountEvent,
        private AccountTelegramHandler $accountTelegramHandler,
        private AccountTelegramEventInterface $accountTelegramEvent,
    ) {}

    /**
     * Блокируем AccountTelegram в случае блокировки AccountEmail
     */
    public function __invoke(AccountMessage $command): void
    {
        $AccountEvent = $this->currentAccountEvent->getByUser($command->getId());

        if(!$AccountEvent)
        {
            return;
        }

        $AccountDTO = new AccountDTO();
        $AccountEvent->getDto($AccountDTO);
        $StatusDTO = $AccountDTO->getStatus();

        if(!$StatusDTO->getStatus()->equals(EmailStatusBlock::class))
        {
            return;
        }

        /** AccountTelegramEvent */

        $AccountTelegramEvent = $this->accountTelegramEvent->findByUser($command->getId());

        if(!$AccountTelegramEvent)
        {
            return;
        }

        $AccountTelegramDTO = new AccountTelegramDTO();
        $AccountTelegramEvent->getDto($AccountTelegramDTO);
        $AccountTelegramDTO->setStatus(AccountTelegramStatusBlock::class);
        $this->accountTelegramHandler->handle($AccountTelegramDTO);

        $this->logger->warning('AccountTelegram успешно заблокирован', [
            self::class.':'.__LINE__,
            'UserUid' => $command->getId(),
            'FirstName' => $AccountTelegramDTO->getFirstname()
        ]);
    }
}
