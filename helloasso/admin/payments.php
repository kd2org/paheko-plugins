<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Payments;

require __DIR__ . '/_inc.php';

$f = Forms::get((int)qg('id'));

if (!$f) {
	throw new UserException('Formulaire inconnu');
}

$list = Payments::list(null, $f);
$list->loadFromQueryString();

$tpl->assign('form', $f);
$tpl->assign(compact('list'));

$tpl->display(PLUGIN_ROOT . '/templates/payments.tpl');
