<?php

namespace Paheko\Plugin\HelloAsso;

use Paheko\Plugin\HelloAsso\Entities\Form;
use Paheko\Plugin\HelloAsso\Entities\Chargeable;
use Paheko\Plugin\HelloAsso\API;
use Paheko\Plugin\HelloAsso\Chargeables;

use Paheko\DB;

use KD2\DB\EntityManager as EM;

class Forms
{
	static protected ?array $forms_ids;
	static protected ?array $forms_names;

	static public function get(int $id): ?Form
	{
		return EM::findOneById(Form::class, $id);
	}

	static public function getId(string $org_slug, string $form_slug): ?int
	{
		if (!isset(self::$forms_ids)) {
			self::$forms_ids = DB::getInstance()->getAssoc(sprintf('SELECT org_slug || \'__\' || slug, id FROM %s;', Form::TABLE));
		}

		$slug = sprintf('%s__%s', $org_slug, $form_slug);
		return self::$forms_ids[$slug] ?? null;
	}

	static public function getLabel(int $id): ?string
	{
		if (!isset(self::$forms_names)) {
			self::$forms_names = DB::getInstance()->getAssoc(sprintf('SELECT id, label FROM %s;', Form::TABLE));
		}

		return self::$forms_names[$id] ?? null;
	}

	static public function getNeedingConfig(): array
	{
		return EM::getInstance(Form::class)->all('SELECT * FROM @TABLE WHERE need_config = 1;');
	}

	// ToDo: add some cache
	static public function getIdForCheckout(): int
	{
		return (int)DB::getInstance()->firstColumn(sprintf('SELECT id FROM %s WHERE slug = :checkout_slug', Form::TABLE), Form::CHECKOUT_SLUG);
	}

	static public function list(): array
	{
		$sql = sprintf('SELECT * FROM %s ORDER BY state = \'Disabled\', type, org_name COLLATE NOCASE, label COLLATE NOCASE;', Form::TABLE);
		$list = DB::getInstance()->get($sql);

		foreach ($list as &$row) {
			$row->state_label = Form::STATES[$row->state] ?? '';
			$row->type_label = Form::TYPES[$row->type] ?? '';
		}

		return $list;
	}

	static public function listOrganizations(): array
	{
		return DB::getInstance()->getAssoc(sprintf('SELECT org_slug, org_name FROM %s GROUP BY org_slug ORDER BY org_slug;', Form::TABLE));
	}

	/**
	 * Synchronizes remote list of forms with local DB
	 * @return void
	 */
	static public function sync(): void
	{
		$organizations = API::getInstance()->listOrganizations();

		foreach ($organizations as $o) {
			$existing = [];
			$list = EM::getInstance(Form::class)->all('SELECT * FROM @TABLE WHERE org_slug = ?;', $o->organizationSlug);

			foreach ($list as $form) {
				$existing[$form->slug] = $form;
			}

			unset($list);

			$forms = API::getInstance()->listForms($o->organizationSlug);

			foreach ($forms as $form) {
				$entity = $existing[$form->formSlug] ?? new Form;
				$entity->set('org_name', $o->name);
				$entity->set('org_slug', $o->organizationSlug);

				$entity->set('label', strip_tags($form->privateTitle ?? $form->title));
				$entity->set('type', $form->formType);
				$entity->set('state', $form->state);
				$entity->set('slug', $form->formSlug);
				$entity->set('need_config', 0);

				$entity->save();

				if ($form->formType === 'Donation' || $form->formType === 'PaymentForm')
				{
					$chargeable = Chargeables::get($entity->id, Chargeable::TARGET_TYPE_FROM_CLASS[get_class($entity)], Chargeable::ONLY_ONE_ITEM_FORM_TYPE, $entity->label, null);
					if (null === $chargeable) {
						$chargeable = Chargeables::createChargeable($entity->id, $entity, Chargeable::ONLY_ONE_ITEM_FORM_TYPE);
					}
				}
			}
		}
	}
	
	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s;', Form::TABLE);
		DB::getInstance()->exec($sql);
	}
}
