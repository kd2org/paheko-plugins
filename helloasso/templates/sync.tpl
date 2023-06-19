{include file="_head.tpl" title="HelloAsso"}

{include file="%s/templates/_menu.tpl"|args:$plugin_root current="sync"}

{if $_GET.ok}
	{if $_GET.ok == 1 && (!$plugin->config->accounting || ($plugin->config->accounting && !$chargeables))}
		<p class="confirm block">Synchronisation effectuée avec succès.</p>
	{/if}
{/if}

{if isset($exceptions)}
	<p class="alert block">
		Les erreurs suivantes sont survenues durant la synchronisation.<br />Les autres entrées ont été synchronisées normalement.
	</p>
	{foreach from=$exceptions item='e'}
		<p class="error block">
			{$e->getMessage()|escape|nl2br}
			{if $e instanceof Plugin\HelloAsso\NoFuturIDSyncException}
				<br /><br />
				Soit il n'y avait pas de champ pour saisir cette information dans le formulaire HelloAsso, soit l'utilisateur/trice n'a pas renseigné cette information, soit l'option "Champ utilisé pour savoir si un membre existe déjà" est mal réglée.
			{/if}
		</p>
	{/foreach}
{/if}

{if $chargeables}
<form method="POST" action="{$self_url_no_qs}">

	<p class="alert block">
		{if $plugin->config->accounting}
			{if !$default_debit_account && !$default_credit_account}
				Pour pouvoir synchroniser la comptabilité, merci de renseigner les types de recette et comptes d'encaissement pour les articles suivants :
			{else}
				Pour pouvoir synchroniser la comptabilité, merci de confirmer les types de recette et comptes d'encaissement pré-remplis pour les articles suivants :
			{/if}
		{else}
			Pour pouvoir synchroniser les membres, merci de sélectionner quels articles doivent inscrire automatiquement les membres :
		{/if}
	</p>

	{foreach from=$chargeables key='form_name' item='form'}
		<fieldset>
			<legend>{if $form_name === 'Checkout'}Paiements isolés{else}{$form_name}{/if}</legend>
			{foreach from=$form item='chargeable'}
				{if $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
					<fieldset>
						<legend>
				{/if}
						{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::CHECKOUT_TYPE}
							{$chargeable->label}
						{elseif $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
							{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TYPE}Option {/if}"{$chargeable.label}" {$chargeable.amount|escape|money_currency}{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TYPE && $chargeable->id_item} de "{$chargeable->getItem_name()}"{/if}
							{if $chargeable->type === Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE}- Gratuit{/if}
						{/if}
					</legend>
					<dl>
						{if $plugin->config->accounting && $chargeable->type !== Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE}
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'6':$chart_id name="chargeable_credit[%d]"|args:$chargeable.id label="Type de recette" required=1 default=$default_credit_account}
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'1:2:3':$chart_id name="chargeable_debit[%d]"|args:$chargeable.id label="Compte d'encaissement" required=1 default=$default_debit_account}
						{/if}
						{input type="select" name="id_category[%d]"|args:$chargeable.id label="Inscrire comme membre dans la catégorie" data-chargeable-id=$chargeable.id default=null source=$chargeable options=$category_options required=true help="Inscrira automatiquement la personne comme membre Paheko si cet article est commandé."}
						<span class="service_fee_registration" id={"service_fee_registration_%d"|args:$chargeable.id} data-chargeable-id="{$chargeable.id|intval}">
							<?php
							$fee = $chargeable->fee();
							$default = $fee ? [ (int)$fee->id => ($chargeable->service()->label . ' - ' . $fee->label) ] : null;
							?>
							{input type="list" target="_fee_selector.php" name="id_fee[%d]"|args:$chargeable.id label="Inscrire à l'activité" required=false default=$default can_delete=true help="Les comptes ci-dessus prévalent sur ceux du tarif de l'activité sélectionnée."}
						</span>
					</dl>
				{if $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
					</fieldset>
				{/if}
			{/foreach}
		</fieldset>
	{/foreach}

	{if $plugin->config->accounting && $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
		<p class="help block">Vous pouvez définir/changer les valeurs de pré-remplissage depuis <a href="{$plugin_admin_url}config.php">la configuration de l'extension</a>.</p>
	{/if}

	{csrf_field key=$csrf_key}
	{button type="submit" name="chargeable_config_submit" label="Finaliser la synchronisation" shape="right" class="main"}
</form>
{/if}


{if !$_GET.ok}
	{if $last_sync}
		<p class="help">
			La dernière synchronisation date du {$last_sync|date}.
		</p>
	{else}
		<p class="alert block">Cliquer sur le bouton ci-dessous pour récupérer les données depuis HelloAsso.</p>
	{/if}
{/if}

{if !$last_sync && $last_sync > (new \DateTime('1 hour ago'))}
	<p class="alert block">Il n'est pas possible d'effectuer plus d'une synchronisation manuelle par heure.</p>
{else}
	<form method="post" action="{$self_url_no_qs}">
		<p class="submit">
			{csrf_field key=$csrf_key}
			{if $chargeables}
				ou bien
				{button type="submit" name="sync" value=1 label="Synchroniser uniquement les anciennes données"}
			{else}
				{button type="submit" name="sync" value=1 label="Synchroniser les données" shape="right" class="main"}
			{/if}
		</p>
	</form>
{/if}

<script type="text/javascript">
{literal}
(function () {
	let blocks = $('.service_fee_registration');
	
	for (let i = 0; i < blocks.length; ++i) {
		let chargeable_id = blocks[i].getAttribute('data-chargeable-id');
		let span = $('#f_id_fee' + chargeable_id + '_container');

		g.toggle('#service_fee_registration_' + chargeable_id, $('#f_id_category' + chargeable_id).value > 0);

		$('#f_id_category' + chargeable_id).onchange = (e) => {
			let chargeable_id = e.target.getAttribute('data-chargeable-id');
			let span = $('#f_id_fee' + chargeable_id + '_container');
			let id_category = e.target.value;

			g.toggle('#service_fee_registration_' + chargeable_id, id_category > 0);

			if (id_category === '0' && span.getElementsByTagName('span').length) {
				span.getElementsByTagName('span')[0].remove();
			}
		};
	};
})();
{/literal}
</script>

{include file="_foot.tpl"}
