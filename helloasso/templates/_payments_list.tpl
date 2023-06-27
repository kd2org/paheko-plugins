{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}
		<tr>
			<td class="num"><a href="payment.php?id={$row.id}">{$row.reference}</a></td>
			<td class="num">
				{if $row.transactions}
					{foreach from=$row.transactions item="id_transaction"}
						{link href="!acc/transactions/details.php?id=%d"|args:$id_transaction label="#%d"|args:$id_transaction}
					{/foreach}
				{/if}
			</td>
			<td>{$row.label}</td>
			<td>{$row.date|date}</td>
			<td class="money">{$row.amount|money_currency|raw}</td>
			<td>
				{if $row.id_author && $row.author}
					<a href="{$admin_url}users/details.php?id={$row.author.id|intval}">{$row.author.nom}</a>
				{else}
					{$row.author_name}
				{/if}
			</td>
			<td>{$row.state}</td>
			<td>{$row.transfert_date|date}</td>
			<td class="num"><a href="{$plugin_admin_url}order.php?id={$row.id_order}">{$row.id_order}</a></td>
			<td class="actions">
				{if $row.receipt_url}
					{linkbutton href=$row.receipt_url target="_blank" shape="print" label="Attestation de paiement"}
				{/if}
				{if $details}{linkbutton href="payment.php?id=%s"|args:$row.id shape="help" label="Détails"}{/if}
			</td>
		</tr>
	{/foreach}

	</tbody>
</table>

{$list->getHTMLPagination()|raw}
