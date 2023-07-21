<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\Forms;

use Paheko\Plugin\HelloAsso\HelloAsso;
use Paheko\Plugin\HelloAsso\API;
use Paheko\Entities\Payments\Payment;
use Paheko\Payments\Payments;
use Paheko\Entities\Users\User;
use Paheko\Form as PA_Form;

use KD2\DB\EntityManager as EM;

require __DIR__ . '/_inc.php';
require __DIR__ . '/_init_current_year.php';

if ($plugin->needUpgrade()) {
	$plugin->upgrade();
}

if (!$ha->getSync()->getDate()) {
	Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php');
}

$csrf_key = 'checkout_creation';

$checkout = null;
$form->runIf('generate_checkout', function () use ($ha, $checkout, $session, $tpl) {
	// ToDo: add a nice check
	$payer = EM::findOneById(User::class, (int)PA_Form::getSelectorValue($_POST['user']));
	$checkout = $ha->createCheckout($_POST['org_slug'], $_POST['label'], (int)($_POST['amount'] * 100), (int)$session->getUser()->id, $payer, [ PA_Form::getSelectorValue($_POST['credit']), PA_Form::getSelectorValue($_POST['debit']) ]);

	$tpl->assign('checkout', $checkout);
}, $csrf_key);

$tpl->assign([
	'list' => Forms::list(),
	'restricted' => $ha::isTrial(),
	'orgOptions' => [ $ha->plugin()->getConfig()->default_organization => $ha->plugin()->getConfig()->default_organization ],
	'csrf_key' => $csrf_key
]);

$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
