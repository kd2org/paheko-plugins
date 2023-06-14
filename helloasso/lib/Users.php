<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Entities\Users\User;
use Garradin\Entities\Users\Category;
use Garradin\Plugin\HelloAsso\HelloAsso;
use KD2\DB\EntityManager;
use Garradin\Users\DynamicFields;

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

	static protected ?array	$_userMatchField = null;
	static protected array	$_existingUsersCache = [];

	static public function getMappedUser(\stdClass $payer, bool $check = true): User
	{
		$user = new User();

		$ha = HelloAsso::getInstance();
		$map = clone $ha->plugin()->getConfig()->payer_map;

		$id_category = $ha->plugin()->getConfig()->id_category;
		if (!$id_category OR !($category = EntityManager::findOneById(Category::class, (int)$id_category))) {
			throw new NotFoundException(sprintf('Cannot map user: Not found category "%s".', $id_category));
		}
		$user->set('id_category', (int)$category->id);
		$user->set('nom', self::guessUserName($payer));
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
		return EntityManager::findOneById(User::class, $id_user);
	}

	static public function guessUserIdentifier(\stdClass $source): ?string
	{
		if (self::getUserMatchField()[1] === 'name') {
			return self::guessUserName($source);
		}
		if (isset($source->email))
			return $source->email;

		return $source->{self::getUserMatchField()[2]} ?? null;
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
		if (array_key_exists($identifier, self::$_existingUsersCache)) {
			return self::$_existingUsersCache[$identifier];
		}
		$id_user = EntityManager::getInstance(User::class)->col(sprintf('SELECT id FROM @TABLE WHERE %s = ?;', self::getUserMatchField()[0]), $identifier);
		self::$_existingUsersCache[$identifier] = (false === $id_user) ? null : $id_user;

		return self::$_existingUsersCache[$identifier];
	}

	static public function addUserToCache(string $identifier, int $id_user): void
	{
		self::$_existingUsersCache[$identifier] = $id_user;
	}

	static public function getUserMatchField(): array
	{
		$ha = HelloAsso::getInstance();
		if (null === self::$_userMatchField) {
			if ($ha->plugin()->getConfig()->user_match_type === self::USER_MATCH_NAME) {
				self::$_userMatchField = [ DynamicFields::getFirstNameField(), 'name', null ];
			}
			else {
				self::$_userMatchField = [ DynamicFields::getFirstEmailField(), 'email', $ha->plugin()->getConfig()->user_match_field ];
			}
		}
		return self::$_userMatchField;
	}
}
