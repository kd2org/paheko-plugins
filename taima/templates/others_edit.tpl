{if $entry->exists()}
	{assign var="title" value="Modifier une tâche"}
{else}
	{assign var="title" value="Ajouter une tâche"}
{/if}
{include file="_head.tpl" title=$title}

{if isset($_GET.ok)}
	<p class="confirm block">Tâche enregistrée.</p>
{/if}

{form_errors}

<form method="post" action="" data-focus="1">
	<fieldset>
		<legend>{$title}</legend>
		<dl>
			{input type="date" label="Date" required=true name="date" default=$now source=$entry}
			{input type="list" name="user" required=false label="Membres" help="Laisser vide pour une tâche bénévole qui n'est pas liée à un membre en particulier" multiple=false target="!users/selector.php" default=$selected_user}
			{input type="select" options=$tasks name="task_id" label="Tâche" source=$entry}
			{input type="text" name="duration" placeholder="0:30" pattern="\d+[:h]\d+|\d+([.,]\d+)?" help="Formats acceptés : 1h30, 1:30, 1.5 ou 1,5." label="Durée" size="5" required=true default=$entry_duration}
			{input type="textarea" name="notes" label="Notes" source=$entry}
		</dl>
	</fieldset>
	<p class="submit">
		{csrf_field key=$csrf_key}
		{button type="submit" name="save" label="Enregistrer" class="main" shape="right"}
	</p>
</form>

{include file="_foot.tpl"}