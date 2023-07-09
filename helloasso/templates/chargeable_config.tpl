{include file="_head.tpl" title="Configuration de l'article HelloAsso \"%s\" n°%s"|args:$chargeable.label:$chargeable.id}

{include file="./_menu.tpl" current="home" current_sub="chargeables"}

{if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_ADMIN)}
	<h2 class="ruler">Configuration de l'article "{$chargeable->label}"</h2>

	<form method="post" action="{$self_url}">
		<fieldset>
			<legend>Inscription</legend>
			<dl>
				{input type="select" name="id_category" label="Inscrire comme membre dans la catégorie" default=null source=$chargeable options=$category_options required=true help="Inscrira automatiquement la personne comme membre Paheko si cet article est commandé."}
				<span class="service_fee_registration">
					{input type="list" target="_fee_selector.php" name="id_fee" label="Inscrire à l'activité" required=false default=$selected_fee can_delete=true help="Les comptes ci-dessous prévalent sur ceux du tarif de l'activité sélectionnée."}
				</span>
			</dl>
		</fieldset>
		{if $plugin->config->accounting && $chargeable->type !== Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE}
			<fieldset>
				<legend>Comptabilité</legend>
				<dl>
					{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'6':$chart_id name="credit" label="Type de recette" required=true default=$credit_account}
					{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'1:2:3':$chart_id name="debit" label="Compte d'encaissement" required=true default=$debit_account}
				</dl>
				<p class="help block">Cette modification impacte uniquement les <em>futures</em> synchronisations. Elle n'est pas rétro-active.</p>
			</fieldset>
		{/if}
		<p class="submit">
			{csrf_field key=$csrf_key}
			{button type="submit" class="main" name="save" label="Enregistrer" shape="right"}
		</p>
	</form>
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
