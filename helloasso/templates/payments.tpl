{include file="_head.tpl" title="%s — %s"|args:$form.org_name,$form.label}

{include file="./_menu.tpl" current="home" current_sub="payments" show_export=true}

{include file="./_payments_list.tpl" details=true}

{*
{pagination url=$list->paginationURL() page=$list.page bypage=$list.per_page total=$list->count()}
*}

{include file="_foot.tpl"}
