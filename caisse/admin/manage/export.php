<?php

namespace Paheko;

use Paheko\Plugin\Caisse\POS;

require __DIR__ . '/_inc.php';

if (f('start') && f('end')) {
	$start = \DateTime::createFromFormat('d/m/Y', f('start'));
	$end = \DateTime::createFromFormat('d/m/Y', f('end'));
}
else {
	$start = new \DateTime('first day of this month');
	$end = new \DateTime('last day of this month');
}

if ($start && $end && f('export')) {
	POS::exportSessionsCSV($start, $end, true);
	exit;
}

$tpl->assign(compact('start', 'end'));

$tpl->display(PLUGIN_ROOT . '/templates/manage/export.tpl');