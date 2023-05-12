<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Config;
use Garradin\DB;
use Garradin\Entities\Plugin;
use KD2\DB\EntityManager;

use Garradin\Plugin\HelloAsso\Entities\Form;

use function Garradin\garradin_contributor_license;

class HelloAsso
{
	const NAME = 'helloasso';
	const PROVIDER_NAME = self::NAME;
	const PROVIDER_LABEL = 'HelloAsso';
	const PER_PAGE = 100;

	const MERGE_NAMES_FIRST_LAST = 0;
	const MERGE_NAMES_LAST_FIRST = 1;

	const MERGE_NAMES_OPTIONS = [
		self::MERGE_NAMES_FIRST_LAST => 'Prénom Nom',
		self::MERGE_NAMES_LAST_FIRST => 'Nom Prénom',
	];

	protected $plugin;
	protected $config;


	static protected $_instance;

	static public function getInstance()
	{
		if (null === self::$_instance) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	protected function __construct()
	{
		$this->plugin = EntityManager::getInstance(Plugin::class)->one('SELECT * FROM @TABLE WHERE name = ? LIMIT 1;', self::NAME);

		$this->config = $this->plugin->getConfig();
		if (null === $this->config) {
			$this->config = new \stdClass();
			$this->config->client_id = null;
		}
	}

	public function plugin(): Plugin
	{
		return $this->plugin;
	}

	public function getLastSync(): ?\DateTime
	{
		$date = $this->plugin->getConfig('last_sync') ?? null;

		if ($date) {
			$date = new \DateTime($date);
		}

		return $date;
	}

	public function sync(): bool
	{
		Forms::sync();
		$organizations = array_keys(Forms::listOrganizations());

		foreach ($organizations as $org_slug) {
			Orders::sync($org_slug);
			Payments::sync($org_slug);
			Items::sync($org_slug);
		}

		$this->plugin->setConfigProperty('last_sync', (new \DateTime)->format(\DATE_ISO8601));
		return $this->plugin->save();
	}

	public function getClientId(): ?string
	{
		return $this->config->client_id;
	}

	public function saveClient(string $client_id, string $client_secret): void
	{
		$client_id = trim($client_id);
		$old_client_id = $this->config->client_id;
		
		if ($client_id !== $old_client_id) {
			$this->reset();
		}

		$api = API::getInstance();
		$api->register($client_id, $client_secret);
		
		if ($client_id !== $old_client_id) {
			$this->sync();
		}
	}

	public function reset(): void
	{
		Forms::reset();
		Orders::reset();
		Payments::reset();
		Items::reset();
	}

	public function saveConfig(array $map, $merge_names, $match_email_field): bool
	{
		$this->plugin->setConfigProperty('merge_names', (int) $merge_names);
		$this->plugin->setConfigProperty('match_email_field', (bool) $match_email_field);
		$this->plugin->setConfigProperty('map_user_fields', $map);
		return $this->plugin->save();
	}

	public function isConfigured(): bool
	{
		return empty($this->config->oauth) ? false : true;
	}

	public function getConfig(): ?\stdClass {
		return $this->config;
	}

/*
	public function listTargets(): array
	{
		return EM::getInstance(Target::class, 'SELECT * FROM @TABLE ORDER BY label;');
	}


	public function findUserForPayment(\stdClass $payer)
	{
		$map = $this->config->map_user_fields;
		$where = '';
		$params = [];

		if ($this->config->match_email_field) {
			$where = sprintf('%s = ? COLLATE NOCASE', $map->email);
			$params[] = $payer->email;
		}
		else {
			// In case we merge first and last names
			if ($map->firstName == $map->lastName) {
				$where = sprintf('%s = ? COLLATE NOCASE', $map->firstName);

				if ($this->config->merge_names == self::MERGE_NAMES_FIRST_LAST) {
					$params[] = $payer->firstName . ' ' . $payer->lastName;
				}
				else {
					$params[] = $payer->lastName . ' ' . $payer->firstName;
				}
			}
			else {
				$where = sprintf('%s = ? AND %s = ?', $map->firstName, $map->lastName);
				$params[] = $payer->firstName;
				$params[] = $payer->lastName;
			}
		}

		$user_identity = Config::getInstance()->get('champ_identite');

		$sql = sprintf('SELECT id, %s AS identity FROM membres WHERE %s;', $user_identity, $where);

		return DB::getInstance()->first($sql, ...$params);
	}

	public function getMappedUser(\stdClass $payer): array
	{
		$out = [];
		$map = $this->config->map_user_fields;

		foreach ($map as $key => $target) {
			if (!$target) {
				continue;
			}

			if (!isset($payer->$key)) {
				continue;
			}

			$value = $payer->$key;

			if ($key == 'country') {
				$value = substr($value, 0, 2);
			}

			$out[$target] = $value;
		}

		if ($map->firstName && $map->firstName == $map->lastName) {
			if ($this->config->merge_names == self::MERGE_NAMES_FIRST_LAST) {
				$out[$map->firstName] = $payer->firstName . ' ' . $payer->lastName;
			}
			else {
				$out[$map->firstName] = $payer->lastName . ' ' . $payer->firstName;
			}
		}

		return $out;
	}
*/

	static public function cron() {
	}

	/**
	 * Toutes ces lignes de code ne se sont pas écrites toutes seules…
	 * Merci de contribuer à Garradin ;)
	 */
	const LEVEL_REQUIRED = 50;
	const PER_PAGE_TRIAL = 5;


	static public function getPageSize(): int
	{
		return self::isTrial() ? self::PER_PAGE_TRIAL : self::PER_PAGE;
	}

	/**
	 * Merci de contribuer à Garradin pour obtenir une licence :)
	 */
	static public function isTrial(): bool
	{
		$level = 100;//\Garradin\garradin_contributor_license();

		if ($level < self::LEVEL_REQUIRED) {
			return true;
		}
		else {
			return false;
		}
	}
}
