{include file="_head.tpl" title="%s — %s"|args:$form.org_name,$form.name plugin_css=['style.css']}

{include file="./_menu.tpl" current="home" current_sub="chargeables" show_export=true}

{include file="./_chargeables_list.tpl" details=true}

{*
{pagination url=$list->paginationURL() page=$list.page bypage=$list.per_page total=$list->count()}
*}

{include file="_foot.tpl"}
