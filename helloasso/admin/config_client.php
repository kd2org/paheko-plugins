<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\HelloAsso;

use KD2\DB\EntityManager as EM;
use Garradin\Entities\Accounting\Account;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = sprintf('config_plugin_%s', $plugin->id);

$ha = HelloAsso::getInstance();

$form->runIf('save', function () use ($ha) {
	if ($client_secret = f('client_secret')) {
		$ha->saveClient(f('client_id'), $client_secret);
	}
	// ToDo: add a nice form check
	$ha->saveConfig($_POST);
	Utils::redirect('?ok=' . ($client_secret ? 'connection' : 'config'));
}, $csrf_key, null);

$credit_account = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_credit_account);
$debit_account = EM::findOneById(Account::class, (int)$plugin->getConfig()->id_debit_account);

$tpl->assign([
	'client_id'  => $ha->getClientId(),
	'secret'     => '',
	'csrf_key'   => $csrf_key,
	'restricted' => $ha->isTrial(),
	'chart_id' => Plugin\HelloAsso\HelloAsso::CHART_ID, // ToDo: make it dynamic
	'default_credit_account' => (null !== $credit_account) ? [ $credit_account->id => $credit_account->code . ' — ' . $credit_account->label ] : null,
	'default_debit_account' => (null !== $debit_account) ? [ $debit_account->id => $debit_account->code . ' — ' . $debit_account->label ] : null
]);

$tpl->display(PLUGIN_ROOT . '/templates/config_client.tpl');
