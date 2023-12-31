{include file="admin/_head.tpl" title="Commande n°%s — %s"|args:$order.id,$order.person current="plugin_%s"|args:$plugin.id}

{include file="%s/templates/_menu.tpl"|args:$plugin_root current="home"}

<h2 class="ruler">Informations de la commande</h2>

<dl class="describe">
	<dt>Personne</dt>
	<dd>{$order.person}</dd>
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
	Membre correspondant trouvé : <a href="{$admin_url}membres/fiche.php?id={$found_user.id}">{$found_user.identity}</a>
</p>
{else}
<form method="post" action="{$admin_url}membres/ajouter.php">
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

{include file="admin/_foot.tpl"}
