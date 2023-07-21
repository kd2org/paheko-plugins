<?php

namespace Paheko;
use Paheko\Plugin\Caisse\Stock;
use Paheko\Plugin\Caisse\Entities\StockEvent;

require __DIR__ . '/../_inc.php';

if (qg('new') !== null) {
	$event = Stock::new();
	$csrf_key = 'event_new';
}
elseif (qg('delete') !== null) {
	$event = Stock::get((int) qg('id'));
	$csrf_key = 'event_edit_' . $event->id();
}
else {
	throw new UserException('Appel invalide');
}

$types = StockEvent::TYPES;

$tpl->assign(compact('event', 'csrf_key', 'types'));

if (qg('delete') !== null) {
	$form->runIf('delete', function () use ($event) {
		if (!f('confirm_delete')) {
			throw new UserException('Merci de cocher la case pour confirmer la suppression.');
		}

		$event->delete();
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/stock/delete.tpl');
}
else {
	$form->runIf('save', function () use ($event) {
		$event->importForm();
		$event->save();
	}, $csrf_key, './');

	$tpl->display(PLUGIN_ROOT . '/templates/manage/stock/edit.tpl');
}
