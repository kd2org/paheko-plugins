<?php

namespace Paheko\Plugin\HelloAsso\Entities;

use Paheko\DB;
use Paheko\Entity;
use Paheko\ValidationException;

use DateTime;

class Target extends Entity
{
	const TABLE = 'plugin_helloasso_targets';

	protected int $id;
	protected string $label;
	protected int $id_form;
	protected ?DateTime $last_sync;
	protected ?int $id_category;
	protected ?int $id_fee;
	protected ?int $id_year;

	public function sync() {
		$api = HelloAsso::getInstance()->api;

		$payments = $api->listPayments($this->org_slug, $this->form_type, $this->form_slug);
		$db = DB::getInstance();
		$already_sync = 0;

		foreach ($payments as $payment) {
			// Stop processing after seeing at least 3 payments already synced
			if ($already_sync >= 3) {
				return;
			}

			if ($db->test(self::SYNC_TABLE, 'payment_id = ?', $payment_id)) {
				$already_sync++;
				continue;
			}

			$payer = $payment->payer;
			$payer->email = strtolower($payer->email);

			$su_id = null;
			$user_id = $db->firstColum('SELECT id FROM membres WHERE email = ? AND id_category = ?;', $payer->email, $target->id_category);

			// Create user
			if (!$user_id) {
				$data = [];

				if ($target->firstName !== null && $target->firstName == $target->lastName) {
					if ($target->merge_names == 0) {
						$data[$target->firstName] = $payer->firstName . ' ' . $payer->lastName;
					}
					else {
						$data[$target->firstName] = $payer->lastName . ' ' . $payer->firstName;
					}

					$target->firstName = $target->lastName = null;
				}

				foreach (array_keys($api::PAYER_FIELDS) as $field) {
					if (null === $target->$field) {
						continue;
					}

					$data[$target->$field] = trim($payer->$field) ?: null;
				}

				$user_id = $membres->add($data);
			}

			// Create subscription/payment
			if ($target->id_fee) {
				$fee = Fees::get($target->id_fee);

				$data = [
					'date'       => (new \DateTime($payment->date))->format('d/m/Y'),
					'id_service' => $fee->id_service,
					'id_fee'     => $target->id_fee,
				];

				if ($target->id_account) {
					$data['create_payment'] = (bool) $target->id_account;
					$data['amount'] = Utils::money_format($payment->amount);
					$data['payment_reference'] = $payment->order->id;
					$data['notes'] = 'Importé automatiquement depuis HelloAsso';
					$data['account'] = [$target->id_account => ''];
				}

				$expected_amount = $fee->getAmountForUser($user_id);

				if ($data['amount'] >= $expected_amount) {
					$data['paid'] = true;
				}

				$su = Service_User::saveFromForm($user_id, $data);
				$su_id = $su->id();
			}

			// Save sync status
		}
	}
}