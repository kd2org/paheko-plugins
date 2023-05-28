{include file="_head.tpl" title="HelloAsso"}

{include file="%s/templates/_menu.tpl"|args:$plugin_root current="sync"}

{if $_GET.ok}
	{if $_GET.ok == 1 && (!$plugin->config->accounting || ($plugin->config->accounting && !$chargeables))}
		<p class="confirm block">Synchronisation effectuée avec succès.</p>
	{/if}
{/if}

{if $plugin->config->accounting}
	{if $chargeables}
	<form method="POST" action="{$self_url}">
		<p class="alert block">Pour pouvoir synchroniser la comptabilité, merci de renseigner les types de recette et comptes d'encaissement pour articles suivants :</p>
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
								{if $chargeable.type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TYPE}"{$chargeable->getItem_name()}" option {/if}"{$chargeable.label}" {$chargeable.amount|escape|money_currency}
							{/if}
						</legend>
						<dl>
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'6':$chart_id name="chargeable_credit[%d]"|args:$chargeable.id label="Type de recette" required=1}
							{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'1:2:3':$chart_id name="chargeable_debit[%d]"|args:$chargeable.id label="Compte d'encaissement" required=1}
						</dl>
					{if $chargeable.type !== Plugin\HelloAsso\Entities\Chargeable::ONLY_ONE_ITEM_FORM_TYPE}
						</fieldset>
					{/if}
				{/foreach}
			</fieldset>
		{/foreach}
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
