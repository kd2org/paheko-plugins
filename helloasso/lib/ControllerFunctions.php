<?php

namespace Paheko\Plugin\HelloAsso;

use KD2\DB\EntityManager as EM;
use Paheko\DB;
use Paheko\Config;

use Paheko\Users\Session;
use Paheko\Users\DynamicFields;
use Paheko\Entities\Users\DynamicField;
use Paheko\Entities\Users\Category;
use Paheko\Plugin\HelloAsso\Entities\Chargeable;
use Paheko\Plugin\HelloAsso\Entities\CustomField;
use Paheko\Plugin\HelloAsso\Entities\Form;

class ControllerFunctions
{
	static public function setDynamicFieldOptions(): array
	{
		$dynamic_fields = [ 'null' => '-- Ne pas importer' ];
		$fields = DynamicFields::getInstance()->all();
		foreach ($fields as $config) {
			if (!isset($config->label)) {
				continue;
			}
			$dynamic_fields[$config->id] = $config->label;
		}
		return $dynamic_fields;
	}

	static public function updateCustomFields(int $id_form, array $source): void
	{
		if (!$form = EM::findOneById(Form::class, (int)$id_form)) {
			throw new \InvalidArgumentException(sprintf('Unable to update custom field of inexisting Form #%d.', $id_form));
		}
		foreach ($source as $id_custom_field => $value) {
			if (!$customField = EM::findOneById(CustomField::class, (int)$id_custom_field)) {
				throw new \InvalidArgumentException(sprintf('Inexisting CustomField #%s.', $id_custom_field));
			}

			if ($value !== 'null') {
				if (!DB::getInstance()->test(DynamicField::TABLE, 'id = ?', (int)$value)) {
					throw new \RuntimeException(sprintf('Inexisting DynamicField #%s.', $value));
				}
				$customField->set('id_dynamic_field', (int)$value);
			}
			else {
				$customField->set('id_dynamic_field', null);
			}
			$customField->save();
		}
		$form->set('need_config', 0);
		$form->save();
	}

	static public function updateChargeable(Chargeable $chargeable, int $id_category, int $id_fee): void
	{
		$chargeable->set('id_category', $id_category === 0 ? null : (int)$id_category);
		$chargeable->set('id_fee', $id_fee === 0 ? null : (int)$id_fee);
		$chargeable->set('need_config', 0);
		$chargeable->save();
	}

	static public function setCategoryOptions(): array
	{
		$categories = EM::getInstance(Category::class)->all('SELECT * FROM @TABLE WHERE perm_config != :admin AND id != :providers_category', (int)Session::ACCESS_ADMIN, (int)Config::getInstance()->providers_category);
		$category_options = [ 0 => 'Ne pas inscrire la personne' ];
		foreach ($categories as $category) {
			$category_options[(int)$category->id] = $category->name;
		}
		return $category_options;
	}
}
