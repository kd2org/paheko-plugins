<?php

namespace Garradin;

require __DIR__ . '/_inc.php';

use KD2\DB\EntityManager;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Forms;

$csrf_key = 'sync';

$form->runIf('sync', function() use ($ha) {
	$ha->sync();
}, $csrf_key, PLUGIN_ADMIN_URL);

$tpl->assign('last_sync', $ha->getLastSync());
$tpl->assign('csrf_key', $csrf_key);

$forms = EntityManager::getInstance(Form::class)->all('SELECT * FROM @TABLE WHERE id_credit_account IS NULL');
$tpl->assign('forms', $forms);
$tpl->assign('chart_id', Plugin\HelloAsso\HelloAsso::CHART_ID); // ToDo: make it dynamic

$form->runIf('form_submit', function() use ($ha) {
	$source = [];
	foreach ($_POST['credit'] as $id_form => $array)
	{
		$id_credit_account = array_keys($array)[0];
		$id_debit_account = array_keys($_POST['debit'][$id_form])[0];
		// ToDo: add a nice check
		if ($id_credit_account && $id_debit_account)
		{
			$source[$id_form]['credit'] = (int)$id_credit_account;
			$source[$id_form]['debit'] = (int)$id_debit_account;
		}
	}
	Forms::setAccounts($source);
	$ha->sync();
	Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php?ok=1');
});

$tpl->display(PLUGIN_ROOT . '/templates/sync.tpl');
