{include file="_head.tpl" title=$plugin.label}

{include file="./_menu.tpl" current="index"}

{include file="../_form.tpl" ask_name=false}

<article class="wikiContent">
	{$config.text|raw|format_skriv}
</article>

{include file="_foot.tpl"}
