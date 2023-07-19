<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\Config;
use Garradin\DB;
use Garradin\Entities\Plugin;
use Garradin\Entities\Users\User;
use Garradin\Entities\Payments\Payment;
use KD2\DB\EntityManager;

use Garradin\Plugin\HelloAsso\Entities\Form;

use function Garradin\garradin_contributor_license;

class HelloAsso
{
	const NAME							= 'helloasso';
	const PROVIDER_NAME					= self::NAME;
	const PROVIDER_LABEL				= 'HelloAsso';
	const ACCOUNTING_DISABLED			= 0;
	const ACCOUNTING_ENABLED			= 1;
	const ACCOUNTING_OPTIONS			= [ self::ACCOUNTING_DISABLED => 'Désactivée', self::ACCOUNTING_ENABLED => 'Activée' ];
	const PAYMENT_EXPIRATION			= '2 days';
	const CHECKOUT_LINK_EXPIRATION		= '15 minutes';
	const CHECKOUT_CREATION_LOG_LABEL	= 'Tunnel de paiement ' . self::PROVIDER_LABEL . ' n°%d créé.';
	const PAYMENT_RESUMING_LOG_LABEL	= 'Reprise du paiement.';
	const LOG_FILE						= __DIR__ . '/../logs';
	const REDIRECTION_FILE				= 'payer.php';
	const DEFAULT_CLIENT_ID				= '';
	const PER_PAGE						= 60;
	const TIME_SAFE_MARGIN				= 4;

	protected				$plugin;
	protected ?\stdClass	$config;

	static protected		$_instance;
	protected				$_max_execution_time;
	protected				$_sync_start_time;
	protected ?Sync			$_sync = null;

	static public function getInstance()
	{
		if (null === self::$_instance) {
			self::$_instance = new self;
		}

		return self::$_instance;
	}

	protected function __construct()
	{
		$this->plugin = EntityManager::getInstance(Plugin::class)->one('SELECT * FROM @TABLE WHERE name = ? LIMIT 1;', self::NAME);

		$this->config = $this->plugin->getConfig();
	}

	public function plugin(): Plugin
	{
		return $this->plugin;
	}

	public function sync(): bool
	{
		$this->getSync();
		if ($this->_sync->isCompleted()) {
			$this->_sync->reset();
		}

		$this->_max_execution_time = ini_get('max_execution_time');
		$this->_sync_start_time = getrusage()['ru_utime.tv_sec'];

		Forms::sync();
		if ($organizations = array_keys(Forms::listOrganizations())) {
			$this->plugin->setConfigProperty('default_organization', $organizations[0]);
		}

		$page = 0;
		foreach ($organizations as $org_slug) {
			if ($this->_sync->getStep() === Sync::ORDERS_STEP) {
				$page = Orders::sync($org_slug, $this->_sync->getPage() ?? 1);
				$this->saveSyncProgression($page);
			}
			if ($this->_sync->getStep() === Sync::PAYMENTS_STEP) {
				$page = Payments::sync($org_slug, $this->_sync->getPage() ?? 1, $this->config->accounting);
				$this->saveSyncProgression($page);
			}
			if ($this->_sync->getStep() === Sync::ITEMS_STEP) {
				$page = Items::sync($org_slug, $this->_sync->getPage() ?? 1, $this->config->accounting);
				$this->saveSyncProgression($page);
			}
		}

		return !$page;
	}

	public function getSync(): Sync
	{
		if (null === $this->_sync) {
			$this->_sync = $this->plugin->getConfig()->sync ? Sync::loadFromStdClass($this->plugin->getConfig()->sync) : new Sync();
		}
		return $this->_sync;
	}

	public function saveSyncProgression(int $page): void
	{
		if (0 === $page) {
			$this->_sync->goNextStep();
		}
		$this->_sync->setPage($page === 0 ? null : $page);
		$this->_sync->setDate(new \DateTime);
		$this->plugin->setConfigProperty('sync', $this->_sync);
		$this->plugin->save();
	}

	public function stillGotTime(): bool
	{
		return (\getrusage()['ru_utime.tv_sec'] - $this->_sync_start_time + self::TIME_SAFE_MARGIN < $this->_max_execution_time);
	}

	public function getClientId(): ?string
	{
		return $this->config->client_id;
	}

	public function saveClient(string $client_id, string $client_secret): void
	{
		$client_id = trim($client_id);
		$old_client_id = $this->config->client_id;
		
		if ($client_id !== $old_client_id) {
			$this->reset();
		}

		$api = API::getInstance();
		$api->register($client_id, $client_secret);
		
		if ($client_id !== $old_client_id && $old_client_id !== self::DEFAULT_CLIENT_ID) {
			$this->sync();
		}
	}

	public function reset(): void
	{
		Forms::reset();
		Orders::reset();
		Payments::reset();
		Items::reset();
	}

	public function initConfig(int $provider_user_id): bool {
		$this->plugin->setConfigProperty('accounting', self::ACCOUNTING_ENABLED);
		$this->plugin->setConfigProperty('client_id', self::DEFAULT_CLIENT_ID);
		$this->plugin->setConfigProperty('provider_user_id', $provider_user_id);
		$this->plugin->setConfigProperty('id_credit_account', false);
		$this->plugin->setConfigProperty('id_debit_account', false);
		$this->plugin->setConfigProperty('sync', false);
		$payer_map = new \stdClass();
		foreach (array_keys(API::PAYER_FIELDS) as $field) {
			$payer_map->$field = null;
		}
		$this->plugin->setConfigProperty('payer_map', $payer_map);
		return $this->plugin->save();
	}

	public function saveConfig(array $data): bool
	{
		$this->plugin->setConfigProperty('accounting', $data['accounting']);
		$this->plugin->setConfigProperty('id_credit_account', $data['default_credit']);
		$this->plugin->setConfigProperty('id_debit_account', $data['default_debit']);
		$this->plugin->setConfigProperty('payer_map', $data['payer_map']);
		$this->plugin->setConfigProperty('user_match_type', $data['user_match_type']);
		$this->plugin->setConfigProperty('user_match_field', $data['user_match_field']);
		return $this->plugin->save();
	}

	public function isConfigured(): bool
	{
		return empty($this->config->oauth) ? false : true;
	}

	public function getConfig(): ?\stdClass {
		return $this->config;
	}

	public function createCheckout(string $organization, string $label, int $amount, int $author_id, User $payer, ?array $accounts = null): \stdClass
	{
		$label .= ' - ' . $payer->nom . ' - ' . self::PROVIDER_LABEL;

		// Resume user failed attemp
		if ($payment = EntityManager::findOne(Payment::class, 'SELECT * FROM @TABLE WHERE id_author = :id_user AND label = :label AND status = :status AND method = :method AND type = :type AND date >= datetime(\'now\', :expiration)', (int)$payer->id, $label, Payment::AWAITING_STATUS, Payment::BANK_CARD_METHOD, Payment::UNIQUE_TYPE, '-' . self::PAYMENT_EXPIRATION)) {
			// Resume current checkout
			if (isset($payment->extra_data->checkout) && !(new \DateTime($payment->extra_data->checkout->date) < new \DateTime('now -' . self::CHECKOUT_LINK_EXPIRATION))) {
				return $payment->extra_data->checkout;
			}
			$payment->addLog(self::PAYMENT_RESUMING_LOG_LABEL);
		}
		else {
			$payment = Payments::createPayment(Payment::UNIQUE_TYPE, Payment::BANK_CARD_METHOD, Payment::AWAITING_STATUS, self::PROVIDER_NAME, null, $author_id, $payer->id, $payer->nom, null, $label, $amount, null, null, null, null);
		}
		$csrf = 'COMING_SOON_CSRF';
		$metadata = [
			'payment_id' => $payment->id,
			'user_id'  => $payment->id_author,
			'csrf' => $csrf
		];
		$checkout = API::getInstance()->createCheckout($organization, $payment->amount, $label, $payment->id, $this->plugin->url() . self::REDIRECTION_FILE, $metadata);
		$checkout->date = (new \DateTime())->format('Y-m-d H:i:s');
		$checkout->csrf = $csrf;
		$payment->setExtraData('checkout', $checkout);
		$payment->set('reference', $checkout->id);
		$payment->setExtraData('organization', $organization);
		$payment->setExtraData('id_credit_account', $accounts ? $accounts[0] : null);
		$payment->setExtraData('id_debit_account', $accounts ? $accounts[1] : null);
		$payment->addLog(sprintf(self::CHECKOUT_CREATION_LOG_LABEL, (int)$checkout->id));
		$payment->save();

		return $checkout;
	}

	static public function checkPaymentStatus(Payment $payment): void
	{
		if ($payment->status !== Payment::AWAITING_STATUS) {
			return ;
		}

		if ($payment->provider != self::PROVIDER_NAME) {
			throw new \LogicException(sprintf('This is not a %s payment!', self::PROVIDER_LABEL));
		}

		if (empty($payment->reference)) {
			throw new \LogicException('This payment does not have a reference.');
		}

		$checkout = API::getInstance()->getCheckout($payment->org_slug, (int)$payment->reference);

		file_put_contents(self::LOG_FILE, sprintf("\n\n==== %s - Fetch: %s ====\n\n%s\n", date('d/m/Y H:i:s'), $payment->reference, json_encode($checkout, JSON_PRETTY_PRINT)), FILE_APPEND);

		if ($checkout->metadata->payment_id != $payment->id) {
			throw new \LogicException(sprintf('Payment ref. "%s" does not match payment ID "%s" (metadata payment_id = %s)', $payment->reference, $payment->id, $checkout->metadata->payment_id));
		}

		if ($checkout->metadata->csrf !== $payment->checkout->csrf) {
			throw new \LogicException(sprintf('Wrong received CSRF (%s) while trying to check payment status of payment ID %s.', $checkout->metadata->csrf, $payment->id));
		}

		/*if (!isset($checkout->order)) {
			// The payment is still waiting
			//mail(ROOT_EMAIL, 'HelloAsso payment data issue', json_encode(['data' => $checkout, 'SERVER' => $_SERVER], JSON_PRETTY_PRINT));
			return;
		}*/

		if (!isset($checkout->order->payments[0]->state, $checkout->order->payments[0]->amount)) {
			throw new \LogicException('Payment is missing details: ' . json_encode($checkout, JSON_PRETTY_PRINT));
		}

		if ($checkout->order->payments[0]->state != Payments::AUTHORIZED_STATUS) {
			self::log(sprintf('NOTHING TO DO. Reason: only handle authorized status (received: %s).', $checkout->order->payments[0]->state));
			return;
		}

		$payment->validate((int)$checkout->order->payments[0]->amount, $checkout->order->payments[0]->paymentReceiptUrl ?? null);
	}

	public static function handleCallback(): void
	{
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			throw new \RuntimeException('Invalid request method');
		}

		$data = file_get_contents('php://input');

		if (empty($data)) {
			throw new \RuntimeException('Empty POST data');
		}

		$json = json_decode($data);

		if (null === $json) {
			throw new \RuntimeException('Invalid JSON data: ' . $data);
		}

		// Some logging
		self::log(sprintf("\n\n==== Callback: %s ====\n\n%s\n", date('d/m/Y H:i:s'), json_encode($json, JSON_PRETTY_PRINT)));

		if (empty($json->eventType)) {
			throw new \RuntimeException('Invalid JSON response, missing eventType: ' . $data);
		}

		if (strtolower($json->eventType) == 'payment') {
			if (empty($json->metadata->payment_id)) {
				self::log('CALLBACK IGNORED. Reason: no payment_id inside metadata.');
				// Ignore
				return;
			}

			$payment = EntityManager::findOneById(Payment::class, (int)$json->metadata->payment_id);

			if (!$payment) {
				//mail(ROOT_EMAIL, 'Callback d\'un paiement qui n\'existe pas', json_encode($json, JSON_PRETTY_PRINT));
				self::log(sprintf('CALLBACK IGNORED. Reason: Payment not found for ID #%s.', $json->metadata->payment_id));
				return;
			}
			
			if ($payment->reference != $json->data->id) {
				throw new \RuntimeException(sprintf('Payment reference (#%s) and checkout ID (#%s) mismatch!', $payment->id, $json->data->id));
			}

			// Don't trust data sent to this callback, let's fetch data
			self::checkPaymentStatus($payment);
		}
		else {
			// Ignore for now
			self::log(sprintf('CALLBACK IGNORED. Reason: \'eventType\'s different from \'payment\' are not yet implemented (received: %s).', $json->eventType));
		}
	}

/*
	public function listTargets(): array
	{
		return EM::getInstance(Target::class, 'SELECT * FROM @TABLE ORDER BY label;');
	}
*/
	static public function log(string $message): void
	{
		file_put_contents(self::LOG_FILE, $message, FILE_APPEND);
	}

	static public function cron() {
	}

	/**
	 * Toutes ces lignes de code ne se sont pas écrites toutes seules…
	 * Merci de contribuer à Garradin ;)
	 */
	const LEVEL_REQUIRED = 50;
	const PER_PAGE_TRIAL = 5;


	static public function getPageSize(): int
	{
		return self::isTrial() ? self::PER_PAGE_TRIAL : self::PER_PAGE;
	}

	/**
	 * Merci de contribuer à Garradin pour obtenir une licence :)
	 */
	static public function isTrial(): bool
	{
		$level = 100;//\Garradin\garradin_contributor_license();

		if ($level < self::LEVEL_REQUIRED) {
			return true;
		}
		else {
			return false;
		}
	}
}
