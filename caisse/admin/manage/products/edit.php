<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Products;

require __DIR__ . '/../_inc.php';

if (qg('new') !== null) {
	$product = Products::new();
	$csrf_key = 'product_new';
}
else {
	$product = Products::get((int) qg('id'));
	$csrf_key = 'product_edit_' . $product->id();
}

$tpl->assign(compact('product', 'csrf_key'));

if (qg('delete') !== null) {
	$form->runIf('delete', function () use ($product) {
		if (!f('confirm_delete')) {
			throw new UserException('Merci de cocher la case pour confirmer la suppression.');
		}

		$product->delete();
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/products/delete.tpl');
}
else {
	$form->runIf('save', function () use ($product) {
		$product->importForm();
		$product->save();
		$product->setMethods(array_keys(f('methods') ?? []));
	}, $csrf_key, './');

	$methods = $product->listPaymentMethods();
	$categories = Products::listCategoriesAssoc();

	$tpl->assign(compact('methods', 'categories'));

	$tpl->display(PLUGIN_ROOT . '/templates/manage/products/edit.tpl');
}