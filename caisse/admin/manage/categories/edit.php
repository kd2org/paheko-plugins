<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Categories;

require __DIR__ . '/../_inc.php';

if (qg('new') !== null) {
	$cat = Categories::new();
	$csrf_key = 'cat_new';
}
else {
	$cat = Categories::get((int) qg('id'));
	$csrf_key = 'cat_edit_' . $cat->id();
}

$tpl->assign(compact('cat', 'csrf_key'));

if (qg('delete') !== null) {
	$form->runIf('delete', function () use ($cat) {
		if (!f('confirm_delete')) {
			throw new UserException('Merci de cocher la case pour confirmer la suppression.');
		}

		$cat->delete();
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/categories/delete.tpl');
}
else {
	$form->runIf('save', function () use ($cat) {
		$cat->importForm();
		$cat->save();
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/categories/edit.tpl');
}
