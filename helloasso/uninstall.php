<?php

namespace Garradin;

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Payments\Provider;
use Garradin\Plugin\HelloAsso\HelloAsso;

DB::getInstance()->import(__DIR__ . '/uninstall.sql');

$provider = EM::findOne(Provider::class, 'SELECT * FROM @TABLE WHERE name = ?;', HelloAsso::PROVIDER_NAME);
if (!$provider || !($provider instanceof Provider)) {
	throw new \RuntimeException(sprintf('%s provider not found!', HelloAsso::PROVIDER_NAME));
}
if (!$provider->delete()) {
	throw new \RuntimeException(sprintf('%s provider deletion failed!', HelloAsso::PROVIDER_NAME));
}
