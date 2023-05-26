
{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}

		<tr>
			<td class="num"><a href="{$admin_url}acc/transactions/details.php?id={$row.id_transaction}">{$row.id_transaction}</a></td>
			<td class="money">{$row.amount|money_currency|raw}</td>
			<td>{$row.label}</td>
			<td>{$row.options|escape|nl2br}</td>
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
		</tr>



	{/foreach}

	</tbody>
</table>
