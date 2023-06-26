{include file="_head.tpl" title="Configuration — %s"|args:$plugin.label}

{include file="./_menu.tpl" current="config"}

{form_errors}

{if isset($_GET['ok']) && !$form->hasErrors()}
	<p class="confirm block">
		La configuration a bien été enregistrée.
	</p>
{/if}

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

<form method="post" action="{$self_url}">

	<fieldset>
		<legend>Ajouter un type de créneau</legend>
		<p class="help">
			Si vous ne définissez qu'un seul type de créneaux, la personne faisant une réservation ne verra que le texte de présentation et les créneaux associés.
		</p>
		<p class="help">
			Si vous définissez plusieurs types de créneaux, elle devra d'abord choisir un type de créneau, et se verra présenter la liste des types de créneaux avec leurs noms et textes d'introduction.
		</p>
		<dl>
			{input type="text" name="nom" required=true label="Nom"}
		</dl>
		<p class="submit">
			{csrf_field key="config_plugin_%s"|args:$plugin.name}
			<input type="submit" name="add" value="Ajouter" />
		</p>
	</fieldset>

</form>



<div class="help">
	<h3>Aide</h3>
	<p class="help">Les membres connectés peuvent réserver un créneau via l'onglet « Réservations » du menu de gauche.</p>
	<p class="help">Les non-membres peuvent réserver un créneau via l'adresse suivante :<br />
		{link href=$plugin_url label=$plugin_url target="_blank"}</p>
	<p class="help">Les administrateurs peuvent visionner les réservations et gérer les inscrit⋅e⋅s dans l'onglet « Réservations ».</p>
</div>

{include file="_foot.tpl"}
