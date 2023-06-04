<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Orders;
use Garradin\Plugin\HelloAsso\Payments;
use Garradin\Plugin\HelloAsso\Items;
use Garradin\Plugin\HelloAsso\Options;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\HelloAsso as HA;

use KD2\DB\EntityManager as EM;

use Garradin\Entities\Users\User;

require __DIR__ . '/_inc.php';

$order = Orders::get((int)qg('id'));
$form = EM::findOneById(Form::class, (int)$order->id_form);

if (!$order) {
	throw new UserException('Commande inconnue');
}

$user = $order->id_user ? EM::findOneById(User::class, (int)$order->id_user) : null;
$payments = Payments::list($order);
$items = Items::list($order);
$options = Options::list($order);

$payer_infos = $order->getPayerInfos();

//$found_user = $ha->findUserForPayment($order->payer);
//$mapped_user = $ha->getMappedUser($order->payer);
$found_user = $mapped_user = [];

$user_match_field_label = (int)$plugin->getConfig()->user_match_type;

$tpl->assign('current_sub', 'orders');
$tpl->assign(compact('order', 'form', 'user', 'payments', 'items', 'options', 'payer_infos', 'found_user', 'mapped_user', 'user_match_field_label'));

$tpl->display(PLUGIN_ROOT . '/templates/order.tpl');
