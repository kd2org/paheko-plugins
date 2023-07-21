<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Methods;
use Paheko\Plugin\Caisse\Sessions;
use Paheko\Plugin\Caisse\Products;

require __DIR__ . '/_inc.php';

$graph = qg('graph');
$year = qg('year');

if ($year) {
	if ($graph == 'methods') {
		header('Content-Type: image/svg+xml');
		echo Methods::graphStatsPerMonth($year);
		exit;
	}
	elseif ($graph == 'categories') {
		header('Content-Type: image/svg+xml');
		echo Products::graphStatsPerMonth($year);
		exit;
	}
	elseif ($graph == 'categories_qty') {
		header('Content-Type: image/svg+xml');
		echo Products::graphStatsQtyPerMonth($year);
		exit;
	}

	$tpl->assign('methods_per_month', Methods::getStatsPerMonth($year));
	$tpl->assign('categories_per_month', Products::getStatsPerMonth($year));
}
else {
	$tpl->assign('years', Sessions::listYears());
}

$tpl->assign(compact('year'));

$tpl->display(PLUGIN_ROOT . '/templates/manage/stats.tpl');
