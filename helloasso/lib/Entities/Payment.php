<?php

namespace Garradin\Plugin\HelloAsso\Entities;

use Garradin\DB;
use Garradin\Entity;
use Garradin\ValidationException;

use DateTime;

class Payment extends Entity
{
	const TABLE = 'plugin_helloasso_payments';

	protected int $id;
	protected int $id_order;
	protected int $id_form;
	protected ?int $id_user;
	protected ?int $id_transaction;
	protected int $amount;
	protected string $state;
	protected ?\DateTime $transfer_date;
	protected string $person;
	protected \DateTime $date;
	protected ?string $receipt_url;
	protected string $raw_data;

	const STATES = [
		'Pending'               => 'À venir',
		'Authorized'            => 'Autorisé',
		'Refused'               => 'Refusé',
		'Unknown'               => 'Inconnu',
		'Registered'            => 'Enregistré',
		'Error'                 => 'Erreur',
		'Refunded'              => 'Remboursé',
		'Abandoned'             => 'Abandonné',
		'Refunding'             => 'En remboursement',
		'Canceled'              => 'Annulé',
		'Contested'             => 'Contesté',
		'WaitingBankValidation' => 'Attente de validation de la banque',
		'WaitingBankWithdraw'   => 'Attente retrait de la banque',
	];

	const STATE_OK = 'Authorized';

	const CASH_OUT_OK = 'CashedOut';

	const PAYER_FIELDS = [
		'firstName'   => 'Prénom',
		'lastName'    => 'Nom',
		'company'     => 'Organisme',
		'email'       => 'Adresse E-Mail',
		'address'     => 'Adresse postale',
		'zipCode'     => 'Code postal',
		'city'        => 'Ville',
		'country'     => 'Pays',
		'dateOfBirth' => 'Date de naissance',
	];

	static public function getPersonName(\stdClass $person)
	{
		$names = [!empty($person->company) ? $person->company . ' — ' : null, $person->firstName ?? null, $person->lastName ?? null];
		$names = array_filter($names);

		$names = implode(' ', $names);

		if (!empty($person->city)) {
			$names .= sprintf(' (%s)', $person->city);
		}

		return $names;
	}

	static public function formatPersonInfos(\stdClass $person): array
	{
		$data = [];

		foreach (self::PAYER_FIELDS as $key => $name) {
			if (!isset($person->$key)) {
				continue;
			}

			$value = $person->$key;

			if ($key == 'dateOfBirth') {
				$value = new \DateTime($value);
			}

			$data[$name] = $value;
		}

		return $data;
	}

	public function selfCheck(): void
	{
		parent::selfCheck();
		if (!array_key_exists($this->state, self::STATES)) {
			throw new \UnexpectedValueException(sprintf('Wrong payment (ID: #%d) status: %s. Possible values are: %s.', $this->id ?? null, $this->state, implode(', ', array_keys(self::STATES))));
		}
	}
}
