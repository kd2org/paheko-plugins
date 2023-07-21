<?php

namespace Paheko;

use Paheko\Users\Session;
use Paheko\Plugin\Caisse\Sessions;
use function Paheko\Plugin\Caisse\get_amount;

require __DIR__ . '/_inc.php';

$pos_session = Sessions::get((int)qg('id'));

if (!$pos_session) {
	throw new UserException('Numéro de session inconnu');
}

if ($pos_session->closed) {
	throw new UserException('Cette session est déjà clôturée');
}

if (isset($_POST['close'], $_POST['amount']) && !empty($_POST['confirm'])) {
	$payments = f('payments') ? array_keys(f('payments')) : [];
	$pos_session->close(
		f('user_name') ?: Session::getInstance()->getUser()->name(),
		get_amount(f('amount')),
		(bool) f('recheck'),
		$payments,
		$plugin->getConfig('send_email_when_closing')
	);
	Utils::redirect(Utils::plugin_url(['file' => 'session.php', 'query' => 'id=' . $pos_session->id]));
}

$tpl->assign('pos_session', $pos_session);

$tpl->assign('open_notes', $pos_session->hasOpenNotes());

$cash_total = $pos_session->getCashTotal();
$tpl->assign('cash_total', $cash_total);
$tpl->assign('user_name', $session->getUser()->name());
$tpl->assign('close_total', $cash_total + $pos_session->open_amount);
$tpl->assign('payments_except_cash', $pos_session->listPaymentWithoutCash());

$tpl->display(PLUGIN_ROOT . '/templates/session_close.tpl');
