<?php

namespace Paheko;

$db = DB::getInstance();

$db->exec('DROP TABLE IF EXISTS plugin_webstats_stats;');
$db->exec('DROP TABLE IF EXISTS plugin_webstats_hits;');
