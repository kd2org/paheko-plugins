<?php

namespace Paheko;

use Paheko\Plugin\Caisse\{Sessions, Tabs, Products};

use function Paheko\Plugin\Caisse\{reload,get_amount};

require __DIR__ . '/_inc.php';

$tab = null;

if (null !== qg('id')) {
	$tab = Tabs::get(qg('id'));

	if (!$tab) {
		throw new UserException('La note sélectionnée n\'existe pas ou plus.');
	}
}

$current_pos_session = Sessions::get($tab ? $tab->session : (int)qg('session'));

if (!$current_pos_session) {
	throw new UserException('Aucune session de caisse en cours et aucune note sélectionnée');
}

if (!empty($_POST['add_item'])) {
	$tab->addItem((int)key($_POST['add_item']));
	reload();
}
elseif (qg('delete_item')) {
	$tab->removeItem((int)qg('delete_item'));
	Utils::redirect(Utils::plugin_url(['file' => 'tab.php', 'query' => 'id=' . $tab->id]));
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
	Utils::redirect(Utils::plugin_url(['file' => 'tab.php', 'query' => 'id=' . $tab->id]));
}
elseif (null !== qg('new')) {
	$tab = $current_pos_session->openTab();
	Utils::redirect(Utils::plugin_url(['file' => 'tab.php', 'query' => 'id=' . $tab->id()]));
}
elseif (!empty($_POST['rename_name'])) {
	$tab->rename($_POST['rename_name'], (int) f('rename_id') ?: null);
	reload();
}
elseif (!empty($_POST['rename_item'])) {
	$tab->renameItem((int) key($_POST['rename_item']), current($_POST['rename_item']));
	reload();
}
elseif (!empty($_POST['close'])) {
	$tab->close();
	reload();
}
elseif (!empty($_POST['reopen'])) {
	$tab->reopen();
	reload();
}
elseif (!empty($_POST['delete'])) {
	$tab->delete();
	Utils::redirect(Utils::plugin_url(['file' => 'tab.php', 'query' => 'session=' . $current_pos_session->id()]));
}

$tabs = Tabs::listForSession($current_pos_session->id);

$tpl->assign('pos_session', $current_pos_session);
$tpl->assign('tab_id', $tab ? $tab->id : null);

$tpl->assign('products_categories', Products::listByCategory());
$tpl->assign('tabs', $tabs);

if ($tab) {
	$tpl->assign('current_tab', $tab);
	$tpl->assign('items', $tab->listItems());
	$tpl->assign('existing_payments', $tab->listPayments());
	$tpl->assign('remainder', $tab->getRemainder());
	$tpl->assign('payment_options', $tab->listPaymentOptions());
}

$tpl->assign('title', 'Caisse ouverte le ' . Utils::date_fr($current_pos_session->opened));
$tpl->display(PLUGIN_ROOT . '/templates/tab.tpl');
