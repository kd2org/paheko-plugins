{include file="_head.tpl" title="Paiement HelloAsso n°%s"|args:$payment.reference}

{include file="./_menu.tpl" current="home"}

<h2 class="ruler">Informations sur le paiement</h2>

<dl class="describe">
	<dt>Label</dt>
	<dd>{$payment->label}</dd>
	<dt>Statut</dt>
	<dd>{$payment->status}</dd>
	<dt>Montant</dt>
	<dd>{$payment->amount|money_currency|raw}</dd>
	<dt>Référence</dt>
	<dd>{$payment->reference}</dd>
	<dt>Méthode</dt>
	<dd>{$payment->method}</dd>
	<dt>Type</dt>
	<dd>{$payment->type}</dd>
	<dt>Auteur/trice</dt>
	<dd>{if $author}<a href="{$admin_url}users/details.php?id={$author->id}">{$author->nom}</a>{else}{$payment->author_name}{/if}</dd>
	{if $form}
		<dt>Formulaire</dt>
		<dd><a href="{$plugin_admin_url}form.php?id={$form->id}">{$form->name}</a></dd>
	{/if}
	{if $order}
		<dt>Commande</dt>
		<dd><a href="{$plugin_admin_url}order.php?id={$order->id}">{$order->person} - {$order->date|date}</a></dd>
	{/if}
	<dt>Écriture comptable</dt>
	<dd>
		{if $payment->id_transaction}
			<mark><a href="{$admin_url}acc/transactions/details.php?id={$payment->id_transaction}">{$payment->id_transaction}</a></mark>
		{else}
			{if !$plugin->config->accounting && $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
				Aucune <span class="help">(vous pouvez activer la génération d'écritures depuis <a href="{$plugin_admin_url}config_client.php">la configuration de l'extension</a>)</span>
			{else}
			-
			{/if}
		{/if}
	</dd>
</dl>

{if $TECH_DETAILS}
	<dl style="background-color: black; color: limegreen; padding-top: 0.8em;" class="describe">
		<dt style="color: limegreen;">extra_data</dt>
		<dd><pre>{$payment->extra_data|dump}</pre></dd>
	</dl>
{/if}

{include file="_foot.tpl"}
