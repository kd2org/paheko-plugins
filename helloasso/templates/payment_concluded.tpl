<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" lang="fr" data-version="{$version_hash}" data-url="{$plugin_url}">
<head>
	<meta charset="utf-8" />
	<meta name="v" content="{$version_hash}" />
	<title>{$title}</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	{* <link rel="stylesheet" type="text/css" href="{$plugin_url}static/style.css?{$version_hash}" media="all" /> *}
</head>

<body>
	<main>
	<h1 class="ruler">Informations sur le paiement</h1>

	<p>
		{if $code === 'succeeded'}
			Votre paiement a été accepté.
		{elseif $code === 'refused'}
			Votre paiement a été refusé.
		{else}
			Votre paiement est en cours de traitement.
		{/if}
	</p>

	<h2 class="ruler">Récapitulatif</h2>
	<dl class="describe">
		<dt>Référence</dt>
		<dd>{$payment->reference}</dd>
		<dt>Montant</dt>
		<dd>{$payment->amount|money_currency|raw}</dd>
		<dt>Libellé</dt>
		<dd>{$payment->label}</dd>
		<dt>Moyen de paiement</dt>
		<dd>{$method}</dd>
		<dt>Type</dt>
		<dd>{$type}</dd>
	</dl>
	</main>

</body>
</html>
