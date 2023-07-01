{include file="_head.tpl" title="Configuration — %s"|args:$plugin.label}

{if !$dialog}
	{include file="./_menu.tpl" current="config"}
{/if}

{form_errors}

{if isset($_GET['ok']) && !$form->hasErrors()}
	<p class="confirm block">
		La configuration a bien été enregistrée.
	</p>
{/if}

{if count($categories)}
<table class="list">
	<thead>
		<tr>
			<th>Nom</th>
			<td></td>
		</tr>
	</thead>
	<tbody>
		{foreach from=$categories item="cat"}
		<tr>
			<th>{$cat.nom}</th>
			<td class="actions">
				{linkbutton label="Modifier" href="config_cat.php?id=%d"|args:$cat.id shape="edit"}
				{linkbutton label="Configurer les créneaux" href="config_slots.php?id=%d"|args:$cat.id shape="menu"}
			</td>
		</tr>
		{/foreach}
	</tbody>
</table>
{else}
<p class="alert block">Il n'y a aucun créneau configuré.</p>
{/if}

<form method="post" action="{$self_url}">

	<fieldset>
		<legend>Ajouter un type de créneau</legend>
		<dl>
			{input type="text" name="nom" required=true label="Nom du créneau" help="Exemple : atelier vélo du mercredi."}
		</dl>
		<p class="submit">
			{csrf_field key=$csrf_key}
			{button type="submit" name="add" label="Ajouter" shape="right" class="main"}
		</p>
	</fieldset>

</form>

<div class="help block">
	<h4>Accès à la réservation</h4>
	<p>Les membres connectés pourront réserver un créneau via le menu « Réservations » à gauche.</p>
	<p>Les non-membres pourront réserver un créneau via l'adresse suivante :<br />
		{input copy=true name="url" type="url" readonly=true default=$plugin_url size=$plugin_url|strlen}
		{linkbutton href=$plugin_url label="Ouvrir" target="_blank" shape="eye"}
	</p>
	<p>Les gestionnaires pourront visionner les réservations et gérer les inscrit⋅e⋅s dans le menu « Réservations ».</p>
</div>

{include file="_foot.tpl"}
