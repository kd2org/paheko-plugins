{include file="_head.tpl"}

<nav class="tabs">
	{if !$pos_session.closed}
		{linkbutton href="tab.php?session=%d"|args:$pos_session.id label="Retour à l'encaissement" shape="left"}
		{linkbutton href="session_close.php?id=%d"|args:$pos_session.id label="Clôturer la caisse" shape="delete"}
	{else}
		{linkbutton href="./" label="Retour" shape="left"}
		{linkbutton href="%s&pdf=1"|args:$self_url label="Télécharger en PDF" shape="print"}
		{if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_ADMIN)}
			{linkbutton href="session_delete.php?id=%d"|args:$pos_session.id label="Supprimer cette session de caisse" shape="delete"}
		{/if}
	{/if}

	{if !$_GET.details}
		{linkbutton href="%s&details=1"|args:$self_url label="Afficher les détails des notes" shape="eye"}
	{else}
		{linkbutton href="%s?id=%d"|args:$self_url_no_qs,$pos_session.id label="Cacher les détails des notes" shape="eye-off"}
	{/if}

</nav>

{include file="./session_export.tpl"}

{include file="_foot.tpl"}