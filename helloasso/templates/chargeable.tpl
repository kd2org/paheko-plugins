{include file="_head.tpl" title="Article HelloAsso n°%s : \"%s\""|args:$chargeable.id:$chargeable.label}

{include file="./_menu.tpl" current="home"}

{if array_key_exists('ok', $_GET)}
	<p class="confirm block">Configuration de l'article mise à jour avec succès.</p>
{/if}

<h2 class="ruler">Informations sur l'article</h2>

<dl class="describe">
	<dt>Label</dt>
	<dd>{$chargeable->label}</dd>
	<dt>Type</dt>
	<dd>
		{if $chargeable->id_item !== null}
			{assign var='types' value=Plugin\HelloAsso\Entities\Item::TYPES}
			{$types[$parent_item->type]}
			{if $chargeable->target_type === Plugin\HelloAsso\Entities\Chargeable::OPTION_TARGET_TYPE}- Option{/if}
		{else}
			{assign var='types' value=Plugin\HelloAsso\Entities\Form::TYPES}
			{$types[$form->type]}
		{/if}
	</dd>
	<dt>Montant</dt>
	<dd>{if null === $chargeable->amount}S'applique peu importe le montant.{elseif $chargeable->type === Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE}Gratuit{else}{$chargeable->amount|money_currency|raw}{/if}</dd>
	<dt>Formulaire</dt>
	<dd>{$form->label}</dd>
	<dt>Statut</dt>
	<dd>
		{if ($plugin->config->accounting && $chargeable->type !== Plugin\HelloAsso\Entities\Chargeable::FREE_TYPE && (!$chargeable->id_credit_account || !$chargeable->id_debit_account)) || ($chargeable->need_config === 1)}
			<em>En attente de configuration {if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE)}de votre part{else}par un·e administrateur/trice.{/if}</em>
			{if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE)}<br />{linkbutton href="chargeable.php?id=%s&config"|args:$chargeable.id shape='settings' label='Configurer'}{/if}
		{else}
			En fonctionnement.
		{/if}
	</dd>
	<dt>Synchro : Inscription Automatique</dt>
	<dd>
		{if $chargeable->id_category}
			{$category->name}
			{if $chargeable->service()}
				- {$chargeable->service()->label} ({$chargeable->fee()->label})
			{/if}
		{else}
			Aucune
		{/if}
	</dd>
	{if $plugin->config->accounting}
		<dt>Synchro : Type de recettes</dt>
		<dd>{if $credit_account}{$credit_account}{/if}</dd>
		<dt>Synchro : Compte d'encaissement</dt>
		<dd>{if $debit_account}{$debit_account}{/if}</dd>
	{/if}
</dl>
{if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_WRITE)}
	<p>
		{linkbutton href="chargeable.php?id=%s&config"|args:$chargeable.id shape='settings' label="Configurer la synchronisation de l'article"}
	</p>
{/if}
</section>

<h2 class="ruler">Commandes comprenant cet article</h2>

{include file='./_order_list.tpl' list=$orders}

{if $TECH_DETAILS}
	<dl style="background-color: black; color: limegreen; padding-top: 0.8em;" class="describe">
		<dt style="color: limegreen;">item</dt>
		<dd><pre>{if $chargeable->id_item}{$parent_item|var_dump}{else}NULL{/if}</pre></dd>
		<dt style="color: limegreen;">item->raw_data</dt>
		<dd><pre>{if $chargeable->id_item}{$parent_item->raw_data|json_revamp}{else}NULL{/if}</pre></dd>
	</dl>
{/if}

{include file="_foot.tpl"}
