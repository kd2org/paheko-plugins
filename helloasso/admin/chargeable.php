<?php

namespace Garradin;

use KD2\DB\EntityManager;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Orders;

use Garradin\Payments\Payments;
use Garradin\Entities\Payments\Payment;
use Garradin\Entities\Accounting\Account;
use Garradin\Entities\Accounting\Transaction;
use Garradin\Entities\Users\User;
use Garradin\Entities\Users\Category;
use Garradin\Entities\Services\Fee;
use Garradin\Entities\Services\Service;

use Garradin\UserException;

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
	if (array_key_exists('credit', $_POST)) {
		$chargeable->set('id_credit_account', (int)array_keys($_POST['credit'])[0]);
		$chargeable->set('id_debit_account', (int)array_keys($_POST['debit'])[0]);
	}
	$chargeable->set('id_category', $_POST['id_category'] === '0' ? null : (int)$_POST['id_category']);
	$chargeable->set('id_fee', isset($_POST['id_fee']) ? (int)array_keys($_POST['id_fee'])[0] : null);
	$chargeable->set('need_config', 0);
	$chargeable->save();
}, $csrf_key, 'chargeable.php?id=' . $id . '&ok');

$item = $chargeable->id_item ? EntityManager::findOneById(Item::class, $chargeable->id_item) : null;
$form = EntityManager::findOneById(Form::class, $chargeable->id_form);
$credit_account = $chargeable->id_credit_account ? EntityManager::findOneById(Account::class, (int)$chargeable->id_credit_account) : null;
$debit_account = $chargeable->id_debit_account ? EntityManager::findOneById(Account::class, (int)$chargeable->id_debit_account) : null;

// ToDo: remove admin categories
$categories = EntityManager::getInstance(Category::class)->all('SELECT * FROM @TABLE');
$category_options = [ 0 => 'Ne pas inscrire la personne' ];
foreach ($categories as $category) {
	$category_options[(int)$category->id] = $category->name;
}

$fee = $chargeable->fee();
$service = $fee ? $chargeable->service() : null;

$tpl->assign([
	'chargeable' => $chargeable,
	'category' => $chargeable->id_category ? EntityManager::findOneById(Category::class, $chargeable->id_category) : null,
	'parent_item' => $item,
	'form' => $form,
	'chart_id' => Plugin\HelloAsso\HelloAsso::CHART_ID, // ToDo: make it dynamic
	'credit_account' => (null !== $credit_account) ? [ $credit_account->id => $credit_account->code . ' — ' . $credit_account->label ] : null,
	'debit_account' => (null !== $debit_account) ? [ $debit_account->id => $debit_account->code . ' — ' . $debit_account->label ] : null,
	'category_options' => $category_options,
	'selected_fee' => $fee ? [ (int)$fee->id => ($service->label . ' - ' . $fee->label) ] : null,
	'orders' => Orders::list($chargeable),
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
