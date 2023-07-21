<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\Forms;
use Paheko\Plugin\HelloAsso\Chargeables;

require __DIR__ . '/_inc.php';

$f = Forms::get((int)qg('id'));

if (!$f) {
	throw new UserException('Formulaire inconnu');
}

$list = Chargeables::list($f);
$list->loadFromQueryString();

$tpl->assign([
	'form' => $f,
	'list' => $list,
	'count_opti' => Chargeables::listCountOpti($f)
]);

$tpl->display(PLUGIN_ROOT . '/templates/chargeables.tpl');
