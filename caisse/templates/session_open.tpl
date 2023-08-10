{include file="_head.tpl" title="Ouverture de caisse"}

{if $current_pos_session}
<p class="alert block">
	Attention : il existe déjà une caisse ouverte en cours, voulez-vous vraiment ouvrir deux sessions de caisse en même temps&nbsp;?<br />
</p>
{/if}

{form_errors}

<form method="post" action="" data-focus="1">
	<fieldset>
		<legend>Ouvrir la caisse</legend>
		<dl>
			{input type="money" name="amount" label="Solde de la caisse à l'ouverture" help="Uniquement les espèces, ne pas compter les chèques" required=true}
			{if $plugin.config.allow_custom_user_name}
				{input type="text" name="user_name" label="Nom de la personne procédant à l'ouverture de la caisse" required=true default=$user_name}
			{/if}
		</dl>
	</fieldset>
	<p class="submit">
		{csrf_field key=$csrf_key}
		{linkbutton shape="left" href="./" label="Annuler"}
		{button type="submit" shape="right" label="Ouvrir la caisse" class="main" name="open"}
	</p>
</form>

{include file="_foot.tpl"}