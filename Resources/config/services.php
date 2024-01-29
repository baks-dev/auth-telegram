<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return static function(ContainerConfigurator $configurator) {
	$services = $configurator->services()
		->defaults()
		->autowire()      // Automatically injects dependencies in your services.
		->autoconfigure() // Automatically registers your services as commands, event subscribers, etc.
	;
	
	$NAMESPACE = 'BaksDev\Auth\Telegram\\';

    $MODULE = substr(__DIR__, 0, strpos(__DIR__, "Resources"));

    $services->load($NAMESPACE, $MODULE)
        ->exclude([
            $MODULE.'{Entity,Resources,Type}',
            $MODULE.'**/*Message.php',
            $MODULE.'**/*DTO.php',
        ])
    ;

    /* Статусы */
    $services->load($NAMESPACE.'Type\Status\AccountTelegramStatus\\', $MODULE.'Type/Status/AccountTelegramStatus');

};

