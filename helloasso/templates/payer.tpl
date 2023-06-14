{include file="_head.tpl" title="Payeur/euse·s — %s"|args:$payer.nom}

{include file="./_menu.tpl" current="payers" show_export=true}

<dl class="describe">
	{if $payer->exists()} {* The payer "exists" (on the database) only if registered as Paheko member *}
		<dt>Numéro</dt>
		<dd class="num"><a href="{$admin_url}users/details.php?id={$payer.id|intval}">{$payer.numero}</a></dd>
	{/if}
	<dt>Nom</dt>
	<dd>{$payer.nom}</dd>
	<dt>Courriel</dt>
	<dd>{$payer.email}</dd>
</dl>

<h2 class="ruler">Commandes</h2>

{include file='./_order_list.tpl' list=$orders}




{* Not yet supported
{pagination url=$list->paginationURL() page=$list.page bypage=$list.per_page total=$list->count()}
*}

{include file="_foot.tpl"}
