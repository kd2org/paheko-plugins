<?php

namespace Garradin;

use KD2\DB\EntityManager;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Item;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Orders;
use Garradin\Plugin\HelloAsso\ControllerFunctions as CF;

use Garradin\Payments\Payments;
use Garradin\Entities\Payments\Payment;
use Garradin\Entities\Accounting\Account;
use Garradin\Entities\Accounting\Transaction;
use Garradin\Entities\Users\User;
use Garradin\Entities\Users\Category;
use Garradin\Entities\Services\Fee;
use Garradin\Entities\Services\Service;
use Garradin\Form as PA_Form;

use Garradin\UserException;

require __DIR__ . '/_inc.php';
require __DIR__ . '/_init_current_year.php';

if ($id = qg('id')) {
	$chargeable = EntityManager::findOneById(Chargeable::class, (int)$id);
}
if (!$chargeable) {
	throw new UserException(sprintf('Article inconnu (n°%d).', $id));
}
$ha_form = EntityManager::findOneById(Form::class, $chargeable->id_form);
$credit_account = $chargeable->id_credit_account ? EntityManager::findOneById(Account::class, (int)$chargeable->id_credit_account) : null;
$debit_account = $chargeable->id_debit_account ? EntityManager::findOneById(Account::class, (int)$chargeable->id_debit_account) : null;

$tpl->assign([
	'chargeable' => $chargeable,
	'form' => $ha_form
]);

if (null !== qg('config'))
{
	$csrf_key = 'accounts_setting';

	$form->runIf('save', function () use ($chargeable) {
		if (array_key_exists('credit', $_POST)) {
			$id_credit_account = (int)PA_Form::getSelectorValue($_POST['credit']);
			$id_debit_account = (int)PA_Form::getSelectorValue($_POST['debit']);

			if (!DB::getInstance()->test(Account::TABLE, 'id = ? AND type = ?', $id_credit_account, Account::TYPE_REVENUE)) {
				throw new UserException('Le compte sélectionné pour le type de recette doit être un compte de revenue.');
			}
			if (!DB::getInstance()->test(Account::TABLE, 'id = ? AND type IN (?, ?, ?, ?)', $id_debit_account, Account::TYPE_NONE, Account::TYPE_BANK, Account::TYPE_CASH, Account::TYPE_OUTSTANDING)) {
				throw new UserException('Le compte d\'encaissement sélectionné doit être de type encaissement.');
			}
			$chargeable->set('id_credit_account', $id_credit_account);
			$chargeable->set('id_debit_account', $id_debit_account);
		}

		CF::updateChargeable($chargeable, (int)$_POST['id_category'], isset($_POST['id_fee']) ? (int)PA_Form::getSelectorValue($_POST['id_fee']) : 0);
		if (array_key_exists('custom_fields', $_POST)) {
			CF::updateCustomFields($_POST['custom_fields']);
		}
	}, $csrf_key, 'chargeable.php?id=' . $id . '&ok');

	$fee = $chargeable->fee();
	$service = $fee ? $chargeable->service() : null;

	$tpl->assign([
		'category_options' => CF::setCategoryOptions(),
		'selected_fee' => $fee ? [ (int)$fee->id => ($service->label . ' - ' . $fee->label) ] : null,
		'ca_type' => Account::TYPE_REVENUE,
		'da_type' => Account::TYPE_BANK . ':' . Account::TYPE_CASH . ':' . Account::TYPE_OUTSTANDING . '',
		'credit_account' => (null !== $credit_account) ? [ $credit_account->id => $credit_account->code . ' — ' . $credit_account->label ] : null,
		'debit_account' => (null !== $debit_account) ? [ $debit_account->id => $debit_account->code . ' — ' . $debit_account->label ] : null,
		'csrf_key' => $csrf_key,
	]);

	$tpl->display(PLUGIN_ROOT . '/templates/chargeable_config.tpl');
}
else
{
	$item = $chargeable->id_item ? EntityManager::findOneById(Item::class, $chargeable->id_item) : null;

	$tpl->assign([
		'category' => $chargeable->id_category ? EntityManager::findOneById(Category::class, $chargeable->id_category) : null,
		'credit_account' => (null !== $credit_account) ? ($credit_account->code . ' — ' . $credit_account->label) : null,
		'debit_account' => (null !== $debit_account) ? ($debit_account->code . ' — ' . $debit_account->label) : null,
		'parent_item' => $item,
		'dynamic_fields' => CF::setDynamicFieldOptions(),
		'orders' => Orders::list($chargeable),
		'orders_count_list' => Orders::listCountOpti($chargeable),
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
}