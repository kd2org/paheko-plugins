{include file="_head.tpl" title="HelloAsso"}

{include file="%s/templates/_menu.tpl"|args:$plugin_root current="sync"}

{if $_GET.ok}
	{if $_GET.ok == 1}
		<p class="confirm block">Synchronisation effectuée avec succès.</p>
	{/if}
{/if}

{if $last_sync}
	<p class="help">
		La dernière synchronisation date du {$last_sync|date}.
	</p>
{else}
	<p class="alert block">Cliquer sur le bouton ci-dessous pour récupérer les données depuis HelloAsso.</p>
{/if}

{if !$last_sync && $last_sync > (new \DateTime('1 hour ago'))}
	<p class="alert block">Il n'est pas possible d'effectuer plus d'une synchronisation manuelle par heure.</p>
{else}
	<form method="post" action="{$self_url}">
		<p class="submit">
			{csrf_field key=$csrf_key}
			{if $plugin->config->accounting && $forms}
				{button type="submit" name="sync" value=1 label="Synchroniser les anciennes données uniquement"}
			{else}
				{button type="submit" name="sync" value=1 label="Synchroniser les données" shape="right" class="main"}
			{/if}
		</p>
	</form>
{/if}

{if $plugin->config->accounting && $forms}
	<form method="POST" action="{$self_url}">
		<p class="alert block">Les types de recette et comptes d'encaissement doivent être renseignés pour formulaires suivants :</p>
		{foreach from=$forms item='form'}
			<fieldset>
				<legend>{$form.name}</legend>
					<dl>
						{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'6':$chart_id name="credit[%d]"|args:$form.id label="Type de recette" required=1}
						{input type="list" target="!acc/charts/accounts/selector.php?targets=%s&chart=%d"|args:'1:2:3':$chart_id name="debit[%d]"|args:$form.id label="Compte d'encaissement" required=1}
					</dl>
			</fieldset>
		{/foreach}
		{button type="submit" name="form_submit" label="Enregistrer et lancer la synchronisation" shape="right" class="main"}
	</form>
{/if}

{include file="_foot.tpl"}
