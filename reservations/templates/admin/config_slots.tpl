{include file="_head.tpl" title="Configuration — %s"|args:$plugin.label}

{include file="./_menu.tpl" current="config"}

{form_errors}

{if $ok}
	<p class="confirm block">
		La configuration a bien été enregistrée.
	</p>
{/if}

<form method="post" action="{$self_url}">
	<h2 class="ruler">{$cat.nom}</h2>

	<fieldset>
		<legend>Créneaux</legend>
		<table class="list">
			<thead>
				<tr>
					<th>À partir du</th>
					<td>Heure</td>
					<td>Jauge maximale</td>
					<td></td>
					<td></td>
				</tr>
			</thead>
			<tbody>
				{foreach from=$slots item="slot"}
				<tr>
					<th>{input type="date" name="slot[%s][jour]"|args:$slot.id default=$slot.jour required=1}</th>
					<td><input type="text" pattern="\d\d:\d\d" size="5" name="slot[{$slot.id}][heure]" value="{$slot.heure}" placeholder="HH:MM" required="required" /></td>
					<td><input type="number" name="slot[{$slot.id}][maximum]" value="{$slot.maximum}" required="required" /> personnes</td>
					<td><label>{input type="checkbox" name="slot[%s][repetition]"|args:$slot.id value="1" default=$slot.repetition} Répétition hebdomadaire</label></td>
					<td class="actions">
						{button onclick="return removeRow(this);" shape="minus" label="Supprimer cette ligne"}
					</td>
				</tr>
				{/foreach}
			</tbody>
		</table>
		<p class="actions">
			{button onclick="return addRow(this);" label="Ajouter un créneau" shape="plus"}
		</p>
	</fieldset>

	<p class="submit">
		{csrf_field key=$csrf_key}
		{button type="submit" name="save" label="Enregistrer" shape="right" class="main"}
	</p>

</form>


<script type="text/javascript">
{literal}
var index = 0;
function removeRow(e) {
	var row = e.parentNode.parentNode;
	var table = row.parentNode.parentNode;

	if (table.rows.length <= 2)
	{
		return false;
	}

	row.parentNode.removeChild(row);
	return false;
}
function addRow(e) {
	var table = e.parentNode.parentNode.querySelector('table');
	if (table.rows.length == 1) {
		alert("Merci d'enregistrer la page pour pouvoir ajouter une ligne.");
		return false;
	}

	var row = table.rows[table.rows.length-1];
	var new_row = row.cloneNode(true);
	row.parentNode.appendChild(new_row);

	index++;

	new_row.querySelectorAll('input').forEach(function (elm) {
		if (elm.classList.contains('date')) {
			elm.onchange = function ()
			{
				if (this.value.match(/\d{2}\/\d{2}\/\d{4}/))
					this.nextSibling.value = this.value.split('/').reverse().join('-');
				else
					this.nextSibling.value = this.value;
			};

			new datepickr(elm, config_fr);
		}
		elm.name = elm.name.replace(/slot\[_?\d+\]/, 'slot[_' + index + ']');
	});

	return false;
}

var config_fr = {
	fullCurrentMonth: true,
	dateFormat: 'd/m/Y',
	firstDayOfWeek: 0,
	weekdays: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
	months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
	suffix: { 1: 'er' },
	defaultSuffix: ''
};

document.getElementById('copyBtn').onclick = function (e) {
	var input = e.target.parentNode.querySelector('input');
	input.select();
	input.focus();
	input.setSelectionRange(0, 99999);
	document.execCommand("copy");
}
{/literal}
</script>

{include file="_foot.tpl"}
