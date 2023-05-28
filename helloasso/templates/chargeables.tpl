{include file="_head.tpl" title="%s — %s"|args:$form.org_name,$form.name}

{include file="./_menu.tpl" current="home" current_sub="items" show_export=true}

{include file="./_chargeables_list.tpl" details=true}

<p class="help block">Les articles (tarifs et options) configurés dans l'administration de HelloAsso ne peuvent apparaître ici qu'uniquement <em>après</em> avoir été commandés au moins une fois.</p>

{*
{pagination url=$list->paginationURL() page=$list.page bypage=$list.per_page total=$list->count()}
*}

{include file="_foot.tpl"}
