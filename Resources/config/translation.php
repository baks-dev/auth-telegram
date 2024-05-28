<?php

// namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Config\FrameworkConfig;

return static function(FrameworkConfig $config) {
	$config->translator()->paths([__DIR__.'/../translations']);
};
