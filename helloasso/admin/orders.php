<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\Forms;
use Paheko\Plugin\HelloAsso\Orders;

use Paheko\Plugin\HelloAsso\ControllerFunctions as CF;

require __DIR__ . '/_inc.php';

$f = Forms::get((int)qg('id'));

if (!$f) {
	throw new UserException('Formulaire inconnu');
}

$csrf_key = 'custom_fields';

$form->runIf('custom_fields_config', function () use ($f) {
	CF::updateCustomFields($f->id, $_POST['custom_fields']);
}, $csrf_key, sprintf('orders.php?id=%dok=1', $f->id));

$list = Orders::list($f);
$list->loadFromQueryString();

$tpl->assign([
	'form' => $f,
	'list' => $list,
	'dynamic_fields' => CF::setDynamicFieldOptions(),
	'csrf_key' => $csrf_key
]);

$tpl->display(PLUGIN_ROOT . '/templates/orders.tpl');
