{include file="_head.tpl" title="HelloAsso" custom_css='%sstyle.css'|args:$plugin_admin_url}

{include file="%s/templates/_menu.tpl"|args:$plugin_root current="sync"}

{if $sync->getDate() && !$sync->isCompleted()}
	<p class="alert block">
		La synchronisation a été interrompu au stade "{$current_step_label}". Vous pouvez la reprendre en cliquant sur le bouton "Reprendre la synchronisation".
	</p>

	<h2>État d'avancement</h2>
	<ul>
		{assign var=in_progress' value=true}
		{foreach from=$steps key='step' item='label'}
			<li{if $sync->getStep() !== $step && $in_progress} class="step_done"{/if}>
				{if $sync->getStep() === $step}
					{assign var=in_progress' value=false}
					{$label} : {if $sync->getPage() === null}à démarrer{else}en cours (page {$sync->getPage()}){/if}
				{else}
					{$label} : {if $in_progress}fait{else}à venir{/if}
				{/if}
			</li>
		{/foreach}
	</ul>
{/if}

{if $_GET.ok}
	{if $_GET.ok == 1 && ((!$plugin->config->accounting || ($plugin->config->accounting && !$chargeables)) && !$forms)}
		<p class="confirm block">Synchronisation effectuée avec succès.</p>
	{/if}
{/if}

{if isset($exceptions) && $exceptions}
	<p class="alert block">
		Les erreurs suivantes sont survenues durant la synchronisation.<br />Les autres entrées ont été synchronisées normalement.
	</p>
	{foreach from=$exceptions item='e'}
		<p class="error block">
			{$e->getMessage()|escape|nl2br}
			{if $e instanceof Plugin\HelloAsso\NoFuturIDSyncException}
				<br /><br />
				Soit il n'y avait pas de champ pour saisir cette information dans le formulaire HelloAsso, soit l'utilisateur/trice n'a pas renseigné cette information, soit la configuration "Correspondance des champs HelloAsso" du formulaire n'est pas correcte, soit l'option "Champ utilisé pour savoir si un membre existe déjà" est mal réglée.
			{/if}
		</p>
	{/foreach}
{/if}

{if ($chargeables || $forms)}
<form method="POST" action="{$self_url_no_qs}" class="sync">
{/if}

{if $chargeables}
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

	{foreach from=$chargeables key='form_label' item='form'}
		<fieldset>
			<legend>{if $form_label === 'Checkout'}Paiements isolés{else}{$form_label}{/if}</legend>
			{foreach from=$form item='chargeable'}
				{if $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
					<fieldset>
						<legend>
				{/if}
						{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::CHECKOUT_TYPE}
							{$chargeable->label}
						{elseif $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
							{if $chargeable.target_type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TARGET_TYPE}Option {/if}"{$chargeable.label}" {$chargeable.amount|escape|money_currency}{if $chargeable.target_type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TARGET_TYPE && $chargeable->id_item} de "{$chargeable->getItem_label()}"{/if}
							{if $chargeable->type === Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE}- Gratuit{/if}
						{/if}
					</legend>
					<dl>
						{if $plugin->config->accounting && $chargeable->type !== Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE}
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:$ca_type:$chart_id name="chargeable_credit[%d]"|args:$chargeable.id label="Type de recette" required=1 default=$default_credit_account can_delete=true}
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:$da_type:$chart_id name="chargeable_debit[%d]"|args:$chargeable.id label="Compte d'encaissement" required=1 default=$default_debit_account can_delete=true}
						{/if}

						{input type="select" name="id_category[%d]"|args:$chargeable.id label="Inscrire comme membre dans la catégorie" data-chargeable-id=$chargeable.id default=$chargeable.id_category options=$category_options required=true help="Inscrira automatiquement la personne comme membre Paheko si cet article est commandé."}

						<div class="service_fee_registration" id={"service_fee_registration_%d"|args:$chargeable.id} data-chargeable-id="{$chargeable.id|intval}">
							<?php
							$fee = $chargeable->fee();
							$default = $fee ? [ (int)$fee->id => ($chargeable->service()->label . ' - ' . $fee->label) ] : null;
							?>
							{input type="list" target="_fee_selector.php" name="id_fee[%d]"|args:$chargeable.id label="Inscrire à l'activité" required=false default=$default can_delete=true help="Les comptes ci-dessus prévalent sur ceux du tarif de l'activité sélectionnée."}
						</div>

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
{/if}

{if $forms}

	<p class="alert block">
		Pour pouvoir synchroniser les membres, merci de renseigner les correspondances entre les champs HelloAsso et Paheko :
	</p>

	{foreach from=$forms item='form'}
		<fieldset>
			<legend>{$form->label}</legend>
			<dl>
				{foreach from=$form->customFields() item='field'}
					{input type="select" name="custom_fields[%d][%d]"|args:$form->id:$field->id label=$field->name options=$dynamic_fields required=true default=$field->id_dynamic_field}
				{/foreach}
			</dl>
		</fieldset>
	{/foreach}

{/if}

{if ($chargeables || $forms)}
	{csrf_field key=$csrf_key}
	{button type="submit" name="chargeable_config_submit" label="Finaliser la synchronisation" shape="right" class="main"}
</form>
{/if}


{if $sync->getDate()}
	{if $sync->isCompleted()}
		<p class="help">
			La dernière synchronisation date du {$sync->getDate()|date}.
		</p>
	{/if}
{else}
	<p class="alert block">Cliquer sur le bouton ci-dessous pour récupérer les données depuis HelloAsso.</p>
{/if}

{if !$sync && $sync > (new \DateTime('1 hour ago'))}
	<p class="alert block">Il n'est pas possible d'effectuer plus d'une synchronisation manuelle par heure.</p>
{else}
	<form method="post" action="{$self_url_no_qs}" class="sync">
		<p class="submit">
			{csrf_field key=$csrf_key}
			{if $chargeables || $forms}
				ou bien
				{button type="submit" name="sync" value=1 label="Synchroniser uniquement les anciennes données"}
			{else}
				{if $sync->getDate() && !$sync->isCompleted()}{assign var='label' value='Reprendre la synchronisation'}{else}{assign var='label' value='Synchroniser les données'}{/if}
				{button type="submit" name="sync" value=1 label=$label shape="right" class="main"}
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

(function () {
	$('form.sync').forEach((form) => {
		form.addEventListener('submit', (e) => {
			if (e.defaultPrevented) return;
			form.classList.add('progressing');
		});
	});
})();
{/literal}
</script>

{include file="_foot.tpl"}
