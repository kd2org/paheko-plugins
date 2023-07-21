<?php

namespace Paheko;

use Paheko\Plugin\Webstats\Stats;

$db = DB::getInstance();
$db->import(__DIR__ . '/schema.sql');

$plugin->registerSignal('web.request', 'Paheko\Plugin\Webstats\Stats::webRequest');
