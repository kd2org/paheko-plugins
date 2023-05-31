
{include file="common/dynamic_list_head.tpl"}

{assign var='chargeable_need_config' value=false}

	{foreach from=$list->iterate() item="row"}

		<tr {if $plugin.config.accounting && (!$row.id_credit_account || !$row.id_debit_account)}class="awaits_account_configuration"{/if}>
			<td class="num"><a href="{"chargeable.php?id=%s"|args:$row.id}">{$row.id}</a></td>
			<td>{$row.type_label}</td>
			<td>{$row.label}</td>
			<td class="money">{$row.amount|money_currency|raw}</td>
			<td>{$row.register_user}</td>
			<td class="num"><a href="{$admin_url}acc/accounts/journal.php?id={$row.id_credit_account|intval}">{$row.credit_account}</a></td>
			<td class="num"><a href="{$admin_url}acc/accounts/journal.php?id={$row.id_debit_account|intval}">{$row.debit_account}</a></td>
			
			{* Not yet supported
			{if property_exists($row, 'custom_fields')}
			<td>
				{if $row.custom_fields}
				<table>
					{foreach from=$row.custom_fields item="value" key="name"}
					<tr>
						<td>{$name}</td>
						<th>{$value}</th>
					</tr>
					{/foreach}
				</table>
				{/if}

			</td>
			{/if}
			*}
			
			{if $plugin.config.accounting}
				<td class="actions">
					{if !$row.id_credit_account || !$row.id_debit_account}
						{assign var='button_label' value='Configurer'}
						{assign var='button_shape' value='settings'}
						{assign var='chargeable_need_config' value=true}
					{else}
						{assign var='button_label' value='Modifier'}
						{assign var='button_shape' value='edit'}
					{/if}
					{linkbutton href="chargeable.php?id=%s"|args:$row.id shape=$button_shape label=$button_label}
				</td>
			{else}
				<td class="actions"></td>
			{/if}
		</tr>

	{/foreach}

	</tbody>
</table>

<p class="help block">Les articles (tarifs et options) configurés dans l'administration de HelloAsso ne peuvent apparaître ici qu'uniquement <em>après</em> avoir été commandés au moins une fois.</p>

{if $plugin.config.accounting && $chargeable_need_config}
	<p class="alert block">
		Les articles en rouge nécessitent une configuration de votre part.
		<br /><br />Vous pouvez soit les configurer un-à-un depuis leur bouton de configuration soit tous d'un coup depuis <a href="{$plugin_admin_url}sync.php">la page de synchronisation</a>.
	</p>
{/if}
