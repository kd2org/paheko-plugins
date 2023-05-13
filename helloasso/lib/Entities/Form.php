<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\DB;
use Garradin\Entity;
use Garradin\ValidationException;

use DateTime;

class Form extends Entity
{
	const TABLE = 'plugin_helloasso_forms';

	protected int $id;
	protected string $org_slug;
	protected string $org_name;
	protected string $name;
	protected string $state;

	protected string $type;
	protected string $slug;
	
	protected ?int $id_credit_account;
	protected ?int $id_debit_account;

	const TYPES = [
		'CrowdFunding' => 'Crowdfunding',
		'Membership'   => 'Adhésion',
		'Event'        => 'Billetteries',
		'Donation'     => 'Dons',
		'PaymentForm'  => 'Ventes',
		'Checkout'     => 'Encaissement',
		'Shop'         => 'Boutique',
	];

	const STATES = [
		'Draft'    => 'brouillon',
		'Public'   => 'public',
		'Private'  => 'privé',
		'Disabled' => 'désactivé',
	];
}
