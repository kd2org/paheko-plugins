{include file="_head.tpl" title="Commande n°%s — %s"|args:$order.id,$order.person}

{include file="./_menu.tpl" current="home"}

<h2 class="ruler">Informations de la commande</h2>

<dl class="describe">
	<dt>Personne</dt>
	<dd class="num">{if $order.id_user}{$user->nom} <a href="{$admin_url}users/details.php?id={$user->id|intval}">{$user->numero}</a>{else}{$order.person}{/if}</dd>
	<dt>Référence</dt>
	<dd>{$order.id}</dd>
	<dt>Montant total</dt>
	<dd>{$order.amount|money_currency|raw}</dd>
	<dt>Date</dt>
	<dd>{$order.date|date}</dd>
	<dt>Statut</dt>
	<dd>{if $order.status}Payée{else}Paiement incomplet{/if}</dd>
</dl>

<h2 class="ruler">Éléments de la commande</h2>

{include file="%s/templates/_items_list.tpl"|args:$plugin_root list=$items details=false}

<h2 class="ruler">Options de la commande</h2>

{include file="%s/templates/_options_list.tpl"|args:$plugin_root list=$options details=false}

<h2 class="ruler">Paiements</h2>

{include file="%s/templates/_payments_list.tpl"|args:$plugin_root list=$payments details=false}

<h2 class="ruler">Personne ayant effectué le paiement</h2>

<dl class="describe">
	{foreach from=$payer_infos key="key" item="value"}
	<dt>{$key}</dt>
	<dd>
		{if $value instanceof \DateTime}
			{$value|date:'d/m/Y'}
		{else}
			{$value}
		{/if}
	</dd>
	{/foreach}
</dl>

{*
{if $found_user}
<p class="block confirm">
	Membre correspondant trouvé : <a href="{$admin_url}users/details.php?id={$found_user.id}">{$found_user.identity}</a>
</p>
{else}
<form method="post" action="{$admin_url}users/new.php">
<p class="alert block">
	Aucun membre correspondant n'a été trouvé.<br />
	{foreach from=$mapped_user key="key" item="value"}
	<input type="hidden" name="{$key}" value="{$value}" />
	{/foreach}
	{button type="submit" shape="plus" label="Créer un membre avec ces informations"}
</p>
</form>
{/if}
*}

{include file="_foot.tpl"}
