{include file="_head.tpl" title="Configuration — %s"|args:$plugin.label}

{include file="./_menu.tpl" current="config"}

{form_errors}

<form method="post" action="{$self_url}">

	<fieldset>
		<legend>Modifier le type de créneau</legend>
		<dl>
            {input type="text" name="nom" label="Nom" required=true source=$cat}
            {input type="textarea" cols=70 rows=7 name="description" label="Description" required=false source=$cat help="Sera affiché sur la page de choix de type de créneau et sur la page d'inscription au créneau"}
			<dd class="help">Syntaxe MarkDown acceptée. {linkbutton shape="help" target="_dialog" href="!static/doc/markdown.html" label="Aide de la syntaxe MarkDown"}</dd>
		</dl>
	</fieldset>

	<fieldset>
		<legend>Information supplémentaire</legend>
		<dl>
            {input type="checkbox" name="has_field" value=1 default=$has_field label="Demander une information supplémentaire"}
			<dd class="help">Dans tous les cas le formulaire de réservation demande de renseigner un nom, mais il est possible de demander une information supplémentaire à la personne qui fait la réservation.</dd>
        </dl>
        <dl class="field">
            {input type="text" name="field[title]" label="Titre" default=$cat.champ.title required=true}
            {input type="text" name="field[help]" label="Aide" default=$cat.champ.help required=true help="Texte d'aide qui apparaîtra en dessous du champ à renseigner"}
            {input type="checkbox" name="field[mandatory]" default=$cat.champ.mandatory value=1 label="Obligatoire" help="Si coché, le champ ne pourra pas être laissé vide."}
		</dl>
	</fieldset>

	<p class="submit">
		{csrf_field key=$csrf_key}
        {button type="submit" name="save" label="Enregistrer" shape="right" class="main"}
	</p>

</form>

<form method="post" action="{$self_url}">
    <fieldset>
        <legend>Supprimer ce type de créneau</legend>
        <p class="alert block">Ceci effacera toutes les réservations liées.</p>
        <p class="submit">
            {csrf_field key="config_plugin_%s"|args:$plugin.name}
            <input type="submit" name="delete" value="Supprimer &rarr;" />
        </p>
    </fieldset>
</form>

<script type="text/javascript">
var checkField = () => g.toggle('.field', $('#f_has_field_1').checked);
checkField();
$('#f_has_field_1').onchange = checkField;
</script>

{include file="_foot.tpl"}
