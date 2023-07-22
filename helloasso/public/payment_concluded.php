<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\HelloAsso;
use Paheko\Entities\Payments\Payment;
use KD2\DB\EntityManager as EM;

require_once __DIR__ . '/../../../../include/init.php';

if (!array_key_exists('p', $_GET) || !array_key_exists('checkoutIntentId', $_GET) || !array_key_exists('code', $_GET)) {
	throw new \RuntimeException(sprintf('HelloAsso API error: missing parameter "p", "checkoutIntentId" or "code".'));
}

HelloAsso::handlePaymentResult((int)$_GET['p'], (int)$_GET['checkoutIntentId'], $_GET['code']);

$code = $_GET['code'];
if ($code === 'succeeded') {
	$tpl->assign('title', HelloAsso::PROVIDER_LABEL . ' - Paiement validé');
}
elseif ($code === 'refused') {
	$tpl->assign('title', HelloAsso::PROVIDER_LABEL . ' - Paiement refusé');
}
else {
	$tpl->assign('title', HelloAsso::PROVIDER_LABEL . ' - Paiement en cours de traitement');
}

if (!$payment = EM::findOneById(Payment::class, (int)$_GET['p'])) {
	throw new \RuntimeException(sprintf('Paiement n°%d introuvable.', $_GET['p']));
}

$tpl->assign([
	'code' => $code,
	'payment' => $payment,
	'method' => Payment::METHODS[$payment->method],
	'type' => Payment::TYPES[$payment->type]
]);
$tpl->display(PLUGIN_ROOT . '/templates/payment_concluded.tpl');
