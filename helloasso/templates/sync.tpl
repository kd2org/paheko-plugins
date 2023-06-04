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
		<p class="error block">{$e->getMessage()|escape|nl2br}</p>
	{/foreach}
{/if}

{if $plugin->config->accounting}
	{if $chargeables}
	<form method="POST" action="{$self_url_no_qs}">
		<p class="alert block">
			{if !$default_debit_account && !$default_credit_account}
				Pour pouvoir synchroniser la comptabilité, merci de renseigner les types de recette et comptes d'encaissement pour les articles suivants :
			{else}
				Pour pouvoir synchroniser la comptabilité, merci de confirmer les types de recette et comptes d'encaissement pré-remplis pour les articles suivants :
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
								{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TYPE}Option {/if}"{$chargeable.label}" {$chargeable.amount|escape|money_currency}{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TYPE} de "{$chargeable->getItem_name()}"{/if}
							{/if}
						</legend>
						<dl>
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'6':$chart_id name="chargeable_credit[%d]"|args:$chargeable.id label="Type de recette" required=1 default=$default_credit_account}
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'1:2:3':$chart_id name="chargeable_debit[%d]"|args:$chargeable.id label="Compte d'encaissement" required=1 default=$default_debit_account}
							{input type="checkbox" name="register_user[%d]"|args:$chargeable.id value="1" label="Inscrire comme membre" source=$chargeable help="Inscrira automatiquement la personne comme membre Paheko si cet article est commandé."}
						</dl>
					{if $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
						</fieldset>
					{/if}
				{/foreach}
			</fieldset>
		{/foreach}
		{if $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
			<p class="help block">Vous pouvez définir/changer les valeurs de pré-remplissage depuis <a href="{$plugin_admin_url}config_client.php">la configuration de l'extension</a>.</p>
		{/if}
		{button type="submit" name="accounts_submit" label="Finaliser la synchronisation" shape="right" class="main"}
	</form>
	{/if}
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
	<form method="post" action="{$self_url}">
		<p class="submit">
			{csrf_field key=$csrf_key}
			{if $plugin->config->accounting && $chargeables}
				ou bien
				{button type="submit" name="sync" value=1 label="Synchroniser uniquement les anciennes données"}
			{else}
				{button type="submit" name="sync" value=1 label="Synchroniser les données" shape="right" class="main"}
			{/if}
		</p>
	</form>
{/if}

{include file="_foot.tpl"}
