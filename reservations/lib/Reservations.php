<?php

namespace Garradin\Plugin\Reservations;

use Garradin\Plugin;
use Garradin\DB;
use Garradin\Config;
use Garradin\UserException;
use KD2\MiniSkel;
use KD2\MiniSkel_Exception;
use DateTime;

use const Garradin\SECRET_KEY;

class Reservations
{
	const COOKIE_NAME = 'reservation';

	public function listCategories()
	{
		return DB::getInstance()->get('SELECT * FROM plugin_reservations_categories ORDER BY nom COLLATE U_NOCASE;');
	}

	public function addCategory(string $nom)
	{
		return DB::getInstance()->insert('plugin_reservations_categories', ['nom' => $nom]);
	}

	public function updateCategory(int $id, string $nom, ?string $description, ?array $champ)
	{
		$db = DB::getInstance();

		if ($champ) {
			$champs = Config::getInstance()->get('champs_membres');
			$champ = array_intersect_key($champ, array_flip(['mandatory', 'title', 'help']));
			$champ = (object)$champ;

			if (empty($champ->title)) {
				throw new UserException('Le libellé de champ ne peut rester vide');
			}

			$champ = json_encode($champ);
		}

		return $db->update('plugin_reservations_categories', compact('nom', 'description', 'champ'), 'id = :id', compact('id'));
	}

	public function getCategory(int $id) {
		$cat = DB::getInstance()->first('SELECT * FROM plugin_reservations_categories WHERE id = ?;', $id);

		if (!$cat) {
			return $cat;
		}

		$cat->champ = json_decode($cat->champ ?? '');

		if (!empty($cat->champ) && is_object($cat->champ)) {
			$cat->champ->type = 'text';
		}
		else {
			$cat->champ = null;
		}

		return $cat;
	}

	public function deleteCategory(int $id)
	{
		return DB::getInstance()->delete('plugin_reservations_categories', 'id = ?', $id);
	}

	public function listSlots(int $cat_id)
	{
		return DB::getInstance()->get('SELECT id, * FROM plugin_reservations_creneaux WHERE categorie = ? ORDER BY jour, heure;', $cat_id);
	}

	public function listUpcomingBookings(int $cat_id)
	{
		$query = 'SELECT p.*, datetime(p.date) AS date, c.categorie
			FROM plugin_reservations_personnes p
			INNER JOIN plugin_reservations_creneaux c ON c.id = p.creneau
			WHERE date(date) >= date(\'now\') AND categorie = ? ORDER BY date;';
		$bookings = DB::getInstance()->get($query, $cat_id);

		$date = null;
		$hour = null;
		foreach ($bookings as &$booking) {
			$d = DateTime::createFromFormat('!Y-m-d H:i:s', $booking->date);
			$h = $d->format('Hi');
			$d = $d->format('Ymd');

			if ($date !== $d) {
				$booking->date_change = true;
				$booking->hour_change = true;
				$date = $d;
				$hour = $h;
			}
			elseif ($h !== $hour) {
				$booking->hour_change = true;
				$hour = $h;
			}
		}

		return $bookings;
	}

	/**
	 * Deletes slots that are not in the array
	 */
	public function deleteMissingSlots(int $cat_id, array $ids)
	{
		$db = DB::getInstance();
		return $db->exec(sprintf('DELETE FROM plugin_reservations_creneaux WHERE categorie = %d AND %s;', $cat_id, $db->where('id', 'NOT IN', $ids)));
	}

	public function listUpcomingSlots(int $cat_id)
	{
		$slots = DB::getInstance()->get('SELECT *,
			(SELECT COUNT(*) FROM plugin_reservations_personnes prp WHERE creneau = a.id AND date(prp.date) = a.date) AS jauge
			FROM (
				SELECT id, heure, maximum,
				CASE WHEN repetition = 1 AND jour < date(\'now\', \'localtime\') THEN
					date(\'now\', \'localtime\', strftime(\'weekday %w\', jour))
				ELSE
					jour
				END AS date
				FROM plugin_reservations_creneaux prc
				WHERE (jour >= date(\'now\', \'localtime\') OR repetition = 1)
				AND categorie = ?
				ORDER BY date, heure
			) AS a;', $cat_id);

		$date = null;
		$hour_now = date('Hi');
		$day_now = date('Y-m-d');

		foreach ($slots as &$slot) {
			if ($date !== $slot->date) {
				$slot->date_change = true;
				$date = $slot->date;
			}

			$slot_hour = (int) str_replace(':', '', $slot->heure);
			$slot->timestamp = DateTime::createFromFormat('!Y-m-d', $slot->date)->getTimestamp();
			$slot->available = max(0, $slot->maximum - $slot->jauge);

			if ($day_now == $slot->date && $hour_now > $slot_hour) {
				$slot->bookable = false;
			}
			else {
				$slot->bookable = true;
			}
		}

		return $slots;
	}

	public function createSlot(int $cat_id, string $day, string $hour, bool $repeat, int $max)
	{
		if (preg_match('!^(\d{2})/(\d{2})/(\d{4})$!', $day, $match)) {
			$day = sprintf('%s-%s-%s', $match[3], $match[2], $match[1]);
		}

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
			throw new UserException('Date invalide');
		}

		if (!preg_match('/^\d{2}:\d{2}$/', $hour)) {
			throw new UserException('Heure invalide');
		}

		$db = DB::getInstance();

		$db->preparedQuery('INSERT OR IGNORE INTO plugin_reservations_creneaux (categorie, jour, heure, repetition, maximum) VALUES (?, ?, ?, ?, ?);', [$cat_id, $day, $hour, (int)$repeat, abs($max)]);

		return $db->firstColumn('SELECT id FROM plugin_reservations_creneaux WHERE categorie = ? AND jour = ? AND heure = ?;', $cat_id, $day, $hour);
	}

	public function updateSlot(int $id, string $day, string $hour, bool $repeat, int $max)
	{
		if (preg_match('!^(\d{2})/(\d{2})/(\d{4})$!', $day, $match)) {
			$day = sprintf('%s-%s-%s', $match[3], $match[2], $match[1]);
		}

		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
			throw new UserException('Date invalide');
		}

		if (!preg_match('/^\d{2}:\d{2}$/', $hour)) {
			throw new UserException('Heure invalide');
		}

		$db = DB::getInstance();

		$db->preparedQuery('DELETE FROM plugin_reservations_personnes WHERE creneau = ? AND date(date) < ?;', [$id, $day]);

		return $db->preparedQuery('UPDATE OR IGNORE plugin_reservations_creneaux SET jour = ?, heure = ?, repetition = ?, maximum = ? WHERE id = ?;', [$day, $hour, (int)$repeat, abs($max), $id]);
	}

	public function createBooking(int $slot_id, DateTime $date, string $nom, ?string $champ)
	{
		$db = DB::getInstance();

		$db->preparedQuery('REPLACE INTO plugin_reservations_personnes (creneau, date, nom, champ)
			VALUES (?, ?, ?, ?);', [$slot_id, $date, $nom, $champ]);

		return $db->lastInsertId();
	}

	public function checkNewBooking(int $slot_id, DateTime &$date, string $nom, ?string $champ)
	{
		$db = DB::getInstance();

		if (strtolower(trim($nom)) == 'zap') {
			die(' ');
		}

		// Pour qu'une réservation soit valide, il faut qu'elle soit à la date-même
		// ou alors si la répétition est activée, au même jour d'une date ultérieure
		$test = 'id = :id AND (
			(repetition = 1 AND :date >= jour AND strftime(\'%w\', jour) = strftime(\'%w\', :date))
			OR jour = :date)';

		$booking = $db->first('SELECT prc.*,
			(SELECT COUNT(*) FROM plugin_reservations_personnes prp WHERE creneau = prc.id AND date(date) = :date) AS jauge
			FROM plugin_reservations_creneaux prc
			WHERE ' . $test, ['id' => $slot_id, 'date' => $date->format('Y-m-d')]);

		if (!$booking) {
			throw new UserException('Date ou créneau invalide');
		}

		if ($booking->jauge >= $booking->maximum) {
			throw new UserException('Ce créneau est déjà complet, désolé !');
		}

		$hour = explode(':', $booking->heure);
		$date->setTime($hour[0], $hour[1], 0);

		if (trim($nom) === '') {
			throw new UserException('Le nom doit être renseigné');
		}

		$cat = $this->getCategory($booking->categorie);

		if ($cat->champ && !empty($cat->champ->mandatory) && trim($champ) === '') {
			throw new UserException(sprintf('%s: merci de renseigner cette information', $cat->champ->title));
		}
	}

	public function deleteBooking(int $id)
	{
		return DB::getInstance()->delete('plugin_reservations_personnes', 'id = ?', $id);
	}

	public function getUserBooking()
	{
		$id = $this->getUserBookingId();

		if (!$id) {
			return null;
		}

		return DB::getInstance()->first('SELECT p.*,
			c.categorie,
			cat.nom AS nom_categorie
			FROM plugin_reservations_personnes p
			INNER JOIN plugin_reservations_creneaux c ON c.id = p.creneau
			LEFT JOIN plugin_reservations_categories cat ON cat.id = c.categorie AND (SELECT COUNT(*) FROM plugin_reservations_categories) > 1
			WHERE p.id = ?;', $id);
	}

	protected function getUserBookingId()
	{
		if (empty($_COOKIE[self::COOKIE_NAME])) {
			return null;
		}

		$id = (int) strtok($_COOKIE[self::COOKIE_NAME], '/');
		$hash = strtok('');

		if (!$id || !$hash) {
			return null;
		}

		if ($hash !== hash_hmac('sha256', $id, SECRET_KEY)) {
			return null;
		}

		return $id;
	}

	protected function setUserBooking(int $id, DateTime $expiry)
	{
		$cookie = sprintf('%d/%s', $id, hash_hmac('sha256', $id, SECRET_KEY));
		setcookie(self::COOKIE_NAME, $cookie, $expiry->getTimestamp());
		$_COOKIE[self::COOKIE_NAME] = $cookie;
	}

	public function cancelUserBooking()
	{
		$id = $this->getUserBookingId();

		if (!$id) {
			return;
		}

		setcookie(self::COOKIE_NAME, '', -1);
		unset($_COOKIE[self::COOKIE_NAME]);

		return $this->deleteBooking($id);
	}

	public function createBookingForUser(string $slot_code, string $nom, ?string $champ)
	{
		$slot_id = (int)strtok($slot_code, '=');
		$date = strtok('');

		try {
			$date = DateTime::createFromFormat('Y-m-d', $date, new \DateTimeZone('UTC'));
		}
		catch (\Exception $e) {
			$date = null;
		}

		if (!$slot_id || !$date) {
			throw new UserException('Erreur dans la date');
		}

		$this->checkNewBooking($slot_id, $date, $nom, $champ);

		return $this->createBooking($slot_id, $date, $nom, $champ);
	}

	public function createUserBooking(string $slot_code, string $nom, ?string $champ)
	{
		$slot_id = (int)strtok($slot_code, '=');
		$date = strtok('');

		try {
			$date = DateTime::createFromFormat('Y-m-d', $date, new \DateTimeZone('UTC'));
		}
		catch (\Exception $e) {
			$date = null;
		}

		if (!$slot_id || !$date) {
			throw new UserException('Erreur dans la date');
		}

		$this->checkNewBooking($slot_id, $date, $nom, $champ);

		// Cancel old booking
		$this->cancelUserBooking();

		$id = $this->createBooking($slot_id, $date, $nom, $champ);

		return $this->setUserBooking($id, $date);
	}

	public function pruneBookings(int $days)
	{
		return DB::getInstance()->preparedQuery('DELETE FROM plugin_reservations_personnes WHERE date < datetime(\'now\', ? || \' days\');', [-$days]);
	}

	public function __destruct()
	{
		$this->pruneBookings(1);
	}

/*
	static public function boucle(array &$params, array &$return)
	{
		foreach ($params['loopCriterias'] as $criteria)
		{
			if ($criteria['action'] != MiniSkel::ACTION_MATCH_FIELD) {
				continue;
			}

			if ($criteria['field'] == 'futur') {
				// Retourne les prochains créneaux à venir
				$return['query'] = 'SELECT id, heure, maximum,
					CASE WHEN repetition = 1 THEN
						strftime(\'%s\', \'now\', strftime(\'weekday %w\', date))
					ELSE
						strftime(\'%s\', date)
					END AS date,
					(SELECT COUNT(*) FROM plugin_reservations_personnes prp WHERE creneau = prc.id AND pr.date = date) AS jauge,
					maximum - jauge AS places
					FROM plugin_reservations_creneaux prc
					WHERE date >= date() OR repetition = 1
					ORDER BY date, heure;';
			}
			elseif ($criteria['field'] == 'perso') {
				$return['query'] = 'SELECT prp.*, prc.heure FROM plugin_reservations_personnes prp
					INNER JOIN plugin_reservations_creneaux prc ON prc.id = prp.creneau
					WHERE prp.id = ?;';
				$return['query_args'] = [(new Reservations)->getUserBookingId()];
			}
			else {
				throw new MiniSkel_Exception('Critère inconnu');
			}
		}

		$url 
		$return['loop_start'] = sprintf('\$this->variables[\'reservations_form_url\'] = %s;', var_export($url, true));

		return true;
	}
*/
}
