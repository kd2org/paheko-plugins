<?php

namespace Garradin;

require __DIR__ . '/_inc.php';

$session->requireAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE);

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Accounting\Account;
use Garradin\Entities\Users\Category;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Items;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Chargeables;

$synchronize = function () use ($ha, $tpl)
{
	$ha->sync();
	if (!$exceptions = Items::getExceptions()) {
		Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php?ok=1');
	}
	$tpl->assign('exceptions', $exceptions);
};

$csrf_key = 'sync';

$form->runIf('sync', $synchronize, $csrf_key);

$form->runIf('accounts_submit', function() use ($ha, $tpl, $synchronize) {

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

	$em = EM::getInstance(Chargeable::class);
	// ToDo: add a nice check
	foreach ($em->iterate('SELECT * FROM @TABLE WHERE id IN (' . implode(', ', array_keys($_POST['id_category'])) . ')') as $chargeable) {
		$chargeable->set('id_category', $_POST['id_category'][$chargeable->id] === '0' ? null : (int)$_POST['id_category'][$chargeable->id]);
		$chargeable->set('need_config', 0);
		$chargeable->save();
	}

	call_user_func($synchronize);

}, $csrf_key);

$default_ca = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_credit_account);
$default_da = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_debit_account);

// ToDo: remove admin categories
$categories = EM::getInstance(Category::class)->all('SELECT * FROM @TABLE');
$category_options = [ 0 => 'Ne pas inscrire la personne' ];
foreach ($categories as $category) {
	$category_options[(int)$category->id] = $category->name;
}

$tpl->assign([
	'last_sync' => $ha->getLastSync(),
	'csrf_key' => $csrf_key,
	'chargeables' => Chargeables::allForDisplay((bool)$plugin->getConfig()->accounting),
	'chargeableTypes' => Chargeable::TYPES,
	'chart_id' => Plugin\HelloAsso\HelloAsso::CHART_ID, // ToDo: make it dynamic
	'default_credit_account' => (null !== $default_ca) ? [ $default_ca->id => $default_ca->code . ' — ' . $default_ca->label ] : null,
	'default_debit_account' => (null !== $default_da) ? [ $default_da->id => $default_da->code . ' — ' . $default_da->label ] : null,
	'category_options' => $category_options
]);

$tpl->display(PLUGIN_ROOT . '/templates/sync.tpl');
