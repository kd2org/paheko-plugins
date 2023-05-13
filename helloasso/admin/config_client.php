<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\HelloAsso;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = sprintf('config_plugin_%s', $plugin->id);

$ha = HelloAsso::getInstance();

$form->runIf('save', function () use ($ha) {
	$ha->saveClient(f('client_id'), f('client_secret'));
	// ToDo: add a nice form check
	$ha->saveConfig($_POST);
}, $csrf_key, '?ok');

$tpl->assign([
	'client_id'  => $ha->getClientId(),
	'secret'     => '',
	'csrf_key'   => $csrf_key,
	'restricted' => $ha->isTrial(),
]);

$tpl->display(PLUGIN_ROOT . '/templates/config_client.tpl');
