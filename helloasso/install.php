<?php

namespace Paheko;

use Paheko\Entities\Payments\Provider;
use Paheko\Plugin\HelloAsso\HelloAsso;

// Création table
$db = DB::getInstance();
$db->import(__DIR__ . '/schema.sql');

$provider = new Provider();
$provider->set('name', HelloAsso::PROVIDER_NAME);
$provider->set('label', HelloAsso::PROVIDER_LABEL);
$provider->save();

$ha = HelloAsso::getInstance();
$ha->initConfig((int)$provider->id_user);

$plugin->registerSignal('cron', 'Paheko\Plugin\HelloAsso\HelloAsso::cron');
