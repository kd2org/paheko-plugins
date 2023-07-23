<?php

namespace Paheko\Plugin\HelloAsso;

use Paheko\Plugin\HelloAsso\Entities\Form;
use Paheko\Plugin\HelloAsso\Entities\Order;
use Paheko\Plugin\HelloAsso\Entities as HA;
use Paheko\Plugin\HelloAsso\API;

use Paheko\Payments\Payments as Paheko_Payments;
use Paheko\Entities\Payments\Payment as Paheko_Payment;
use Paheko\Plugin\HelloAsso\Entities\Payment;
use Paheko\Payments\Users as PaymentsUsers;
use Paheko\Entities\Accounting\Transaction;
use Paheko\Entities\Users\User;

use Paheko\DB;
use Paheko\DynamicList;
use Paheko\Utils;

use KD2\DB\EntityManager as EM;

class Payments extends Paheko_Payments
{
	// HelloAsso Statuses
	const PENDING_STATUS = 'Pending';
	const AUTHORIZED_STATUS = 'Authorized';
	const REFUSED_STATUS = 'Refused';
	const UNKNOWN_STATUS = 'Unknown';
	const REGISTERED_STATUS = 'Registered';
	const REFUNDED_STATUS = 'Refunded';
	const REFUNDING_STATUS = 'Refunding';
	const CONTESTED_STATUS = 'Contested';

	// Paheko Payment matching statuses
	const STATUSES = [
		self::PENDING_STATUS => Payment::AWAITING_STATUS,
		self::AUTHORIZED_STATUS => Payment::VALIDATED_STATUS,
		self::REFUSED_STATUS => Payment::REFUSED_STATUS,
		self::UNKNOWN_STATUS => Payment::UNKNOWN_STATUS,
		self::REGISTERED_STATUS => Payment::UNKNOWN_STATUS,
		self::REFUNDED_STATUS => Payment::UNKNOWN_STATUS,
		self::REFUNDING_STATUS => Payment::UNKNOWN_STATUS,
		self::CONTESTED_STATUS => Payment::UNKNOWN_STATUS
	];

	const UPDATE_LOG_LABEL = 'Mise à jour du paiement (nouveau statut : %s).';
	const TRANSACTION_NOTE = null;
	const USER_NOTE = '%s';
	const BENEFICIARY_NOTE = 'Bénéficiaire (%s)';
	const BENEFICIARY_LOG_LABEL = 'Ajout du bénéficiaire n°%d.';
	const LABEL_UPDATED_LOG_LABEL = 'Intitulé mis à jour.';
	const ORDER_SYNCED_LOG_LABEL = 'Commande n°%s synchronisée.';
	const ITEM_SYNCED_LOG_LABEL = 'Item n°%s synchronisée.';
	const PAYER_CHANGE_LOG_LABEL = 'Rectification de la personne effectuant le paiement : %2$s (membre n°%1$d).';
	const PAYER_REGISTRATION_LOG_LABEL = 'Inscription du payeur/euse comme membre n°%d.';
	const PAYER_REGISTRATION_FAILED_LOG_LABEL = 'Inscription du payeur/euse refusée : conflit dans son identifiant "%s".';
	const CHECKOUT_PREFIX_LABEL = 'Paiement isolé';
	const WITH_BENEFICIARY_LABEL = '%s%s - Payé par %s';

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

	static protected ?array $payment_ids = null;

	static public function get(int $id): ?Payment
	{
		return EM::findOne(Payment::class, 'SELECT * FROM @TABLE WHERE provider = :provider AND reference = :id', HelloAsso::PROVIDER_NAME, $id);
	}

	static public function getId(int $reference): ?int
	{
		if (!isset(self::$payment_ids)) {
			self::$payment_ids = DB::getInstance()->getAssoc(sprintf('SELECT reference, id FROM %s;', Payment::TABLE));
		}

		return self::$payment_ids[$reference] ?? null;
	}

	static public function getByOrderId(int $id_order): ?Payment
	{
		return EM::findOne(Payment::class, 'SELECT * FROM @TABLE WHERE provider = :provider AND json_extract(extra_data, \'$.id_order\') = :id_order', HelloAsso::PROVIDER_NAME, $id_order);
	}

	static public function list(?string $provider = HA::PROVIDER_NAME, $for = null): DynamicList
	{
		$columns = [
			'id' => [],
			'reference' => [
				'label' => 'Référence',
			],
			'transactions' => [
				'label' => 'Écritures',
				'select' => sprintf('(SELECT GROUP_CONCAT(id, \';\') FROM %s t WHERE t.id_payment = %s.id)', Transaction::TABLE, Payment::TABLE)
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
			'id_payer' => [
				'label' => 'Payeur'
			],
			'payer_name' => [],
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
			throw new \InvalidArgumentException('Invalid target');
		}

		$list->setModifier(function ($row) {
			$row->state = self::STATES[$row->state] ?? 'Inconnu';
			if ($row->id_payer) {
				$row->payer = EM::findOneById(User::class, (int)$row->id_payer);
			}
			if (isset($row->transactions)) {
				$row->transactions = explode(';', $row->transactions);
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

	static public function sync(string $org_slug, $resumingPage = 1, bool $accounting = true): int
	{
		$last_payment = self::getLastPaymentDate();

		$params = [
			'pageSize'  => HelloAsso::getPageSize(),
			// Only return new Authorized payments, we are no expecting
			'states'    => self::STATE_OK,
		];

		$page_count = $resumingPage;
		$ha = HelloAsso::getInstance();

		if ($last_payment) {
			$last_payment->modify('-7 days');
			//$params['from'] = $last_payment->format('Y-m-d');
		}

		for ($i = $resumingPage; $i <= $page_count; $i++) {
			if (!$ha->stillGotTime()) {
				$ha->saveSyncProgression($i);
				return $i;
			}
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
		return 0;
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
			$payer = isset($data->payer) ? Users::findUserMatchingPayer($data->payer) : null;
			$payer_id = $payer ? (int)$payer->id : null;
			$payer_name = $data->payer_name;
			$label = ($data->order ? ($data->order->formName === 'Checkout' ? self::CHECKOUT_PREFIX_LABEL : $data->order->formName) . ' - ' : '') . $data->payer_name;
			$payment = Payments::createPayment(Payment::UNIQUE_TYPE, Payment::BANK_CARD_METHOD, self::STATUSES[$data->state], HelloAsso::PROVIDER_NAME, null, (int)HelloAsso::getInstance()->getConfig()->provider_user_id, $payer_id, $payer_name, $data->id, $label, $data->amount, null, null, $data, self::TRANSACTION_NOTE, $data->id_form);
			self::setPaymentExtraDataAndSave($payment, $data);
		}
		elseif ($payment->status !== self::STATUSES[$data->state])
		{
			$payment->set('status', self::STATUSES[$data->state]);
			$payment->addLog(sprintf(self::UPDATE_LOG_LABEL, Payment::STATUSES[$payment->status]), (new \DateTime($data->meta->updatedAt))->format('Y-m-d H:i:s'));
			self::setPaymentExtraDataAndSave($payment, $data);
		}
	}

	static protected function setPaymentExtraDataAndSave(Payment $payment, \stdClass $data): void
	{
		$payment->setExtraData('date', $data->date);
		$payment->setExtraData('transfer_date', $data->transfer_date);
		$payment->setExtraData('person', $data->payer_name);
		$payment->setExtraData('receipt_url', $data->paymentReceiptUrl ?? null);
		$payment->save();
	}

	static public function formatData(\stdClass $data): \stdClass
	{
		$formated = clone $data;
		$formated->id = (int) $data->id;
		$formated->id_order = (int) $data->order->id ?: null;
		$formated->date = new \DateTime($data->date);
		$formated->status = self::STATES[$data->state] ?? '--';
		$formated->transferred = isset($data->cashOutState) && $data->cashOutState == self::CASH_OUT_OK ? true : false;
		$formated->transfer_date = isset($data->cashOutDate) ? new \DateTime($data->cashOutDate) : null;
		$formated->payer_name = isset($data->payer) ? Payers::getPersonName($data->payer) : null;
		$formated->payer_infos = isset($data->payer) ? Payers::formatPersonInfos($data->payer) : null;
		$formated->form_slug = $data->order->formSlug;
		$formated->org_slug = $data->order->organizationSlug;

		return $formated;
	}

	static public function handleBeneficiary(int $id_user, \stdClass $data, string $label)
	{
		if (isset($data->user)) { // Means the beneficiary is not the payer
			$payment = Payments::getByReference(HelloAsso::PROVIDER_NAME, $data->payment_ref);

			if (!array_key_exists($id_user, PaymentsUsers::getIds($payment->id))) {
				PaymentsUsers::add($payment->id, [ $id_user ], [ sprintf(self::BENEFICIARY_NOTE, $label) ]);
				$payment->addLog(sprintf(self::BENEFICIARY_LOG_LABEL, $id_user));

				$beneficiary = Payers::getPersonName($data->user);
				if ($beneficiary !== $data->payer_name) {
					$payment->set('label', sprintf(self::WITH_BENEFICIARY_LABEL, ($data->order ? $data->order->formName . ' - ' : ''), $beneficiary, $data->payer_name));
					$payment->addLog(self::LABEL_UPDATED_LOG_LABEL);
				}

				$payment->save();
			}
		}
	}

	static public function createPayment(string $type, string $method, string $status, string $provider_name, ?array $accounts, ?int $author_id, ?int $payer_id, ?string $payer_name, ?string $reference, string $label, int $amount, ?array $user_ids = null, ?array $user_notes = null, ?\stdClass $extra_data = null, ?string $transaction_notes = null, ?int $id_form = null): ?Payment
	{
		if ($id_form && !DB::getInstance()->test(Form::TABLE, 'id = ?', $id_form)) {
			throw new \RuntimeException(sprintf('Inexisting form ID #%d.', $id_form));
		}

		$pa_payment = parent::createPayment($type, $method, $status, $provider_name, $accounts, $author_id, $payer_id, $payer_name, $reference, $label, $amount, $user_ids, $user_notes, $extra_data, $transaction_notes);
		$payment = self::createFromPahekoPayment($pa_payment);
		$payment->setExtraData('id_form', $id_form ?? null);
		$payment->save();

		return $payment;
	}

	static public function createFromPahekoPayment(Paheko_Payment $source): Payment
	{
		$payment = new Payment();
		$payment->loadFromPahekoPayment($source);

		return $payment;
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