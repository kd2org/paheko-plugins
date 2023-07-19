<?php

namespace Garradin;

require __DIR__ . '/_inc.php';
require __DIR__ . '/_init_current_year.php';

$session->requireAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE);

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Accounting\Account;
use Garradin\Entities\Users\Category;
use Garradin\Form as PA_Form;
use KD2\Form as KD2_Form;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\Sync;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Items;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Chargeables;
use Garradin\Plugin\HelloAsso\ControllerFunctions as CF;

$csrf_key = 'sync';
$csrf_field = KD2_Form::tokenFieldName($csrf_key);

$synchronize = function () use ($ha, $tpl, $csrf_field)
{
	$completed = $ha->sync();

	$exceptions = Items::getExceptions();
	if ($completed && !$exceptions) {
		Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php?ok=1');
	}
	elseif (!$completed) {
		Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php?continue=1&' . $csrf_field . '=' . $_POST[$csrf_field]);
	}

	$tpl->assign('exceptions', $exceptions);
};

// Emulate POST for runIf() compatibility
if (qg('continue')) {
	if (!array_key_exists($csrf_field, $_GET)) {
		throw new ValidationException('Une erreur est survenue, merci de bien vouloir renvoyer le formulaire.');
	}
	$_POST[$csrf_field] = $_GET[$csrf_field];
	$_POST['sync'] = 1;
}

$form->runIf('sync', $synchronize, $csrf_key);

$form->runIf('chargeable_config_submit', function() use ($ha, $tpl, $synchronize) {

	update_accounting();
	update_chargeables();
	update_custom_fields();

	call_user_func($synchronize);

}, $csrf_key);

$default_ca = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_credit_account);
$default_da = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_debit_account);

$sync = $ha->getSync();
$steps = Sync::STEPS;
unset($steps[Sync::COMPLETED_STEP]);

$tpl->assign([
	'sync' => $sync,
	'current_step_label' => Sync::STEPS[$sync->getStep()],
	'steps' => $steps,
	'csrf_key' => $csrf_key,
	'chargeables' => $sync->isCompleted() ? Chargeables::allForDisplay((bool)$plugin->getConfig()->accounting) : null,
	'chargeableTypes' => Chargeable::TYPES,
	'ca_type' => Account::TYPE_REVENUE,
	'da_type' => Account::TYPE_BANK . ':' . Account::TYPE_CASH . ':' . Account::TYPE_OUTSTANDING . '',
	'default_credit_account' => (null !== $default_ca) ? [ $default_ca->id => $default_ca->code . ' — ' . $default_ca->label ] : null,
	'default_debit_account' => (null !== $default_da) ? [ $default_da->id => $default_da->code . ' — ' . $default_da->label ] : null,
	'category_options' => CF::setCategoryOptions(),
	'forms' => $sync->isCompleted() ? Forms::getNeedingConfig() : null,
	'dynamic_fields' => CF::setDynamicFieldOptions()
]);

$tpl->display(PLUGIN_ROOT . '/templates/sync.tpl');


function update_accounting(): void
{
	if (array_key_exists('chargeable_credit', $_POST)) {
		$source = [];
		foreach ($_POST['chargeable_credit'] as $id_chargeable => $array)
		{
			$id_credit_account = PA_Form::getSelectorValue($array);
			$id_debit_account = PA_Form::getSelectorValue($_POST['chargeable_debit'][$id_chargeable]);

			if ($id_credit_account && $id_debit_account)
			{
				if (!DB::getInstance()->test(Account::TABLE, 'id = ? AND type = ?', (int)$id_credit_account, Account::TYPE_REVENUE)) {
					throw new UserException(sprintf('Le compte sélectionné (#%s) pour le type de recette est invalide.', $id_credit_account));
				}
				if (!DB::getInstance()->test(Account::TABLE, 'id = ? AND type IN (?, ?, ?, ?)', (int)$id_debit_account, Account::TYPE_NONE, Account::TYPE_BANK, Account::TYPE_CASH, Account::TYPE_OUTSTANDING)) {
					throw new UserException(sprintf('Le compte d\'encaissement sélectionné (#%s) est invalide.', $id_debit_account));
				}
				$source[$id_chargeable]['credit'] = (int)$id_credit_account;
				$source[$id_chargeable]['debit'] = (int)$id_debit_account;
			}
		}
		Chargeables::setAccounts($source);
	}
}

function update_chargeables(): void
{
	if (array_key_exists('id_category', $_POST)) {

		foreach ($_POST['id_category'] as $id_chargeable => $id_category) {
			if (($id_chargeable != (string)(int)$id_chargeable) || !DB::getInstance()->test(Chargeable::TABLE, 'id = ?', (int)$id_chargeable)) {
				throw new \RuntimeException(sprintf('Invalid chargeable ID: %s.', $id_chargeable));
			}
			if (($id_category != (string)(int)$id_category) || ($id_category > 0 && !DB::getInstance()->test(Category::TABLE, 'id = ?', (int)$id_category))) {
				throw new UserException(sprintf('La catégorie sélectionnée pour auto-inscription est invalide : #%s.', $id_category));
			}
		}

		$ids = array_map(function ($item) { return (int)$item; }, array_keys($_POST['id_category']));
		$placeholder = implode(', ', array_fill(0, count($ids), '?'));
		$em = EM::getInstance(Chargeable::class);
		foreach ($em->iterate('SELECT * FROM @TABLE WHERE id IN (' . $placeholder . ')', ...$ids) as $chargeable) {
			CF::updateChargeable($chargeable, (int)$_POST['id_category'][$chargeable->id], isset($_POST['id_fee'][$chargeable->id]) ? (int)PA_Form::getSelectorValue($_POST['id_fee'][$chargeable->id]) : 0);
		}
	}
}

function update_custom_fields(): void
{
	if (isset($_POST['custom_fields'])) {
		foreach ($_POST['custom_fields'] as $id_form => $fields) {
			CF::updateCustomFields($id_form, $fields);
		}
	}
}
