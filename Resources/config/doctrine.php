<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use BaksDev\Auth\Telegram\BaksDevAuthTelegramBundle;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventType;
use BaksDev\Auth\Telegram\Type\Event\AccountTelegramEventUid;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatus;
use BaksDev\Auth\Telegram\Type\Status\AccountTelegramStatusType;
use Symfony\Config\DoctrineConfig;

return static function(ContainerConfigurator $container, DoctrineConfig $doctrine) {

    $doctrine->dbal()->type(AccountTelegramEventUid::TYPE)->class(AccountTelegramEventType::class);
    $doctrine->dbal()->type(AccountTelegramStatus::TYPE)->class(AccountTelegramStatusType::class);

    $emDefault = $doctrine->orm()->entityManager('default')->autoMapping(true);

    $emDefault->mapping('auth-telegram')
        ->type('attribute')
        ->dir(BaksDevAuthTelegramBundle::PATH.'Entity')
        ->isBundle(false)
        ->prefix(BaksDevAuthTelegramBundle::NAMESPACE.'\\Entity')
        ->alias('auth-telegram');
};
