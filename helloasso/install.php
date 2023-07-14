<?php

namespace Garradin;

use Garradin\Entities\Payments\Provider;
use Garradin\Plugin\HelloAsso\HelloAsso;

// Création table
$db = DB::getInstance();
$db->import(__DIR__ . '/schema.sql');

$provider = new Provider();
$provider->set('name', HelloAsso::PROVIDER_NAME);
$provider->set('label', HelloAsso::PROVIDER_LABEL);
$provider->save();

$ha = HelloAsso::getInstance();
$ha->initConfig((int)$provider->id_user);

$plugin->registerSignal('cron', 'Garradin\Plugin\HelloAsso\HelloAsso::cron');
