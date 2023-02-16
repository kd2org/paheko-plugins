<?php

namespace Garradin;

use Garradin\Plugin\Taima\Tracking;

$db = DB::getInstance();
$db->import(__DIR__ . '/schema.sql');

$plugin->registerSignal('menu.item', [Tracking::class, 'menuItem']);
$plugin->registerSignal('home.button', [Tracking::class, 'homeButton']);
