<?php

namespace Paheko;

use Paheko\Plugin\Caisse\POS;

$db = DB::getInstance();

preg_match_all('/CREATE TABLE IF NOT EXISTS (@PREFIX_\w+)/', file_get_contents(__DIR__ . '/schema.sql'), $match, PREG_PATTERN_ORDER);
$tables = array_reverse($match[1]);

$db->exec('PRAGMA foreign_keys = OFF');

foreach ($tables as $table) {
	$db->exec(POS::sql(sprintf('DROP TABLE IF EXISTS %s;', $table)));
}
