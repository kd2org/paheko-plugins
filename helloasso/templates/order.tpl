{include file="_head.tpl" title="Commande n°%s — %s"|args:$order.id,$order.person}

{include file="./_menu.tpl" current="home"}

{if isset($_GET.ok)}
	<p class="confirm block">
		Payeur/euse inscrit·e avec succès.
	</p>
{/if}

<h2 class="ruler">Informations de la commande</h2>

<dl class="describe">
	<dt>Personne</dt>
	<dd class="num">{if $order.id_payer}{$payer->nom} <a href="{$admin_url}users/details.php?id={$payer->id|intval}">{$payer->numero}</a>{else}{$order.payer_name}{/if}</dd>
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

{include file="%s/templates/_items_list.tpl"|args:$plugin_root list=$items count_opti=$items_count_list details=false}

<h2 class="ruler">Options de la commande</h2>

{include file="%s/templates/_options_list.tpl"|args:$plugin_root list=$options count_opti=$options_count_list details=false}

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
	{if $payer && $payer->nom === \Garradin\Plugin\HelloAsso\Users::guessUserName($order->getRawPayer())}
		<dt>Membre correspondant·e</dt>
		<dd class="num">{$payer->nom} <a href="{$admin_url}users/details.php?id={$payer->id|intval}">{$payer->numero}</a></dd>
	{elseif $guessed_user}
		<dt>Membre correspondant·e</dt>
		<dd class="num">{$guessed_user->nom} <a href="{$admin_url}users/details.php?id={$guessed_user->id|intval}">{$guessed_user->numero}</a></dd>
	{/if}
</dl>

{if !$payer || $payer->nom !== \Garradin\Plugin\HelloAsso\Users::guessUserName($order->getRawPayer())}
	<form method="post" action="{$self_url}">
		{csrf_field key=$csrf_key}
		{if $guessed_user}
			<p class="block confirm">
				Membre correspondant·e trouvé·e : <a href="{$admin_url}users/details.php?id={$guessed_user.id}">{$guessed_user.nom}</a>
				{button type="submit" name="create_payer" shape="check" label="Confirmer la correspondance"}
			</p>
		{else}
			<p class="alert block">
				Aucun·e membre correspondant·e n'a été trouvé·e.<br />
				{button type="submit" name="create_payer" shape="plus" label="Créer un·e membre avec ces informations"}
			</p>
		{/if}
	</form>
{/if}

{include file="_foot.tpl"}
