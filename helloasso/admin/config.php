<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Plugin\HelloAsso\Users;

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Accounting\Account;
use Garradin\Entities\Users\Category;
use Garradin\Users\DynamicFields;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = sprintf('config_plugin_%s', $plugin->id);

$ha = HelloAsso::getInstance();

$form->runIf('save', function () use ($ha) {
	if ($client_secret = f('client_secret')) {
		$ha->saveClient(f('client_id'), $client_secret);
	}
	// ToDo: add a nice form check
	$data = $_POST;
	$data['payer_map']['name'] = (int)$data['payer_map']['name'];
	foreach ($data['payer_map'] as $field => $value) {
		if ($value === 'null') {
			$data['payer_map'][$field] = null;
		}
	}
	$data['user_match_type'] = (int)$data['user_match_type'];
	$data['user_match_field'] = $data['user_match_field'] === '' ? null : $data['user_match_field'];
	$ha->saveConfig($data);
	Utils::redirect('?ok=' . ($client_secret ? 'connection' : 'config'));
}, $csrf_key, null);

$credit_account = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_credit_account);
$debit_account = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_debit_account);

$categories = EM::getInstance(Category::class)->all('SELECT * FROM @TABLE');
$category_options = [];
foreach ($categories as $category) {
	$category_options[(int)$category->id] = $category->name;
}

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
	'chart_id' => Plugin\HelloAsso\HelloAsso::CHART_ID, // ToDo: make it dynamic
	'default_credit_account' => (null !== $credit_account) ? [ $credit_account->id => $credit_account->code . ' — ' . $credit_account->label ] : null,
	'default_debit_account' => (null !== $debit_account) ? [ $debit_account->id => $debit_account->code . ' — ' . $debit_account->label ] : null,
	'category_options' => $category_options,
	'payer_fields' => $payer_fields,
	'dynamic_fields' => $dynamic_fields,
	'email_fields' => $email_fields,
	'user_match_fields' => Users::USER_MATCH_TYPES,
	'merge_names_options' => Users::MERGE_NAMES_OPTIONS
]);

$tpl->display(PLUGIN_ROOT . '/templates/config.tpl');
