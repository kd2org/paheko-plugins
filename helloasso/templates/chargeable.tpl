{include file="_head.tpl" title="Article HelloAsso n°%s"|args:$chargeable.id}

{include file="./_menu.tpl" current="home"}

{if array_key_exists('ok', $_GET)}
	<p class="confirm block">Article mis à jour avec succès.</p>
{/if}

<h2 class="ruler">Informations sur l'article</h2>

<dl class="describe">
	<dt>Label</dt>
	<dd>{$chargeable->label}</dd>
	<dt>Type</dt>
	<dd>
		{assign var='types' value=Plugin\HelloAsso\Entities\Chargeable::TYPES}
		{$types[$chargeable->type]}
	</dd>
	<dt>Montant</dt>
	<dd>{$chargeable->amount|money_currency|raw}</dd>
	<dt>Formulaire</dt>
	<dd>{$form->name}</dd>
	{if $plugin->config->accounting}
		<dt>Statut</dt>
		<dd>
			{if !$chargeable->id_credit_account || !$chargeable->id_debit_account}
				<em>En attente de configuration de votre part.</em>
				<br />Merci de remplir le formulaire "Comptabilité" ci-dessous.
			{else}
				En fonctionnement.
			{/if}
		</dd>
	{/if}
</dl>

{if $plugin->config->accounting}
	<form method="post" action="{$self_url}">
		<fieldset>
			<legend>Comptabilité</legend>
			<dl>
				{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'6':$chart_id name="credit" label="Type de recette" required=true default=$credit_account}
				{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'1:2:3':$chart_id name="debit" label="Compte d'encaissement" required=true default=$debit_account}
			</dl>
			<p class="help block">Cette modification impacte uniquement les <em>futures</em> synchronisations. Elle n'est pas rétro-active.</p>
		</fieldset>
		<p class="submit">
			{csrf_field key=$csrf_key}
			{button type="submit" class="main" name="save" label="Enregistrer" shape="right"}
		</p>
	</form>
{/if}

{if $TECH_DETAILS}
	<dl style="background-color: black; color: limegreen; padding-top: 0.8em;" class="describe">
		<dt style="color: limegreen;">item</dt>
		<dd><pre>{if $chargeable->id_item}{$parent_item|var_dump}{else}NULL{/if}</pre></dd>
		<dt style="color: limegreen;">item->raw_data</dt>
		<dd><pre>{if $chargeable->id_item}{$parent_item->raw_data|json_revamp}{else}NULL{/if}</pre></dd>
	</dl>
{/if}

{include file="_foot.tpl"}
