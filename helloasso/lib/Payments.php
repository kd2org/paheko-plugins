<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities\Payment;
use Garradin\Plugin\HelloAsso\API;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;

use KD2\DB\EntityManager as EM;

class Payments
{
	static public function get(int $id): ?Payment
	{
		return EM::findOneById(Payment::class, $id);
	}

	static public function list($for): DynamicList
	{
		$columns = [
			'id' => [
				'label' => 'Référence',
			],
			'id_transaction' => [
				'label' => 'Écriture',
			],
			'date' => [
				'label' => 'Date',
			],
			'amount' => [
				'label' => 'Montant',
			],
			'person' => [
				'label' => 'Personne',
			],
			'state' => [
				'label' => 'Statut',
			],
			'transfer_date' => [
				'label' => 'Versement',
			],
			'receipt_url' => [],
			'id_order' => [],
		];

		$tables = Payment::TABLE;
		$list = new DynamicList($columns, $tables);

		if ($for instanceof Form) {
			$conditions = sprintf('id_form = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('%s - Paiements', $for->name));
		}
		elseif ($for instanceof Order) {
			$conditions = sprintf('id_order = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('Commande - %d - Paiements', $for->id));
		}
		else {
			throw new \RuntimeException('Invalid target');
		}

		$list->setModifier(function ($row) {
			$row->state = Payment::STATES[$row->state] ?? 'Inconnu';
		});

		$list->setExportCallback(function (&$row) {
			$row->amount = $row->amount ? Utils::money_format($row->amount, '.', '', false) : null;
		});

		$list->orderBy('date', true);
		return $list;
	}

	static public function getLastPaymentDate(): ?\DateTime
	{
		$date = DB::getInstance()->firstColumn(sprintf('SELECT MAX(date) FROM %s WHERE state = ?;', Payment::TABLE), Payment::STATE_OK);

		if ($date) {
			$date = \DateTime::createFromFormat('!Y-m-d H:i:s', $date);
		}

		return $date;
	}

	static public function sync(string $org_slug): void
	{
		$last_payment = self::getLastPaymentDate();

		$params = [
			'pageSize'  => HelloAsso::getPageSize(),
			// Only return new Authorized payments, we are no expecting
			'states'    => Payment::STATE_OK,
		];

		$page_count = 1;

		if ($last_payment) {
			$last_payment->modify('-7 days');
			//$params['from'] = $last_payment->format('Y-m-d');
		}

		for ($i = 1; $i <= $page_count; $i++) {
			$params['pageIndex'] = $i;
			$result = API::getInstance()->listOrganizationPayments($org_slug, $params);
			$page_count = $result->pagination->totalPages;

			foreach ($result->data as $payment) {
				// This API endpoint does not return Pending payments
				self::syncPayment($payment);
			}

			if (HelloAsso::isTrial()) {
				break;
			}
		}
	}

	static protected function syncPayment(\stdClass $data): void
	{
		$entity = self::get($data->id) ?? new Payment;

		$entity->set('raw_data', json_encode($data));

		$data = self::transform($data);

		if (!$entity->exists()) {
			$entity->set('id', $data->id);
			$entity->set('id_order', $data->order_id);
			$entity->set('id_form', Forms::getId($data->org_slug, $data->form_slug));
		}

		$entity->set('amount', $data->amount);
		$entity->set('state', $data->state);
		$entity->set('date', $data->date);
		$entity->set('transfer_date', $data->transfer_date);
		$entity->set('person', $data->payer_name);
		$entity->set('receipt_url', $data->paymentReceiptUrl ?? null);

		$entity->save();
	}

	static public function transform(\stdClass $data): \stdClass
	{
		$data->id = (int) $data->id;
		$data->order_id = (int) $data->order->id ?: null;
		$data->date = new \DateTime($data->date);
		$data->status = Payment::STATES[$data->state] ?? '--';
		$data->transferred = isset($data->cashOutState) && $data->cashOutState == Payment::CASH_OUT_OK ? true : false;
		$data->transfer_date = isset($data->cashOutDate) ? new \DateTime($data->cashOutDate) : null;
		$data->payer_name = isset($data->payer) ? Payment::getPayerName($data->payer) : null;
		$data->payer_infos = isset($data->payer) ? Payment::getPayerInfos($data->payer) : null;
		$data->form_slug = $data->order->formSlug;
		$data->org_slug = $data->order->organizationSlug;

		return $data;
	}
	
	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s;', Payment::TABLE);
		DB::getInstance()->exec($sql);
	}

/*


	public function getPayment(string $id): \stdClass
	{
		$data = $this->api->getPayment($id);
		return $this->transformPayment($data);
	}
	public function listPayments(\stdClass $form, int $page = 1, &$count = null): array
	{
		$per_page = self::PER_PAGE;

		if ($this->isTrial()) {
			$per_page = self::PER_PAGE_TRIAL;
			$page = 1;
		}

		$result = $this->api->listFormPayments($form->org_slug, $form->form_type, $form->form_slug, $page, $per_page);

		$count = $result->pagination->totalCount;

		foreach ($result->data as &$row) {
			$row = $this->transformPayment($row);
		}

		unset($row);

		return $result->data;
	}

	public function listOrganizationPayments(string $org_slug, int $page = 1, &$count = null): array
	{
		$per_page = self::PER_PAGE;

		if ($this->isTrial()) {
			$per_page = self::PER_PAGE_TRIAL;
			$page = 1;
		}

		$result = $this->api->listOrganizationPayments($org_slug, $page, $per_page);

		$count = $result->pagination->totalCount;

		foreach ($result->data as &$row) {
			$row = $this->transformPayment($row);
		}

		unset($row);

		return $result->data;
	}
*/
}