{include file="common/dynamic_list_head.tpl"}

	<tbody>

	{foreach from=$list->iterate() item="row"}
		<tr>
			<td class="num"><a href="order.php?id={$row.id|intval}">{$row.id}</a></td>
			<td>{$row.date|date}</td>
			{if !isset($chargeable)}
				<td>{$row.form_name}</td>
			{/if}
			<td>{$row.label}</td>
			<td class="money">{$row.amount|money_currency|raw}</td>
			{if isset($chargeable)}
				<td>{$row.person}</td>
			{/if}
			<td>{$row.status}</td>
			<td class="num"><a href="{$plugin_admin_url}payment.php?ref={$row.id_payment|intval}">{$row.id_payment}</a></td>
			<td class="actions">{linkbutton href="%sorder.php?id=%s"|args:$plugin_admin_url:$row.id shape="help" label="Détails"}</td>
		</tr>
	{/foreach}

	</tbody>
</table>

{$list->getHTMLPagination()|raw}
