<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Methods;

require __DIR__ . '/../_inc.php';

$method = Methods::get((int)qg('id'));

if (!$method) {
	throw new UserException('Moyen de paiement inconnu');
}

$csrf_key = 'link_products_' . $method->id;

$form->runIf('save', function () use ($method) {
	$products = f('products') ?? [];
	$method->linkProducts(array_keys($products));
}, $csrf_key, './');

$tpl->assign(compact('method', 'csrf_key'));
$tpl->assign('list', $method->listProducts());

$tpl->display(PLUGIN_ROOT . '/templates/manage/methods/products.tpl');
