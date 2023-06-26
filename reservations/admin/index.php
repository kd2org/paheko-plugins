<?php

namespace Garradin;
use Garradin\Plugin\Reservations\Reservations;

if ($plugin->needUpgrade()) {
	$plugin->upgrade();
}

if ($session->canAccess($session::SECTION_USERS, $session::ACCESS_WRITE)) {
	Utils::redirect(Utils::plugin_url(['file' => 'bookings.php']));
}

$r = new Reservations;

$user = $session->getUser();

if (isset($_POST['book'], $_POST['slot'])) {
	$identite = $config->get('champ_identite');
	$nom = $user->$identite;
	$r->createUserBooking($_POST['slot'], $nom, f('champ'));
	Utils::redirect(Utils::getSelfURI());
}
elseif (isset($_POST['cancel'])) {
	$r->cancelUserBooking();
	Utils::redirect(Utils::getSelfURI());
}

$booking = $r->getUserBooking();
$cat_id = $cat = null;

if ($booking) {
	$cat_id = $booking->categorie;
}
elseif (qg('cat')) {
	$cat_id = (int) qg('cat');
}
else {
	$categories = $r->listCategories();

	if (count($categories) == 1) {
		$cat_id = current($categories)->id;
	}
}

if ($cat_id) {
	$cat = $r->getCategory($cat_id);

	if (!$cat) {
		throw new UserException('Catégorie inconnue');
	}
}

if (!$cat) {
	$tpl->assign('categories', $categories);
}
else {
	$tpl->assign('slots', $r->listUpcomingSlots($cat->id));
}

$tpl->assign('cat', $cat);
$tpl->assign('booking', $booking);
$tpl->assign('plugin_css', ['style.css']);
$tpl->assign('custom_css', ['wiki.css']);

$tpl->display(PLUGIN_ROOT . '/templates/admin/index.tpl');
