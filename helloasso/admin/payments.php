<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\Forms;
use Paheko\Plugin\HelloAsso\Payments;

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
