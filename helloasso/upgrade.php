<?php

namespace Paheko;

$db = DB::getInstance();

$old_version = $plugin->getInfos('version');

if (version_compare($old_version, '0.3.3', '<')) {
	$db->import(__DIR__ . '/uninstall.sql');
	$db->import(__DIR__ . '/schema.sql');
	$plugin->setConfig('last_sync', null);
}
