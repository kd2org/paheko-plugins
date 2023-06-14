{if !$dialog}

<nav class="tabs">
	<ul>
		<li{if $current == 'home'} class="current"{/if}><a href="./">Formulaires</a></li>
		<li{if $current == 'payers'} class="current"{/if}><a href="payers.php">Payeur/euse·s</a></li>
		{*<li{if $current == 'targets'} class="current"{/if}><a href="targets.php">Synchronisation</a></li>*}
		{if $session->canAccess($session::SECTION_CONFIG, $session::ACCESS_ADMIN)}
			{*<li{if $current == 'config'} class="current"{/if}><a href="config.php">Configuration</a></li>*}
			<li{if $current == 'config_client'} class="current"{/if}><a href="config.php">Connexion à HelloAsso</a></li>
		{/if}
		<li{if $current == 'sync'} class="current"{/if}><a href="sync.php">Synchronisation</a></li>
	</ul>

	{if !empty($form.name)}
		{if !empty($show_export)}
		<aside>
			{linkbutton href="%s&export=csv"|args:$self_url shape="export" label="Export CSV"}
			{linkbutton href="%s&export=ods"|args:$self_url shape="export" label="Export tableur"}
		</aside>
		{/if}

	<ul class="sub">
		<li class="title">{$form.name}</li>
		<li{if $current_sub == 'orders'} class="current"{/if}>{link href="orders.php?id=%d"|args:$form.id label="Commandes"}</li>
		<li{if $current_sub == 'payments'} class="current"{/if}>{link href="payments.php?id=%d"|args:$form.id label="Paiements"}</li>
		<li{if $current_sub == 'chargeables'} class="current"{/if}>{link href="chargeables.php?id=%d"|args:$form.id label="Articles"}</li>
	</ul>
	{/if}
</nav>

{/if}

{if !empty($restricted)}
	<p class="alert block">Cette version est limitée, et ne pourra importer que les 5 premiers résultats depuis HelloAsso.<br />Merci de réaliser une contribution à Garradin pour débloquer l'extension et participer au financement du projet :)</p>
{/if}
