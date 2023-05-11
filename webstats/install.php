<?php

namespace Garradin;

use Garradin\Plugin\Webstats\Stats;

$db = DB::getInstance();
$db->import(__DIR__ . '/schema.sql');

$plugin->registerSignal('web.request', 'Garradin\Plugin\Webstats\Stats::webRequest');
