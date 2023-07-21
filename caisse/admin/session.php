<?php

namespace Paheko;

use Paheko\Plugin\Caisse\Sessions;

require __DIR__ . '/_inc.php';

$pos_session = Sessions::get((int)qg('id'));

if (!$pos_session) {
	throw new UserException('Aucun numéro de session indiqué, ou numéro invalide');
}

echo $pos_session->export((bool) qg('details'), qg('pdf') ? 2 : 0);
