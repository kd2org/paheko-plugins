<?php

namespace Paheko;

use Paheko\Plugin\Webstats\Stats;

if (isset($_GET['graph'])) {
	header('Content-Type: image/svg+xml');
	echo Stats::graph();
	return;
}

$tpl->assign([
	'stats' => Stats::getStats(),
	'hits' => Stats::getHits(),
]);

$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
