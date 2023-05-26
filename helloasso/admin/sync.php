<?php

namespace Garradin;

require __DIR__ . '/_inc.php';

use KD2\DB\EntityManager;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Chargeables;

$csrf_key = 'sync';

$form->runIf('sync', function() use ($ha) {
	$ha->sync();
}, $csrf_key, PLUGIN_ADMIN_URL . 'sync.php?ok=1');

$tpl->assign('last_sync', $ha->getLastSync());
$tpl->assign('csrf_key', $csrf_key);

$chargeables = Chargeables::allPlusExtraFields(
	sprintf('
		SELECT c.*, f.name AS _form_name, i.label AS _item_name
		FROM @TABLE c
		LEFT JOIN %s f ON (f.id = c.id_form)
		LEFT JOIN %s i ON (i.id = c.id_item)
		WHERE c.id_credit_account IS NULL
		',
		Form::TABLE, Item::TABLE),
	['_form_name', '_item_name']
);
$tpl->assign('chargeables', $chargeables);
$tpl->assign('chargeableTypes', Chargeable::TYPES);

$tpl->assign('chart_id', Plugin\HelloAsso\HelloAsso::CHART_ID); // ToDo: make it dynamic

$form->runIf('accounts_submit', function() use ($ha) {
	if (array_key_exists('chargeable_credit', $_POST)) {
		$source = [];
		foreach ($_POST['chargeable_credit'] as $id_item => $array)
		{
			$id_credit_account = array_keys($array)[0];
			$id_debit_account = array_keys($_POST['chargeable_debit'][$id_item])[0];
			// ToDo: add a nice check
			if ($id_credit_account && $id_debit_account)
			{
				$source[$id_item]['credit'] = (int)$id_credit_account;
				$source[$id_item]['debit'] = (int)$id_debit_account;
			}
		}
		Chargeables::setAccounts($source);
	}
	$ha->sync();
}, null, PLUGIN_ADMIN_URL . 'sync.php?ok=1');

$tpl->display(PLUGIN_ROOT . '/templates/sync.tpl');
