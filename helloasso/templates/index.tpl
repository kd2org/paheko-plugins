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
			<th><a href="orders.php?id={$form.id}">{$form.name}</a></th>
			<td>{$form.type_label}</td>
			<td>{$form.state_label}</td>
		</tr>
		{/foreach}
	</tbody>
</table>

{if isset($checkout)}
	<h2 class="ruler">Tunnel de paiement</h2>
	<p class="confirm block">Tunnel de paiement généré avec succès.</p>
	<dl class="describe">
		<dt>ID</dt>
		<dd>{$checkout->id}</dd>
		<dt>URL</dt>
		<dd><a href="{$checkout->url}">{$checkout->url}</a></dd>
	</dl>
	<p class="help block">Pour rappel, ce tunnel de paiement/lien n'est valide que 15 minutes.</p>
{else}
	<h2 class="ruler">Créer un tunnel de paiement</h2>

	<form method="POST" action="{$self_url}">
		<fieldset>
			<legend>Paiement</legend>
			<dl>
				{input type="select" name="org_slug" label="Association" options=$orgOptions required=true}
				{input type="text" name="label" label="Libellé" required=true}
				{input type="money" name="amount" label="Montant" required=true}
				{input type="list" name="user" label="Membre" target="!users/selector.php" can_delete="true" required=true}
			</dl>
		</fieldset>

		{**** ToDo: add csrf token ****}
		{button type="submit" name="generate_checkout" label="Créer" class="main"}
	</form>
{/if}

{include file="_foot.tpl"}
