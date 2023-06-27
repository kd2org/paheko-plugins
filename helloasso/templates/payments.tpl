{include file="_head.tpl" title="%s — %s"|args:$form.org_name,$form.label}

{include file="./_menu.tpl" current="home" current_sub="payments" show_export=true}

{include file="./_payments_list.tpl" details=true}

{include file="_foot.tpl"}
