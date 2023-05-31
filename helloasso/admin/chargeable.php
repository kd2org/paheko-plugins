<?php

namespace Garradin;

use KD2\DB\EntityManager;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Form;

use Garradin\Entities\Accounting\Account;

use Garradin\Payments\Payments;
use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Entities\Payments\Payment;
use Garradin\Entities\Accounting\Transaction;
use Garradin\UserException;
use Garradin\Entities\Users\User;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Orders;

require __DIR__ . '/_inc.php';

if ($id = qg('id')) {
	$chargeable = EntityManager::findOneById(Chargeable::class, (int)$id);
}
if (!$chargeable) {
	throw new UserException(sprintf('Article inconnu (n°%d).', $id));
}

$csrf_key = 'accounts_setting';

$form->runIf('save', function () use ($chargeable) {
	// ToDo: add a nice check
	$chargeable->set('id_credit_account', (int)array_keys($_POST['credit'])[0]);
	$chargeable->set('id_debit_account', (int)array_keys($_POST['debit'])[0]);
	$chargeable->set('register_user', (int)isset($_POST['register_user']));
	$chargeable->save();
}, $csrf_key, 'chargeable.php?id=' . $id . '&ok');

$item = $chargeable->id_item ? EntityManager::findOneById(Item::class, $chargeable->id_item) : null;
$form = EntityManager::findOneById(Form::class, $chargeable->id_form);
$credit_account = $chargeable->id_credit_account ? EntityManager::findOneById(Account::class, (int)$chargeable->id_credit_account) : null;
$debit_account = $chargeable->id_debit_account ? EntityManager::findOneById(Account::class, (int)$chargeable->id_debit_account) : null;

$tpl->assign([
	'chargeable' => $chargeable,
	'parent_item' => $item,
	'form' => $form,
	'chart_id' => Plugin\HelloAsso\HelloAsso::CHART_ID, // ToDo: make it dynamic
	'credit_account' => (null !== $credit_account) ? [ $credit_account->id => $credit_account->code . ' — ' . $credit_account->label ] : null,
	'debit_account' => (null !== $debit_account) ? [ $debit_account->id => $debit_account->code . ' — ' . $debit_account->label ] : null,
	'csrf_key' => $csrf_key,
	'current_sub' => 'chargeables'
]);

$tpl->assign('TECH_DETAILS', SHOW_ERRORS && ENABLE_TECH_DETAILS);
$tpl->register_modifier('json_revamp', function ($data) { return json_encode(json_decode($data), JSON_PRETTY_PRINT); });
$tpl->register_modifier('var_dump', function ($data) {
	ob_start();
	var_dump($data);
	return ob_get_clean();
});

$tpl->display(PLUGIN_ROOT . '/templates/chargeable.tpl');
