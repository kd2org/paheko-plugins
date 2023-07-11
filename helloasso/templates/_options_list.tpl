
{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}

		<tr>
			<td class="num"><a href="chargeable.php?id={$row.id_chargeable}">{$row.id_chargeable}</a></td>
			<td class="num"><a href="{$admin_url}acc/transactions/details.php?id={$row.id_transaction}">{$row.id_transaction}</a></td>
			<td class="money">{if $row.price_type === Plugin\HelloAsso\Entities\Item::FREE_PRICE_TYPE}Gratuit{else}{$row.amount|money_currency|raw}{/if}</td>
			<td>{$row.label}</td>
			<td class="num">{if $row.id_user}{$row.user_name} <a href="{$admin_url}users/details.php?id={$row.id_user}">{$row.user_numero}</a>{/if}</td>
			<td>
			{if property_exists($row, 'custom_fields')}
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
			{/if}
			</td>
			<td>{if $row.service}{$row.service}{else}-{/if}</td>
			
		</tr>

	{/foreach}

	</tbody>
</table>

{$count_opti->getHTMLPagination()|raw}
