{include file="_head.tpl" title="Paiement HelloAsso n°%s"|args:$payment.reference}

{include file="./_menu.tpl" current="home"}

<h2 class="ruler">Informations sur le paiement</h2>

<dl class="describe">
	<dt>Label</dt>
	<dd>{$payment->label}</dd>
	<dt>Statut</dt>
	<dd>{$payment_statuses[$payment->status]}</dd>
	<dt>Montant</dt>
	<dd>{$payment->amount|money_currency|raw}</dd>
	<dt>Référence</dt>
	<dd>{$payment->reference}</dd>
	<dt>Méthode</dt>
	<dd>{$payment_methods[$payment->method]}</dd>
	<dt>Type</dt>
	<dd class="num">
		{$payment_types[$payment->type]}
		{if isset($payment->parent_id)} — lié au paiement {link href="payment.php?id=%d"|args:$payment->parent_id label=$payment->parent_id}{/if}
		{if isset($payment->children)} — Paiements suivants :
			{foreach from=$payment->children item='id_payment'}
				{link href="payment.php?id=%d"|args:$id_payment label=$id_payment}
			{/foreach}
		{/if}
	</dd>
	<dt>Payeur/euse</dt>
	<dd>{if $payer}{link href="!users/details.php?id=%d"|args:$payer->id label=$payer->nom}{else}{$payment->payer_name}{/if}</dd>
	<dt>Bénéficiaires</dt>
	<dd class="num">
		{if $users}
			<ul class="flat">
			{foreach from=$users item='user'}
				<li>{$user->nom} {link href="!users/details.php?id=%d"|args:$user->id label=$user->numero}{if isset($users_notes[$user->id])} ({$users_notes[$user->id]}){/if}</li>
			{/foreach}
			</ul>
		{else}
			—
		{/if}
	</dd>
	{if $form}
		<dt>Formulaire</dt>
		<dd><a href="{$plugin_admin_url}orders.php?id={$form->id}">{$form->label}</a></dd>
	{/if}
	{if $order}
		<dt>Commande</dt>
		<dd><a href="{$plugin_admin_url}order.php?id={$order->id}">{if $payer}{$payer->nom}{else}{$payment->payer_name}{/if} - {$order->date|date}</a></dd>
	{/if}
	<dt>Écritures comptables</dt>
	<dd>
		{if !$plugin->config->accounting}
			Aucune
			{if $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
				<span class="help">(vous pouvez activer la génération d'écritures depuis <a href="{$plugin_admin_url}config.php">la configuration de l'extension</a>)</span>
			{/if}
		{else}
			{if $transactions}
				{foreach from=$transactions item='transaction'}
					<mark><a href="{$admin_url}acc/transactions/details.php?id={$transaction->id}">{$transaction->id}</a></mark>
				{/foreach}
			{else}
				—
			{/if}
		{/if}
	</dd>
	<dt>Historique</dt>
	<dd>{$payment->history|escape|nl2br}</dd>
</dl>

{if $TECH_DETAILS}
	<dl style="background-color: black; color: limegreen; padding-top: 0.8em;" class="describe">
		<dt style="color: limegreen;">extra_data</dt>
		<dd><pre>{$payment->extra_data|dump}</pre></dd>
	</dl>
{/if}

{include file="_foot.tpl"}
