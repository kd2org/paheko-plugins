<?php

use Paheko\Plugin\Webstats\Stats;

$plugin->unregisterSignal('http.request.skeleton.before');
$plugin->unregisterSignal('http.request.skeleton.after');

$plugin->registerSignal('web.request', 'Paheko\Plugin\Webstats\Stats::webRequest');
