<?php

namespace Paheko;

require_once __DIR__ . '/_inc.php';

if (qg('graph') == 'years') {
	header('Content-Type: image/svg+xml');
	echo $velos->graphStatsPerYear();
	exit;
}
elseif (qg('graph') == 'exit') {
	header('Content-Type: image/svg+xml');
	echo $velos->graphStatsPerExit();
	exit;
}
elseif (qg('graph') == 'entry') {
	header('Content-Type: image/svg+xml');
	echo $velos->graphStatsPerEntry();
	exit;
}

$tpl->assign('stats_years', $velos->statsByYear());
$tpl->assign('stats_months', $velos->statsByMonth());

$tpl->display(PLUGIN_ROOT . '/templates/stats.tpl');
