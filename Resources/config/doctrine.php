<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventType;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatusType;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine) {
    
    $doctrine->dbal()->type(AccountTelegramEventUid::TYPE)->class(AccountTelegramEventType::class);
    $doctrine->dbal()->type(AccountTelegramStatus::TYPE)->class(AccountTelegramStatusType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $MODULE = substr(__DIR__, 0, strpos(__DIR__, "Resources"));

    $emDefault->mapping('auth-telegram')
        ->type('attribute')
        ->dir($MODULE.'Entity')
        ->isBundle(false)
        ->prefix('BaksDev\Auth\Telegram\Entity')
        ->alias('auth-telegram');
};
