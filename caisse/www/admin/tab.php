<?php

namespace Garradin;

use Garradin\Plugin\Caisse\{Session, Tab, Product};

use function Garradin\Plugin\Caisse\{reload,get_amount};

require __DIR__ . '/_inc.php';

$tab = $tab_id = null;

if ($tab_id = qg('id')) {
	$tab = new Tab($tab_id);
}

$current_pos_session = new Session($tab ? $tab->session : Session::getCurrentId());


if (!empty($_POST['add_item'])) {
	$tab->addItem((int)key($_POST['add_item']));
	reload();
}
elseif (qg('delete_item')) {
	$tab->removeItem((int)qg('delete_item'));
	reload();
}
elseif (!empty($_POST['change_qty'])) {
	$tab->updateItemQty((int)key($_POST['change_qty']), (int)current($_POST['change_qty']));
	reload();
}
elseif (!empty($_POST['change_price'])) {
	$tab->updateItemPrice((int)key($_POST['change_price']), (int)get_amount(current($_POST['change_price'])));
	reload();
}
elseif (!empty($_POST['pay'])) {
	$tab->pay((int)$_POST['method_id'], get_amount(f('amount')), $_POST['reference']);
	reload();
}
elseif (qg('delete_payment')) {
	$tab->removePayment((int) qg('delete_payment'));
	reload();
}
elseif (null !== qg('new')) {
	$id = Tab::open($current_pos_session->id);
	Utils::redirect(Utils::plugin_url(['file' => 'tab.php', 'query' => 'id=' . $id]));
}
elseif (!empty($_POST['rename'])) {
	$tab->rename($_POST['rename']);
	reload();
}
elseif (!empty($_POST['close'])) {
	$tab->close();
	reload();
}
elseif (!empty($_POST['delete'])) {
	$tab->delete();
	Utils::redirect(Utils::plugin_url(['file' => 'tab.php']));
}

$tabs = Tab::listForSession($current_pos_session->id);

$tpl->assign('pos_session', $current_pos_session);
$tpl->assign('tab_id', $tab_id);

$tpl->assign('products_categories', Product::listByCategory());
$tpl->assign('tabs', $tabs);

if ($tab_id) {
	$tpl->assign('current_tab', $tabs[$tab_id]);
	$tpl->assign('items', $tab->listItems());
	$tpl->assign('existing_payments', $tab->listPayments());
	$tpl->assign('remainder', $tab->getRemainder());

	$options = $tab->listPaymentOptions();
	$eligible = 0;

	foreach ($options as $option) {
		if ($option->id == 3) {
			$eligible = $option->amount;
		}
	}

	$tpl->assign('eligible_alveole', $eligible);
	$tpl->assign('payment_options', $options);
}

$tpl->register_modifier('show_methods', function ($m) {
	$m = explode(',', $m);
	if (in_array(3, $m)) {
		return '<i>🚲</i>';
	}
});

$tpl->assign('title', 'Caisse ouverte le ' . Utils::sqliteDateToFrench($current_pos_session->opened));
$tpl->display(PLUGIN_ROOT . '/templates/tab.tpl');