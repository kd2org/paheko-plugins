<?php

namespace Garradin;

use KD2\DB\EntityManager;
use Garradin\Payments\Payments;
use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Entities\Payments\Payment;
use Garradin\Entities\Accounting\Transaction;
use Garradin\UserException;
use Garradin\Entities\Users\User;
use Garradin\Plugin\HelloAsso\Forms;
use Garradin\Plugin\HelloAsso\Orders;

require __DIR__ . '/_inc.php';

if ($id = qg('id')) {
	$payment = EntityManager::findOneById(Payment::class, (int)$id);
}
elseif ($ref = qg('ref')) {
	$payment = Payments::getByReference(HelloAsso::PROVIDER_NAME, $ref);
}
if (!$payment) {
	throw new UserException('Paiement inconnu');
}
$author = EntityManager::findOneById(User::class, (int)$payment->id_author);
$form = $payment->id_form ? Forms::get($payment->id_form) : null;
$order = Orders::get($payment->id_order);
$transactions = EntityManager::getInstance(Transaction::class)->all('SELECT * FROM @TABLE WHERE reference = :reference', $payment->id);

$tpl->assign(compact('payment', 'author', 'form', 'order', 'transactions'));
$tpl->assign('current_sub', 'payments');

$tpl->assign('TECH_DETAILS', SHOW_ERRORS && ENABLE_TECH_DETAILS);

$tpl->display(PLUGIN_ROOT . '/templates/payment.tpl');
