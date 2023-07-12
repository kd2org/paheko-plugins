<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Entities\Users\User;
use Garradin\UserException;

require __DIR__ . '/_inc.php';

$id = \Garradin\qg('id');
$email = \Garradin\qg('email');
$first_name = \Garradin\qg('first_name');
$last_name = \Garradin\qg('last_name');

if (!$id && !$email &&!$first_name && !$last_name) {
	throw new UserException('Aucun·e payeur/euse sélectionné·e.');
}

$payer = $id ? Payers::get((int)$id) : Payers::getRawData($email ? $email : [ 'first_name' => $first_name, 'last_name' => $last_name ]);

if (!$payer) {
	throw new UserException(sprintf('Payeur/euse #%s introuvable.', $id ?? ($email ?? $first_name . ' ' . $last_name)));
}

$tpl->assign([
	'payer' => ($payer instanceof User) ? $payer : Users::getMappedUser($payer, false),
	'orders' => Orders::list($payer),
	'orders_count_list' => Orders::listCountOpti($payer)
]);
$tpl->display(\Garradin\PLUGIN_ROOT . '/templates/payer.tpl');
