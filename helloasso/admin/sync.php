<?php

namespace Garradin;

require __DIR__ . '/_inc.php';

$session->requireAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE);

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Accounting\Account;

use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Items;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Chargeables;

$csrf_key = 'sync';

$form->runIf('sync', function() use ($ha, $tpl) {
	$ha->sync();
	if (!$exceptions = Items::getExceptions()) {
		Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php?ok=1');
	}
	$tpl->assign('exceptions', $exceptions);
}, $csrf_key);

$default_ca = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_credit_account);
$default_da = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_debit_account);

$tpl->assign([
	'last_sync' => $ha->getLastSync(),
	'csrf_key' => $csrf_key,
	'chargeables' => Chargeables::allForDisplay((bool)$plugin->getConfig()->accounting),
	'chargeableTypes' => Chargeable::TYPES,
	'chart_id' => Plugin\HelloAsso\HelloAsso::CHART_ID, // ToDo: make it dynamic
	'default_credit_account' => (null !== $default_ca) ? [ $default_ca->id => $default_ca->code . ' — ' . $default_ca->label ] : null,
	'default_debit_account' => (null !== $default_da) ? [ $default_da->id => $default_da->code . ' — ' . $default_da->label ] : null
]);

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

	$registrators = $to_remove_from_registrators = [];
	foreach (array_keys($_POST['ids']) as $id) {
		if (isset($_POST['register_user'][$id])) {
			$registrators[] = (int)$id;
		}
		else {
			$to_remove_from_registrators[] = (int)$id;
		}
	}
	if ($registrators) {
		Chargeables::setUserRegistrators($registrators);
	}
	if ($to_remove_from_registrators) {
		Chargeables::unsetUserRegistrators($to_remove_from_registrators);
	}

	$ha->sync();

}, null, PLUGIN_ADMIN_URL . 'sync.php?ok=1');

$tpl->display(PLUGIN_ROOT . '/templates/sync.tpl');
