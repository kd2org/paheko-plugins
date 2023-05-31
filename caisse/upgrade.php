<?php

namespace Garradin;

use Garradin\Plugin\Caisse\POS;
use Garradin\Users\DynamicFields;

$db = DB::getInstance();

$old_version = $plugin->oldVersion();

if (version_compare($old_version, '0.2.0', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.2.0.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.3.0', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.3.0.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.3.1', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.3.1.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.3.3', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.3.3.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.3.4', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.3.4.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.4.1', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.4.1.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.5.0', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.5.0.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.5.1', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.5.1.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.5.3', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.5.3.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.5.4', '<')) {
	$db->toggleForeignKeys(false);
	$db->exec(POS::sql(file_get_contents(__DIR__ . '/update_0.5.4.sql')));
	$db->toggleForeignKeys(true);
}

if (version_compare($old_version, '0.6.2', '<')) {
	$db->beginSchemaUpdate();
	$identity = DynamicFields::getNameFieldsSQL();
	$sql = str_replace('@__NAME', $identity, POS::sql(file_get_contents(__DIR__ . '/update_0.6.1.sql')));
	$db->exec($sql);
	$db->commitSchemaUpdate();
}
