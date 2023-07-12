<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Forms;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Entities\Payments\Payment;
use Garradin\Payments\Payments;
use Garradin\Entities\Users\User;
use Garradin\Form as PA_Form;

use KD2\DB\EntityManager as EM;

require __DIR__ . '/_inc.php';
require __DIR__ . '/_init_current_year.php';

if ($plugin->needUpgrade()) {
	$plugin->upgrade();
}

if (!$ha->getLastSync()) {
	Utils::redirect(PLUGIN_ADMIN_URL . 'sync.php');
}

$checkout = null;
$form->runIf('generate_checkout', function () use ($ha, $checkout, $tpl) {
	// ToDo: add a nice check
	$user = EM::findOneById(User::class, (int)PA_Form::getSelectorValue($_POST['user']));
	$checkout = $ha->createCheckout($_POST['org_slug'], $_POST['label'], (int)($_POST['amount'] * 100), $user, [ PA_Form::getSelectorValue($_POST['credit']), PA_Form::getSelectorValue($_POST['debit']) ]);

	$tpl->assign('checkout', $checkout);
});

$tpl->assign([
	'list' => Forms::list(),
	'restricted' => $ha::isTrial(),
	'orgOptions' => [ $ha->plugin()->getConfig()->default_organization => $ha->plugin()->getConfig()->default_organization ]
]);

$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
