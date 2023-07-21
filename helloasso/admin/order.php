<?php

namespace Paheko;

use Paheko\Plugin\HelloAsso\Orders;
use Paheko\Plugin\HelloAsso\Payments;
use Paheko\Plugin\HelloAsso\Items;
use Paheko\Plugin\HelloAsso\Users;
use Paheko\Plugin\HelloAsso\Options;
use Paheko\Plugin\HelloAsso\Entities\Form;
use Paheko\Plugin\HelloAsso\HelloAsso as HA;
use Paheko\Plugin\HelloAsso\NotFoundException;

use KD2\DB\EntityManager as EM;

use Paheko\Entities\Users\User;

require __DIR__ . '/_inc.php';

$order = Orders::get((int)qg('id'));
if (!$order) {
	throw new UserException('Commande inconnue');
}

$csrf_key = 'create_payer';
$form->runIf('create_payer', function () use ($order) {
	$order->registerRawPayer();
}, $csrf_key, '?id=' .(int)$order->id. '&ok=1');

$form = EM::findOneById(Form::class, (int)$order->id_form);
$payer = $order->id_payer ? EM::findOneById(User::class, (int)$order->id_payer) : null;
$payments = Payments::list(null, $order);
$items = Items::list($order);
$items_count_list = Items::listCountOpti($order);
$options = Options::list($order);
$options_count_list = Options::listCountOpti($order);
$payer_infos = $order->getRawPayerInfos();
$guessed_user = Users::findUserMatchingPayer($order->getRawPayer());

$user_match_field_label = (int)$plugin->getConfig()->user_match_type;

$tpl->assign('current_sub', 'orders');
$tpl->assign(compact('order', 'form', 'payer', 'payments', 'items', 'items_count_list', 'options', 'options_count_list', 'payer_infos', 'guessed_user', 'user_match_field_label', 'csrf_key'));

$tpl->display(PLUGIN_ROOT . '/templates/order.tpl');
