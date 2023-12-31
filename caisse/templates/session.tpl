{include file="admin/_head.tpl" current="plugin_%s"|args:$plugin.id}

<nav class="tabs">
	{if !$pos_session.closed}
		{linkbutton href="tab.php?session=%d"|args:$pos_session.id label="Retour à l'encaissement" shape="left"}
		{linkbutton href="session_close.php?id=%d"|args:$pos_session.id label="Clôturer la caisse" shape="delete"}
	{else}
		{linkbutton href="./" label="Retour" shape="left"}
		{linkbutton href="%s&pdf=1"|args:$self_url label="Télécharger en PDF" shape="print"}
	{/if}

	{if !$_GET.details}
		{linkbutton href="%s&details=1"|args:$self_url label="Afficher les détails des notes" shape="eye"}
	{else}
		{linkbutton href="%s?id=%d"|args:$self_url_no_qs,$pos_session.id label="Cacher les détails des notes" shape="eye-off"}
	{/if}
</nav>

{include file="%s/templates/session_export.tpl"|args:$plugin_root}

{include file="admin/_foot.tpl"}