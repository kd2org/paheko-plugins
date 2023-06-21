<?php

namespace Garradin\Plugin\HelloAsso;

use KD2\DB\EntityManager as EM;

use Garradin\Users\DynamicFields;
use Garradin\Entities\Users\DynamicField;
use Garradin\Entities\Users\Category;
use Garradin\Plugin\HelloAsso\Entities\Chargeable;
use Garradin\Plugin\HelloAsso\Entities\CustomField;
use Garradin\Plugin\HelloAsso\Entities\Form;

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
				if (!EM::findOneById(DynamicField::class, (int)$value)) {
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
		// ToDo: remove admin categories
		$categories = EM::getInstance(Category::class)->all('SELECT * FROM @TABLE');
		$category_options = [ 0 => 'Ne pas inscrire la personne' ];
		foreach ($categories as $category) {
			$category_options[(int)$category->id] = $category->name;
		}
		return $category_options;
	}
}
