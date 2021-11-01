<?php

namespace Garradin\Plugin\HelloAsso;

use Garradin\UserException;

use KD2\HTTP;

class API
{
	const BASE_URL = 'https://api.helloasso.com/';

	protected $ha;

	public function __construct(HelloAsso $ha)
	{
		$this->ha = $ha;
	}


	protected function GET(string $url, array $data = [])
	{
		return $this->request('GET', $url, $data);
	}

	protected function POST(string $url, array $data = [])
	{
		return $this->request('POST', $url, $data);
	}

	protected function request(string $type, string $url, array $data = [])
	{
		$url = self::BASE_URL . $url;

		$token = $this->getToken();

		$headers = [
			'Authorization' => sprintf('Bearer %s', $token),
			'Accept'        => 'application/json',
			'User-Agent'    => 'Garradin',
		];

		if ($type == 'GET') {
			if ($data) {
				$url .= '?' . http_build_query($data);
			}

			$response = (new HTTP)->GET($url, $headers);
		}
		else {
			$response = (new HTTP)->POST($url, $data, HTTP::FORM, $headers);
		}

		if ($response->fail || $response->status != 200) {
			$error = sprintf('%d - %s', $response->status, $response->body ?: $response->error);
			throw new UserException('Erreur de l\'API HelloAsso : ' . $error);
		}

		$data = json_decode($response->body);

		if (null === $data) {
			throw new UserException('Erreur de l\'API HelloAsso, réponse illisible : ' . $response->body);
		}

		return $data;
	}

	/******* OAUTH METHODS ********/
	protected function getToken(): string
	{
		$oauth = $this->ha->getOAuth();

		if (empty($oauth->access_token) || empty($oauth->expiry)) {
			throw new UserException('Authentification à l\'API impossible, merci de renseigner les informations de connexion à l\'API dans la configuration.');
		}
		elseif ($oauth->expiry - 10 <= time()) {
			return $this->refreshToken($oauth->refresh_token);
		}

		return $oauth->access_token;
	}

	public function createToken(string $secret): string
	{
		$params = [
			'grant_type'    => 'client_credentials',
			'client_id'     => $this->ha->getClientId(),
			'client_secret' => $secret,
		];

		return $this->requestToken($params);
	}

	protected function refreshToken(string $token): string
	{
		$params = [
			'grant_type'    => 'refresh_token',
			'client_id'     => $this->ha->getClientId(),
			'refresh_token' => $token,
		];

		return $this->requestToken($params);
	}

	protected function requestToken(array $params): string
	{
		$url = self::BASE_URL . 'oauth2/token';

		$response = (new HTTP)->POST($url, $params);

		if ($response->fail || $response->status != 200) {
			$error = sprintf('%d - %s', $response->status, $response->body ?: $response->error);
			throw new UserException('Erreur de l\'API HelloAsso : ' . $error);
		}

		$oauth = json_decode($response->body);

		if (null === $oauth) {
			throw new UserException('Erreur de l\'API HelloAsso, réponse illisible : ' . $response->body);
		}

		if (!isset($oauth->access_token, $oauth->refresh_token, $oauth->expires_in, $oauth->token_type)) {
			throw new UserException('Erreur de l\'API HelloAsso à l\'authentification, essayez de supprimer puis de remettre les informations de connexion à l\'API dans la configuration.');
		}

		$oauth->expiry = time() + $oauth->expires_in;

		$this->ha->saveOAuth($oauth);
		return $oauth->access_token;
	}

	protected function assert($condition)
	{
		if (!$condition) {
			throw new \RuntimeException('Données manquantes depuis HelloAsso !');
		}
	}

	public function listOrganizations(): array
	{
		$result = $this->GET('v5/users/me/organizations');

		$this->assert(is_array($result));

		if (count($result)) {
			$r = $result[0];
			$this->assert(isset($r->name));
			$this->assert(isset($r->organizationSlug));
		}

		return $result;
	}

	public function listForms(string $organization): array
	{
		if (!preg_match('/^[a-z0-9_-]+$/', $organization)) {
			throw new \RuntimeException('Invalid organization slug');
		}

		$params = ['pageSize' => 100];

		$result = $this->GET(sprintf('v5/organizations/%s/forms', $organization), $params);

		$this->assert(isset($result->data));
		$this->assert(is_array($result->data));

		if (count($result->data)) {
			$r = $result->data[0];
			$this->assert(isset($r->title));
			$this->assert(isset($r->formType));
			$this->assert(isset($r->formSlug));
			$this->assert(isset($r->state));
		}

		return $result->data;
	}

	public function listOrganizationOrders(string $organization, int $page, int $per_page): \stdClass
	{
		if (!preg_match('/^[a-z0-9_-]+$/', $organization)) {
			throw new \RuntimeException('Invalid organization slug');
		}

		$params = [
			'pageSize'    => $per_page,
			'pageIndex'   => $page,
			'withDetails' => 'true',
		];

		$result = $this->GET(sprintf('v5/organizations/%s/orders', $organization), $params);

		$this->assertOrders($result);

		return $result;
	}

	public function listFormOrders(string $organization, string $form_type, string $form_slug, int $page, int $per_page): \stdClass
	{
		if (!preg_match('/^[a-z0-9_-]+$/', $organization)) {
			throw new \RuntimeException('Invalid organization slug');
		}

		if (!preg_match('/^[a-z0-9_-]+$/', $form_slug)) {
			throw new \RuntimeException('Invalid form slug');
		}

		if (!preg_match('/^[a-z0-9_-]+$/i', $form_type)) {
			throw new \RuntimeException('Invalid form type');
		}

		$params = [
			'pageSize'    => $per_page,
			'pageIndex'   => $page,
			'withDetails' => 'true',
		];

		$result = $this->GET(sprintf('v5/organizations/%s/forms/%s/%s/orders', $organization, $form_type, $form_slug), $params);

		$this->assertOrders($result);

		return $result;
	}

	public function assertOrders(\stdClass $result)
	{
		$this->assert(isset($result->data));
		$this->assert(is_array($result->data));
		$this->assert(isset($result->pagination->totalCount));

		if (count($result->data)) {
			$r = $result->data[0];
			$this->assert(isset($r->date));
			$this->assert(strtotime($r->date));
			$this->assert(isset($r->id));
			$this->assert(isset($r->payer->firstName));
			$this->assert(isset($r->payer->lastName));
			$this->assert(isset($r->amount->total) && ctype_digit($r->amount->total));
		}
	}

	public function listOrganizationPayments(string $organization, int $page, int $per_page): \stdClass
	{
		if (!preg_match('/^[a-z0-9_-]+$/', $organization)) {
			throw new \RuntimeException('Invalid organization slug');
		}

		$params = [
			'pageSize'  => $per_page,
			'pageIndex' => $page,
		];

		$result = $this->GET(sprintf('v5/organizations/%s/payments', $organization), $params);

		$this->assertPayments($result);

		return $result;
	}

	protected function assertPayments($result)
	{
		$this->assert(isset($result->data));
		$this->assert(is_array($result->data));
		$this->assert(isset($result->pagination->totalCount));

		if (count($result->data)) {
			$r = $result->data[0];
			$this->assert(isset($r->date));
			$this->assert(strtotime($r->date));
			$this->assert(isset($r->order->id));
			$this->assert(isset($r->payer));
			$this->assert(isset($r->state));
			$this->assert(isset($r->id));
			$this->assert(isset($r->paymentReceiptUrl));
			$this->assert(isset($r->amount) && ctype_digit($r->amount));
		}
	}

	public function listFormPayments(string $organization, string $form_type, string $form_slug, int $page, int $per_page): \stdClass
	{
		if (!preg_match('/^[a-z0-9_-]+$/', $organization)) {
			throw new \RuntimeException('Invalid organization slug');
		}

		if (!preg_match('/^[a-z0-9_-]+$/', $form_slug)) {
			throw new \RuntimeException('Invalid form slug');
		}

		if (!preg_match('/^[a-z0-9_-]+$/i', $form_type)) {
			throw new \RuntimeException('Invalid form type');
		}

		$params = [
			'pageSize'  => $per_page,
			'pageIndex' => $page,
		];

		$result = $this->GET(sprintf('v5/organizations/%s/forms/%s/%s/payments', $organization, $form_type, $form_slug), $params);

		$this->assertPayments($result);

		return $result;
	}

	public function getPayment(string $id): \stdClass
	{
		return $this->GET(sprintf('v5/payments/%s', $id));
	}

	public function getOrder(string $id): \stdClass
	{
		return $this->GET(sprintf('v5/orders/%s', $id));
	}
}
