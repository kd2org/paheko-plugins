<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Chargeables;

require __DIR__ . '/_inc.php';

$f = Forms::get((int)qg('id'));

if (!$f) {
	throw new UserException('Formulaire inconnu');
}

$list = Chargeables::list($f);
$list->loadFromQueryString();

$tpl->assign([
	'form' => $f,
	'list' => $list
]);

$tpl->display(PLUGIN_ROOT . '/templates/chargeables.tpl');
