
{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}

		<tr>
			<td class="num"><a href="#">{$row.id}</a></td>
			<td>{$row.type}</td>
			<td>{$row.label}</td>
			<td class="money">{$row.amount|money_currency|raw}</td>
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
			<td class="actions">
				{* {if $details}{linkbutton href=".php?id=%s"|args:$row.id_order shape="help" label="Détails"}{/if} *}
			</td>
		</tr>

	{/foreach}

	</tbody>
</table>
