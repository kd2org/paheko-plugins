<?php

namespace Garradin;

require_once __DIR__ . '/_inc.php';

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = 'caisse_config';

$form->runIf('save', function () use ($plugin) {
	$plugin->setConfigProperty('allow_custom_user_name', (bool)f('allow_custom_user_name'));
	$plugin->setConfigProperty('send_email_when_closing', trim(f('send_email_when_closing') ?: '') ?: null);
}, $csrf_key, PLUGIN_ADMIN_URL . 'config.php?ok');

$tpl->assign(compact('csrf_key'));

$tpl->display(PLUGIN_ROOT . '/templates/config.tpl');
