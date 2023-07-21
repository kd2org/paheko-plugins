<?php

namespace Paheko;

use Paheko\Users\Session;
use Paheko\Plugin\Caisse\Sessions;
use function Paheko\Plugin\Caisse\get_amount;

require __DIR__ . '/_inc.php';

Session::getInstance()->requireAccess(Session::SECTION_ACCOUNTING, Session::ACCESS_ADMIN);

$pos_session = Sessions::get((int)qg('id'));

if (!$pos_session) {
	throw new UserException('Numéro de session inconnu');
}

if (!$pos_session->closed) {
	throw new UserException('Cette session n\'est pas clôturée');
}

$csrf_key = 'pos_delete_' . $pos_session->id();

$form->runIf(f('delete') && f('confirm_delete'), fn() => $pos_session->delete(), $csrf_key, Utils::plugin_url());

$tpl->assign(compact('csrf_key', 'pos_session'));

$tpl->display(PLUGIN_ROOT . '/templates/session_delete.tpl');
