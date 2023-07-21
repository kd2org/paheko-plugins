<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Stock;
use Paheko\Plugin\Caisse\Products;

require __DIR__ . '/../_inc.php';

$event = Stock::get((int) qg('id'));

$csrf_key = sprintf('event_%d', $event->id);

if (!$event->applied) {
	$form->runIf('add', function () use ($event) {
		$event->addProduct(key(f('add')), (int) current(f('add')));
	}, $csrf_key, Utils::getRequestURI());

	$form->runIf(qg('delete') !== null, function () use ($event) {
		$event->deleteProduct((int) qg('delete'));
	}, null, '?id=' . $event->id());

	$form->runIf('change', function () use ($event) {
		$event->setProductQty(key(f('change')), (int) current(f('change')));
	}, $csrf_key, Utils::getRequestURI());

	$form->runIf('apply', function () use ($event) {
		$event->applyChanges();
	}, $csrf_key, Utils::getRequestURI());
}

$list = $event->listChanges();
$total = $event->totalChanges($list);
$tpl->assign('products_categories', Products::listByCategory(false, true));

$tpl->assign(compact('event', 'csrf_key', 'list', 'total'));

$tpl->display(PLUGIN_ROOT . '/templates/manage/stock/details.tpl');
