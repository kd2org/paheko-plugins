<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Forms;

use Garradin\Plugin\HelloAsso\HelloAsso;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Entities\Payments\Payment;
use Garradin\Payments\Payments;
use Garradin\Entities\Users\User;

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
	$user = EM::findOneById(User::class, (int)(array_keys($_POST['user'])[0]));
	$checkout = $ha->createCheckout($_POST['org_slug'], $_POST['label'], (int)($_POST['amount'] * 100), $user, [ array_keys($_POST['credit'])[0], array_keys($_POST['debit'])[0] ]);

	$tpl->assign('checkout', $checkout);
});

$tpl->assign([
	'list' => Forms::list(),
	'restricted' => $ha::isTrial(),
	'orgOptions' => [ $ha->plugin()->getConfig()->default_organization => $ha->plugin()->getConfig()->default_organization ]
]);

$tpl->display(PLUGIN_ROOT . '/templates/index.tpl');
