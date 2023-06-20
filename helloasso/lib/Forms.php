<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\API;
use Garradin\Plugin\HelloAsso\Chargeables;

use Garradin\DB;

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

	static public function getName(int $id): ?string
	{
		if (!isset(self::$forms_names)) {
			self::$forms_names = DB::getInstance()->getAssoc(sprintf('SELECT id, name FROM %s;', Form::TABLE));
		}

		return self::$forms_names[$id] ?? null;
	}

	static public function list(): array
	{
		$sql = sprintf('SELECT * FROM %s ORDER BY state = \'Disabled\', type, org_name COLLATE NOCASE, name COLLATE NOCASE;', Form::TABLE);
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
				$entity->org_name = $o->name;
				$entity->org_slug = $o->organizationSlug;

				$entity->name = strip_tags($form->privateTitle ?? $form->title);
				$entity->type = $form->formType;
				$entity->state = $form->state;
				$entity->slug = $form->formSlug;

				$entity->save();

				if ($form->formType === 'Donation' || $form->formType === 'PaymentForm')
				{
					$chargeable = Chargeables::get($entity->id, Chargeable::TARGET_TYPE_FROM_CLASS[get_class($entity)], Chargeable::ONLY_ONE_ITEM_FORM_TYPE, $entity->name, null);
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
