{include file="_head.tpl" title="HelloAsso"}

{include file="./_menu.tpl" current="home" current_sub=null}

<table class="list">
	<thead>
		<tr>
			<td>Organisme</td>
			<th>Formulaire</th>
			<td>Type</td>
			<td>Statut</td>
		</tr>
	</thead>
	<tbody>
		{foreach from=$list item="form"}
		<tr{if $form.state == 'Disabled'} class="disabled"{/if}>
			<td>{$form.org_name}</td>
			<th><a href="orders.php?id={$form.id}">{$form.label}</a></th>
			<td>{$form.type_label}</td>
			<td>{$form.state_label}</td>
		</tr>
		{/foreach}
	</tbody>
</table>

{if $plugin.config.accounting}

	{if isset($checkout)}
		<h2 class="ruler">Proposition de paiement</h2>
		<p class="confirm block">Tunnel de paiement généré avec succès.</p>
		<dl class="describe">
			<dt>ID</dt>
			<dd>{$checkout->id}</dd>
			<dt>URL</dt>
			<dd><a href="{$checkout->url}">{$checkout->url}</a></dd>
		</dl>
		<p class="help block">Pour rappel, ce tunnel de paiement/lien n'est valide que 15 minutes.</p>
	{else}
		<h2 class="ruler">Proposer un paiement à un·e membre</h2>

		<form method="POST" action="{$self_url}">
			<fieldset>
				<legend>Paiement</legend>
				<dl>
					{input type="select" name="org_slug" label="Association" options=$orgOptions required=true}
					{input type="text" name="label" label="Libellé" required=true}
					{input type="money" name="amount" label="Montant" required=true}
					{input type="list" name="user" label="Membre" target="!users/selector.php" can_delete="true" required=true}
				</dl>
				<dl>
					{input type="select" name="id_category" label="Inscrire comme membre dans la catégorie" default=null options=$category_options required=true help="Inscrira automatiquement la personne comme membre Paheko une fois le paiement validé."}
					<span class="service_fee_registration">
						{input type="list" target="_fee_selector.php" name="id_fee" label="Inscrire à l'activité" required=false can_delete=true help="Les comptes ci-dessous prévalent sur ceux du tarif de l'activité sélectionnée."}
					</span>
					{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:$ca_type:$chart_id name="credit" label="Type de recette" required=1}
					{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:$da_type:$chart_id name="debit" label="Compte d'encaissement" required=1}
				</dl>
			</fieldset>

			{csrf_field key=$csrf_key}
			{button type="submit" name="generate_checkout" label="Créer" class="main"}
		</form>
	{/if}

{/if}

<script type="text/javascript">
{literal}
(function () {
	g.toggle('.service_fee_registration', $('#f_id_category').value > 0);

	$('#f_id_category').onchange = () => {
		g.toggle('.service_fee_registration', $('#f_id_category').value > 0);
		if ($('#f_id_category').value === '0' && $('#f_id_fee_container').getElementsByTagName('span').length) {
			$('#f_id_fee_container').getElementsByTagName('span')[0].remove();
		}
	};
})();
{/literal}
</script>

{include file="_foot.tpl"}
