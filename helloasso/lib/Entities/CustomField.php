<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\Entity;

class CustomField extends Entity
{
	protected int		$id;
	protected int		$id_chargeable;
	protected ?int		$id_dynamic_field;
	protected string	$name;

	const TABLE = 'plugin_helloasso_chargeable_custom_fields';
}
