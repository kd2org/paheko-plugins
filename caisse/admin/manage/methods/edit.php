<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Methods;

require __DIR__ . '/../_inc.php';

if (qg('new') !== null) {
	$method = Methods::new();
	$csrf_key = 'method_new';
}
else {
	$method = Methods::get((int) qg('id'));
	$csrf_key = 'method_edit_' . $method->id();
}

$tpl->assign(compact('method', 'csrf_key'));

if (qg('delete') !== null) {
	$form->runIf('delete', function () use ($method) {
		if (!f('confirm_delete')) {
			throw new UserException('Merci de cocher la case pour confirmer la suppression.');
		}

		$method->delete();
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/methods/delete.tpl');
}
else {
	$form->runIf('save', function () use ($method) {
		$new = $method->exists() ? false : true;
		$method->importForm();
		$method->save();

		if ($new && f('link_all')) {
			$method->linkAllProducts();
		}
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/methods/edit.tpl');
}
