<?php

namespace Paheko\Plugin\HelloAsso;

use Paheko\Entities\Users\User;
use Paheko\Entities\Users\Category;
use Paheko\Plugin\HelloAsso\HelloAsso;
use Paheko\Plugin\HelloAsso\Entities\CustomField;
use Paheko\Plugin\HelloAsso\Entities\Form;
use Paheko\Plugin\HelloAsso\Entities\Order;
use Paheko\Plugin\HelloAsso\Entities\Chargeable;

use KD2\DB\EntityManager as EM;
use Paheko\DB;
use Paheko\Users\DynamicFields;
use Paheko\Entities\Users\DynamicField;
use Paheko\Services\Services_User;
use Paheko\Config;

class Users
{
	const MERGE_NAMES_FIRST_LAST = 0;
	const MERGE_NAMES_LAST_FIRST = 1;

	const MERGE_NAMES_OPTIONS = [
		self::MERGE_NAMES_LAST_FIRST => 'Nom Prénom',
		self::MERGE_NAMES_FIRST_LAST => 'Prénom Nom'
	];

	const USER_MATCH_NAME = 0;
	const USER_MATCH_EMAIL = 1;
	const USER_MATCH_TYPES = [ self::USER_MATCH_NAME => 'Nom et prénom', self::USER_MATCH_EMAIL => 'Courriel' ];
	const DUPLICATE_MEMBER_PREFIX = 'Doublon-%s-';

	static protected ?array		$_user_match_field = null;
	static protected array		$_existing_users_cache = [];
	static protected string		$_name_field;
	static protected ?string	$_custom_field_login = null;
	static protected array		$_payer_fields_map;
	static protected array		$_custom_fields_cache = [];
	static protected array		$_dynamic_field_name_cache = [];
	static protected array		$_forms_cache = [];

	static public function getMappedUser(\stdClass $payer, bool $check = true): User
	{
		$user = new User();

		$ha = HelloAsso::getInstance();
		$map = clone $ha->plugin()->getConfig()->payer_map;

		$id_category = (int)Config::getInstance()->default_category;
		if (!$id_category || !DB::getInstance()->test(Category::TABLE, 'id = ?', (int)$id_category)) {
			throw new NotFoundException(sprintf('Cannot map user: Not found category "%s".', $id_category));
		}
		$user->set('id_category', $id_category);
		$user->set('nom', self::guessUserName($payer)); // ToDo: use DynamicField instead
		unset($map->name);

		foreach ($map as $api_field => $user_field) {
			if (!$user_field) {
				continue;
			}

			if (!isset($payer->$api_field)) {
				continue;
			}

			$value = $payer->$api_field;

			if ($api_field == 'country') {
				$value = substr($value, 0, 2);
			}

			$user->set($user_field, $value);
		}

		// Force to null critical fields
		$user->set('otp_secret', null);
		$user->set('pgp_key', null);
		$user->set('password', null);
		$user->set('id_parent', null);

		$user->setNumberIfEmpty();
		if ($check) {
			$user->selfCheck();
		}

		return $user;
	}

	static public function findUserMatchingPayer(\stdClass $payer): ?User
	{
		if (!($identifier = self::guessUserIdentifier($payer)) || !($id_user = self::getUserId($identifier))) {
			return null;
		}
		return EM::findOneById(User::class, $id_user);
	}

	static public function guessUserIdentifier(\stdClass $source): ?string
	{
		if (self::getUserMatchField()['type'] === self::USER_MATCH_NAME) {
			return self::guessUserName($source);
		}

		if (isset($source->email)) {
			return $source->email;
		}

		// 'api_field' may be null if the identifier has the same name as a custom_field
		$field = self::getUserMatchField()['api_field'] ?? self::$_custom_field_login;

		return $source->$field ?? ($source->fields[$field] ?? null);
	}

	static public function guessUserName(\stdClass $source): string
	{
		if (HelloAsso::getInstance()->plugin()->getConfig()->payer_map->name === self::MERGE_NAMES_FIRST_LAST) {
			return $source->firstName . ' ' . $source->lastName;
		}
		return $source->lastName . ' ' . $source->firstName;
	}

	static public function userAlreadyExists(string $identifier): bool
	{
		return (bool)self::getUserId($identifier);
	}

	static public function getUserId(string $identifier): ?int
	{
		if (array_key_exists($identifier, self::$_existing_users_cache)) {
			return self::$_existing_users_cache[$identifier];
		}
		$id_user = EM::getInstance(User::class)->col(sprintf('SELECT id FROM @TABLE WHERE %s = ?;', DB::getInstance()->quoteIdentifier(self::getUserMatchField()['entity_field'])), $identifier);
		self::$_existing_users_cache[$identifier] = (false === $id_user) ? null : $id_user;

		return self::$_existing_users_cache[$identifier];
	}

	/**
	 * @return int|bool|null
	 * true: user already exists (no need to register him/her)
	 * int: newly registered user's ID
	 * null: conflict happened
	 */
	static public function syncRegistration(\stdClass $data, int $id_form, ChargeableInterface $entity, int $chargeable_type)
	{
		self::addNewCustomFields($id_form, $data);

		$chargeable = Chargeables::getFromEntity($id_form, $entity, $chargeable_type);
		if (!$chargeable->id_category) { // Meaning this chargeable does not register members
			return null;
		}

		if (!$identifier = Users::guessUserIdentifier($data->beneficiary)) {
			throw new NoFuturIDSyncException(sprintf(
				'Commande n°%s : Impossible d\'inscrire le membre "%s". Aucun %s à lui associer comme identifiant.' . "\n" . 'Informations reçues de %s :' . "\n" . '%s',
				$data->order_id,
				$data->beneficiary_label,
				Users::USER_MATCH_TYPES[HelloAsso::getInstance()->plugin()->getConfig()->user_match_type],
				HelloAsso::PROVIDER_LABEL,
				json_encode($data->beneficiary, JSON_UNESCAPED_UNICODE)
			));
		}

		$date = new \DateTime($data->order->date);
		if ($id_user = Users::getUserId($identifier)) {
			self::handleFeeRegistration($chargeable, $id_user, $date);
			return true;
		}

		$source = self::createUserSource($data, $id_form, $date);

		if (!$source['_conflict'])
		{
			$user = \Paheko\Users\Users::create();
			$user->importForm($source);
			$user->set('id_category', (int)$chargeable->id_category);
			$user->setNumberIfEmpty();
			$user->save();

			self::addUserToCache(Users::guessUserIdentifier($data->beneficiary), (int)$user->id);
			self::bindUserToWholeProcess((int)$user->id, $entity, $data);
			self::handleFeeRegistration($chargeable, (int)$user->id, $date);

			return (int)$user->id;
		}
		return null;
	}

	static protected function createUserSource(\stdClass $data, int $id_form, \DateTime $date): array
	{
		$source = [
			'id_parent' => null,
			self::$_name_field => Users::guessUserName($data->beneficiary),
			'date_inscription' => $date,
			'_conflict' => false
		];

		foreach (self::$_payer_fields_map as $user_field => $api_field) {
			if (isset($data->beneficiary->$api_field))
				$source[$user_field] = $data->beneficiary->$api_field;
		}
		if (array_key_exists($id_form, self::$_custom_fields_cache)) {
			foreach (self::$_custom_fields_cache[$id_form] as $custom_field) {
				// The custom field may not exist for this particular item (e.g., the custom field has been added a long time after the order been processed)
				if ($custom_field->id_dynamic_field && isset($data->fields[$custom_field->name])) {
					if (!array_key_exists((int)$custom_field->id_dynamic_field, self::$_dynamic_field_name_cache)) {
						throw new SyncException(sprintf('Inexisting DynamicField #%s.', $custom_field->id_dynamic_field));
					}
					$name = self::$_dynamic_field_name_cache[$custom_field->id_dynamic_field];
					$source[$name] = $data->fields[$custom_field->name];
				}
			}
		}
		$user_match_field = self::getUserMatchField();

		// The user match field may not exist (aka. not filled during the HelloAsso checkout) when the payer is also the beneficiary
		if (isset($data->beneficiary->{$user_match_field['api_field']})) {
			$source[$user_match_field['entity_field']] = $data->beneficiary->{$user_match_field['api_field']};
		}

		$login_field = DynamicFields::getLoginField();
		if (array_key_exists($login_field, $source) && $user_match_field['entity_field'] !== $login_field)
		{
			$id_user = EM::getInstance(User::class)->col(sprintf('SELECT id FROM @TABLE WHERE %s = ? COLLATE NOCASE;', DB::getInstance()->quoteIdentifier($login_field)), $source[$login_field]);
			if ($id_user) {
				$source['_conflict'] = true;
				$source[$login_field] = sprintf(self::DUPLICATE_MEMBER_PREFIX . $source[$login_field], uniqid());
				//unset($source[$login_field]);
			}
		}

		return $source;
	}

	static protected function bindUserToWholeProcess(int $id_user, ChargeableInterface $entity, \stdClass $data): void
	{
		$entity->setUserId($id_user);
		$entity->save();

		if (!$order = EM::findOneById(Order::class, (int)$data->order_id)) {
			throw new \RuntimeException(sprintf('Order #%d not found while trying to associate its user #%d from entity #%d.', $data->order_id, $id_user, $entity->id));
		}
		$order->set('id_payer', (int)$id_user);
		$order->save();

		if (!$payment = Payments::getByOrderId((int)$order->id)) {
			throw new \RuntimeException(sprintf('No payment found for order #%d while trying to associate its payer User.', $order->id));
		}
		$payment->set('id_payer', (int)$id_user);
		$payment->save();
	}

	static protected function addNewCustomFields(int $id_form, \stdClass $data): void
	{
		if (!array_key_exists($id_form, self::$_forms_cache)) {
			throw new SyncException(sprintf('Tried to add custom fields to an inexisting (never synchronized?) form #%d.', $id_form));
		}
		$form = self::$_forms_cache[$id_form];
		$existings = CustomFields::getNamesForForm((int)$form->id);
		foreach ($data->fields as $name => $value) {
			if (!in_array($name, $existings)) {
				$form->createCustomField($name);
				$form->set('need_config', 1);
				$form->save();
			}
		}
	}

	static protected function handleFeeRegistration(Chargeable $chargeable, int $id_user, \DateTime $date)
	{
		if (null === $chargeable->id_fee) {
			return null;
		}
		if (Services_User::exists($id_user, null, $chargeable->id_fee)) {
			return true;
		}
		try {
			$su = $chargeable->registerToService($id_user, $date, true);
		}
		catch (\Exception $e) {
			throw new SyncException(sprintf('User service registration failed. Chargeable ID: #%d, user ID: #%d, service ID: #%d, fee ID: #%d.', $chargeable->id, $id_user, $chargeable->service()->id, $chargeable->id_fee), 0, $e);
		}
		return $su;
	}

	static public function initSync(): void
	{
		self::$_name_field = DynamicFields::getFirstNameField();
		self::setUserFieldsMap();
		self::setCustomFieldsCache();
		self::setFormsCache();
		self::$_dynamic_field_name_cache = DB::getInstance()->getAssoc(sprintf('SELECT id, name FROM %s', DynamicField::TABLE));
	}

	static protected function setUserFieldsMap(): void
	{
		$map = clone HelloAsso::getInstance()->plugin()->getConfig()->payer_map;
		unset($map->name); // name has a specific process
		foreach ($map as $api_field => $payer_field) {
			if (null === $payer_field) {
				unset($map->$api_field);
			}
		}
		self::$_payer_fields_map = array_flip((array)$map);
	}

	static protected function setCustomFieldsCache(): void
	{
		$login_id = (int)DynamicFields::getInstance()->fieldByKey(DynamicFields::getLoginField())->id;
		foreach (DB::getInstance()->iterate(sprintf('SELECT * FROM %s ORDER BY id_form', CustomField::TABLE)) as $row) {
			$custom_field = new CustomField();
			$custom_field->load((array)$row);
			self::$_custom_fields_cache[(int)$row->id_form][] = $custom_field;
			if ($custom_field->id_dynamic_field === $login_id) {
				self::$_custom_field_login = $custom_field->name;
			}
		}
	}

	static protected function setFormsCache(): void
	{
		foreach (EM::getInstance(Form::class)->all('SELECT id, * FROM @TABLE;') as $form) {
			self::$_forms_cache[(int)$form->id] = $form;
		}
	}

	static public function addUserToCache(string $identifier, int $id_user): void
	{
		self::$_existing_users_cache[$identifier] = $id_user;
	}

	static public function getUserMatchField(): array
	{
		$ha = HelloAsso::getInstance();
		if (null === self::$_user_match_field) {
			if ($ha->plugin()->getConfig()->user_match_type === self::USER_MATCH_NAME) {
				self::$_user_match_field = [
					'type' => self::USER_MATCH_NAME,
					'entity_field' => DynamicFields::getFirstNameField(),
					'api_field' => null
				];
			}
			else {
				self::$_user_match_field = [
					'type' => self::USER_MATCH_EMAIL,
					'entity_field' => DynamicFields::getFirstEmailField(),
					'api_field' => $ha->plugin()->getConfig()->user_match_field ?? null
				];
			}
		}
		return self::$_user_match_field;
	}
}
