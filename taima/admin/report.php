<?php

namespace Paheko\Plugin\Taima;

use Paheko\Plugin\Taima\Tracking;
use Paheko\Accounting\Years;
use Paheko\Utils;
use Paheko\UserException;
use Paheko\Users\Session;

use function Paheko\{f, qg};

require_once __DIR__ . '/_inc.php';

$session = Session::getInstance();
$session->requireAccess($session::SECTION_USERS, $session::ACCESS_ADMIN);
$session->requireAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE);

$csrf_key = 'taima_report';
$years = Years::listOpenAssoc();

if (!count($years)) {
	throw new UserException('Aucun exercice n\'est ouvert, il n\'est donc pas possible de valoriser le temps bénévole.');
}

$year = null;

if (f('id_year')) {
	$year = Years::get((int)f('id_year'));
}
elseif (count($years) == 1) {
	$year = Years::getCurrentOpenYear();
}

if ($year) {
	$start = f('start') ? Utils::get_datetime(f('start')) : ($year->start_date ?? null);
	$end = f('end') ? Utils::get_datetime(f('end')) : ($year->end_date ?? null);

	$form->runIf('save', function () use ($year, $start, $end) {
		$id_user = Session::getInstance()->getUser()->id;
		$t = Tracking::createReport($year, $start, $end, $id_user);
		$t->save();
		Utils::redirect(Utils::getSelfURI(['ok' => $t->id()]));
	}, $csrf_key);

	$report = Tracking::getFinancialReport($year, $start, $end);

	$tpl->assign(compact('report', 'year', 'csrf_key'));
}
else {
	$tpl->assign(compact('years', 'csrf_key'));
}

$tpl->display(__DIR__ . '/../templates/report.tpl');
