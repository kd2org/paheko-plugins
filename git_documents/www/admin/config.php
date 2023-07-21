<?php

namespace Paheko;

$session->requireAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN);

$csrf_key = 'git__config';

$form->runIf('save', function () use ($plugin) {
	$plugin->setConfig('diff_email', trim(f('diff_email')));
}, $csrf_key, './config.php?ok');

$tpl->assign(compact('csrf_key'));

$tpl->display(PLUGIN_ROOT . '/templates/config.tpl');
