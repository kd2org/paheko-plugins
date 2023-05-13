<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Plugin\HelloAsso\Entities\Form;
use Garradin\Plugin\HelloAsso\Entities\Order;
use Garradin\Plugin\HelloAsso\Entities as HA;
use Garradin\Plugin\HelloAsso\API;

use Garradin\Payments\Payments as Paheko_Payments;
use Garradin\Entities\Payments\Payment;
use Garradin\Entities\Users\User;

use Garradin\DB;
use Garradin\DynamicList;
use Garradin\Utils;

use KD2\DB\EntityManager as EM;

class Payments extends Paheko_Payments
{
	const PAHEKO_STATUS = [ HA\Payment::STATE_OK => Payment::VALIDATED_STATUS ]; // ToDo: complete the list
	const UPDATE_MESSAGE = 'Mise à jour du paiement';
	const TRANSACTION_NOTE = 'Générée automatiquement par l\'extension ' . HelloAsso::PROVIDER_LABEL . '.';

	static public function get(int $id): ?Payment
	{
		return EM::findOne(Payment::class, 'SELECT * FROM @TABLE WHERE provider = :provider AND json_extract(extra_data, \'$.id\') = :id', HelloAsso::PROVIDER_NAME, $id);
	}

	static public function list($for): DynamicList
	{
		$columns = [
			'id' => [],
			'reference' => [
				'label' => 'Référence',
			],
			'id_transaction' => [
				'label' => 'Écriture'
			],
			'label' => [
				'label' => 'Libellé'
			],
			'date' => [
				'label' => 'Date',
			],
			'amount' => [
				'label' => 'Montant',
			],
			'id_author' => [
				'label' => 'Personne'
			],
			'author_name' => [],
			'state' => [
				'label' => 'Statut',
				'select' => 'json_extract(extra_data, \'$.state\')'
			],
			'transfer_date' => [
				'label' => 'Versement',
				'select' => 'json_extract(extra_data, \'$.transfert_date\')'
			],
			'receipt_url' => [
				'select' => 'json_extract(extra_data, \'$.receipt_url\')'
			],
			'id_order' => [
				'label' => 'Commande',
				'select' => 'json_extract(extra_data, \'$.id_order\')'
			]
		];

		$tables = Payment::TABLE;
		$list = new DynamicList($columns, $tables);

		if ($for instanceof Form) {
			$conditions = sprintf('json_extract(extra_data, \'$.id_form\') = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('%s - Paiements', $for->name));
		}
		elseif ($for instanceof Order) {
			$conditions = sprintf('json_extract(extra_data, \'$.id_order\') = %d', $for->id);
			$list->setConditions($conditions);
			$list->setTitle(sprintf('Commande - %d - Paiements', $for->id));
		}
		else {
			throw new \RuntimeException('Invalid target');
		}

		$list->setModifier(function ($row) {
			$row->state = HA\Payment::STATES[$row->state] ?? 'Inconnu';
			if ($row->id_author) {
				$row->author = EM::findOneById(User::class, (int)$row->id_author);
			}
		});

		$list->setExportCallback(function (&$row) {
			$row->amount = $row->amount ? Utils::money_format($row->amount, '.', '', false) : null;
		});

		$list->orderBy('date', true);
		return $list;
	}

	static public function getLastPaymentDate(): ?\DateTime
	{
		$date = DB::getInstance()->firstColumn(sprintf('SELECT MAX(date) FROM %s WHERE provider = :plugin_provider AND status = :validated_status;', Payment::TABLE), HelloAsso::PROVIDER_NAME, Payment::VALIDATED_STATUS);

		if ($date) {
			$date = \DateTime::createFromFormat('!Y-m-d H:i:s', $date);
		}

		return $date;
	}

	static public function sync(string $org_slug, bool $accounting = true): void
	{
		$last_payment = self::getLastPaymentDate();

		$params = [
			'pageSize'  => HelloAsso::getPageSize(),
			// Only return new Authorized payments, we are no expecting
			'states'    => HA\Payment::STATE_OK,
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
				self::syncPayment($payment, $accounting);
			}

			if (HelloAsso::isTrial()) {
				break;
			}
		}
	}

	static protected function syncPayment(\stdClass $raw_data, bool $accounting): void
	{
		$payment = self::get($raw_data->id) ?? new Payment;
		$data = self::formatData($raw_data);
		$data->raw_data = &$raw_data;
		$data->id_form = Forms::getId($data->org_slug, $data->form_slug);
		if (!$form = EM::findOneById(Form::class, $data->id_form)) {
			throw new \RuntimeException(sprintf('Form not found! Form ID: %d.', $data->id_form));
		}

		if (!$payment->exists()) {
			// If accounting is enabled, we record the payment only if credit and debit accounts are set
			if (!$accounting || ($accounting && $form->id_credit_account && $form->id_debit_account)) {
				$id = DB::getInstance()->firstColumn(sprintf('SELECT id FROM %s WHERE email = \'%s\' LIMIT 1;', User::TABLE, $data->payer->email));
				$author_id = $id ?? null;
				$author_name = $data->payer->lastName . ' ' . $data->payer->firstName;
				$label = ($data->order ? $data->order->formName . ' - ' : '') . $data->payer_name . ' - ' . HelloAsso::PROVIDER_NAME . ' #' . $data->id;
				$accounts = $accounting ? [$form->id_credit_account, $form->id_debit_account] : null;
				$payment = Payments::createPayment(Payment::UNIQUE_TYPE, Payment::BANK_CARD_METHOD, self::PAHEKO_STATUS[$data->state], HelloAsso::PROVIDER_NAME, $accounts, $author_id, $author_name, $data->id, $label, $data->amount, $data, self::TRANSACTION_NOTE);
			}
		}
		else
		{
			if ($accounting && !$payment->id_transaction) { // Happens when the user decided to switch on the accounting while sync had already be done without accounting
				$transaction = Payments::createTransaction($payment, [$form->id_credit_account, $form->id_debit_account], self::TRANSACTION_NOTE);
				$payment->set('id_transaction', (int)$transaction->id);
			}
			$payment->set('amount', $data->amount);
			$payment->set('status', self::PAHEKO_STATUS[$data->state]);
			$payment->set('history', $data->date->format('Y-m-d H:i:s') . ' - '. self::UPDATE_MESSAGE . "\n" . $payment->history);
			
			$payment->setExtraData('date', $data->date);
			$payment->setExtraData('transfer_date', $data->transfer_date);
			$payment->setExtraData('person', $data->payer_name);
			$payment->setExtraData('receipt_url', $data->paymentReceiptUrl ?? null);

			$payment->save();
		}
	}

	static public function formatData(\stdClass $data): \stdClass
	{
		$formated = clone $data;
		$formated->id = (int) $data->id;
		$formated->id_order = (int) $data->order->id ?: null;
		$formated->date = new \DateTime($data->date);
		$formated->status = HA\Payment::STATES[$data->state] ?? '--';
		$formated->transferred = isset($data->cashOutState) && $data->cashOutState == HA\Payment::CASH_OUT_OK ? true : false;
		$formated->transfer_date = isset($data->cashOutDate) ? new \DateTime($data->cashOutDate) : null;
		$formated->payer_name = isset($data->payer) ? HA\Payment::getPayerName($data->payer) : null;
		$formated->payer_infos = isset($data->payer) ? HA\Payment::getPayerInfos($data->payer) : null;
		$formated->form_slug = $data->order->formSlug;
		$formated->org_slug = $data->order->organizationSlug;

		return $formated;
	}
	
	static public function reset(): void
	{
		$sql = sprintf('DELETE FROM %s WHERE provider = \'%s\';', Payment::TABLE, HelloAsso::PROVIDER_NAME);
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