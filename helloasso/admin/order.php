<?php

namespace Garradin;

use Garradin\Plugin\HelloAsso\Orders;
use Garradin\Plugin\HelloAsso\Payments;
use Garradin\Plugin\HelloAsso\Items;
use Garradin\Plugin\HelloAsso\Users;
use Garradin\Plugin\HelloAsso\Options;
use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\HelloAsso as HA;
use Garradin\Plugin\HelloAsso\NotFoundException;

use KD2\DB\EntityManager as EM;

use Garradin\Entities\Users\User;

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
$user = $order->id_user ? EM::findOneById(User::class, (int)$order->id_user) : null;
$payments = Payments::list($order);
$items = Items::list($order);
$options = Options::list($order);
$payer_infos = $order->getRawPayerInfos();
$guessed_user = Users::findUserMatchingPayer($order->getRawPayer());

$user_match_field_label = (int)$plugin->getConfig()->user_match_type;

$tpl->assign('current_sub', 'orders');
$tpl->assign(compact('order', 'form', 'user', 'payments', 'items', 'options', 'payer_infos', 'guessed_user', 'user_match_field_label', 'csrf_key'));

$tpl->display(PLUGIN_ROOT . '/templates/order.tpl');
