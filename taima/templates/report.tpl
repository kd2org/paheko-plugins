{include file="admin/_head.tpl" title="Valorisation du temps bénévole" plugin_css=['style.css']}

{include file="%s/templates/_nav.tpl"|args:$plugin_root current="report"}

{form_errors}


{if isset($_GET.ok)}
	<p class="block confirm">
		L'écriture numéro {link href="!acc/transactions/details.php?id=%d"|args:$_GET.ok class="num" label="#%d"|args:$_GET.ok} a été ajoutée.
	</p>
{/if}

<form method="post" action="">

{if empty($year)}
	<fieldset>
		<legend>Exercice</legend>
		<dl>
			{input type="select" name="id_year" options=$years required=true label="Exercice où reporter la valorisation"}
		</dl>
	</fieldset>

	<p class="submit">
		{csrf_field key=$csrf_key}
		{button type="submit" name="next" label="Continuer" shape="right" class="main"}
	</p>
{else}
	<fieldset>
		<legend>Période à valoriser</legend>
		<dl>
			{input type="date" name="start" required=true label="Date de début" default=$year.start_date}
			{input type="date" name="end" required=true label="Date de fin" default=$year.end_date}
		</dl>
		<p class="submit">
			{csrf_field key=$csrf_key}
			{button type="submit" name="set" label="Modifier la période" shape="right"}
		</p>
	</fieldset>


	<table class="list auto">
		<thead>
			<tr>
				<th>Tâche</th>
				<td class="num">Heures</td>
				<td class="money">Valorisation</td>
				<td>Compte</td>
			</tr>
		</thead>
		<tbody>
		{foreach from=$report item="line"}
			<tr>
				<th>{$line.label}</th>
				<td class="num">{$line.hours}</td>
				<td class="money">{$line.total|raw|money_currency:true}</td>
				<td class="num">
					{if $line.id_account}
						{link href="!acc/accounts/journal.php?id=%d"|args:$line.id_account label=$line.account_code} {$line.account_label}
					{else}
						<strong>{$line.account_code}</strong> n'est pas dans ce plan comptable
					{/if}
				</td>
			</tr>
		{/foreach}
		</tbody>
	</table>

	<p class="submit">
		{input type="hidden" name="id_year" source=$_POST}
		{csrf_field key=$csrf_key}
		{button type="submit" name="save" label="Enregistrer cette valorisation dans l'exercice '%s'"|args:$year.label shape="right" class="main"}
	</p>
{/if}

</form>


{include file="admin/_foot.tpl"}