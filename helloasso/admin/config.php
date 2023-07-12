<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Plugin\HelloAsso\Users;
use Garradin\Plugin\HelloAsso\ControllerFunctions as CF;

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Accounting\Account;
use Garradin\Entities\Users\Category;
use Garradin\Entities\Users\DynamicField;
use Garradin\Users\DynamicFields;
use Garradin\Form as PA_Form;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

require __DIR__ . '/_init_current_year.php';

$csrf_key = sprintf('config_plugin_%s', $plugin->id);

$ha = HelloAsso::getInstance();

$form->runIf('save', function () use ($ha) {
	if ($client_secret = f('client_secret')) {
		$ha->saveClient(f('client_id'), $client_secret);
	}

	if (!array_key_exists($_POST['accounting'], $ha::ACCOUNTING_OPTIONS)) {
		throw new UserException(sprintf('Choix pour la liaison avec la comptabilité invalide.'));
	}
	if (!array_key_exists($_POST['user_match_type'], Users::USER_MATCH_TYPES)) {
		throw new UserException(sprintf('Champ utilisé pour savoir si un membre existe déjà invalide.'));
	}
	if (strlen($_POST['user_match_field']) > 200) {
		throw new UserException(sprintf('L\'intitulé du champ ' . $ha::PROVIDER_LABEL . ' correspondant à "Courriel" ne peut faire plus de 200 caractères.'));
	}
	if (array_key_exists('default_credit', $_POST) && !DB::getInstance()->test(Account::TABLE, 'id = ? AND type = ?', PA_Form::getSelectorValue($_POST['default_credit']), Account::TYPE_REVENUE)) {
		throw new UserException('Le compte sélectionné pour le type de recette doit être un compte de revenue.');
	}
	if (array_key_exists('default_debit', $_POST) && !DB::getInstance()->test(Account::TABLE, 'id = ? AND type IN (?, ?, ?, ?)', PA_Form::getSelectorValue($_POST['default_debit']), Account::TYPE_NONE, Account::TYPE_BANK, Account::TYPE_CASH, Account::TYPE_OUTSTANDING)) {
		throw new UserException('Le compte d\'encaissement sélectionné doit être de type encaissement.');
	}

	$data = $_POST;
	$data['payer_map']['name'] = (int)$data['payer_map']['name'];

	if (!array_key_exists($data['payer_map']['name'], Users::USER_MATCH_TYPES)) {
		throw new UserException(sprintf('L\'ordre de fusion des champs nom et prénom est invalide.'));
	}

	foreach ($data['payer_map'] as $field => $value) {
		if ($field !== 'name')
		{
			if (!array_key_exists($field, API::PAYER_FIELDS)) {
				throw new \RuntimeException(sprintf('Le champ "%s" ne fait pas partie des informations sur le/la payeur/euse.', $field));
			}
			if ($value === 'null') {
				$data['payer_map'][$field] = null;
			}
			elseif (!DB::getInstance()->test(DynamicField::TABLE, 'name = ?', $value)) {
				throw new UserException(sprintf('Correspondance pour "%s" invalide : %s.', $field, $value));
			}
		}
	}
	$data['user_match_type'] = (int)$data['user_match_type'];
	$data['user_match_field'] = $data['user_match_field'] === '' ? null : $data['user_match_field'];
	$data['default_credit'] = array_key_exists('default_credit', $data) ? (int)PA_Form::getSelectorValue($data['default_credit']) : null;
	$data['default_debit'] = array_key_exists('default_debit', $data) ? (int)PA_Form::getSelectorValue($data['default_debit']) : null;

	$ha->saveConfig($data);
	Utils::redirect('?ok=' . ($client_secret ? 'connection' : 'config'));
}, $csrf_key, null);

$credit_account = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_credit_account);
$debit_account = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_debit_account);

$dynamic_fields = $email_fields = [
	'null' => '-- Ne pas importer',
];
$user_match_fields = [];

$fields = DynamicFields::getInstance()->all();
foreach ($fields as $key => $config) {
	if (!isset($config->label)) {
		continue;
	}
	$dynamic_fields[$key] = $config->label;
}

$payer_fields = API::PAYER_FIELDS;
// The following fields have a specific process
unset($payer_fields['firstName']);
unset($payer_fields['lastName']);
unset($payer_fields['email']);

$fields = DynamicFields::getEmailFields();
foreach ($fields as $field) {
	$email_fields[$field] = $field;
	$user_match_fields[$field] = $field;
}

$fields = DynamicFields::getNameFields();
foreach ($fields as $field) {
	$user_match_fields[$field] = $field;
}

$tpl->assign([
	'client_id'  => $ha->getClientId(),
	'secret'     => '',
	'csrf_key'   => $csrf_key,
	'restricted' => $ha->isTrial(),
	'default_credit_account' => (null !== $credit_account) ? [ $credit_account->id => $credit_account->code . ' — ' . $credit_account->label ] : null,
	'default_debit_account' => (null !== $debit_account) ? [ $debit_account->id => $debit_account->code . ' — ' . $debit_account->label ] : null,
	'payer_fields' => $payer_fields,
	'dynamic_fields' => $dynamic_fields,
	'email_fields' => $email_fields,
	'user_match_fields' => Users::USER_MATCH_TYPES,
	'merge_names_options' => Users::MERGE_NAMES_OPTIONS
]);

$tpl->display(PLUGIN_ROOT . '/templates/config.tpl');
