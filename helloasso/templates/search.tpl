{include file="_head.tpl" title="HelloAsso"}

{include file="./_menu.tpl" current=null current_sub=null}

<h2 class="ruler">Résultat(s) de la recherche "{$_GET.q}"</h2>

<?php
$target = [
	Plugin\HelloAsso\SearchResults::FORM_TYPE => 'orders.php?id=%d',
	Plugin\HelloAsso\SearchResults::ORDER_TYPE => 'order.php?id=%d',
	Plugin\HelloAsso\SearchResults::PAYMENT_TYPE => 'payment.php?id=%d',
	Plugin\HelloAsso\SearchResults::CHARGEABLE_TYPE => 'chargeable.php?id=%d',
	Plugin\HelloAsso\SearchResults::USER_TYPE => $admin_url . 'users/details.php?id=%d'
];
?>

{assign var='found' value=false}
{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}

		{assign var='found' value=true}
		<tr>
			<td>{$row.type_label}</td>
			<td class="num"><a href="{$target[$row.type]|args:$row.id}">{$row.id}</a></td>
			<td>{$row.label}</td>
			<td>{if $row->date}{$row.date|date}{else}-{/if}</td>
			<td class="num">
				{if $row.person}
					{$row.person}{if $row.id_user} <a href="{$admin_url}users/details.php?id={$row.id_user}">{$row.user_number}</a>{/if}
				{else}
				-
				{/if}
			</td>
			<td class="actions">
				{if $row.id_user}
					{linkbutton href="payer.php?id=%d"|args:$row.id_user shape="help" label="Voir les commandes"}
				{elseif $row.type === Plugin\HelloAsso\SearchResults::USER_TYPE}
					{linkbutton href="payer.php?id=%d"|args:$row.id shape="help" label="Voir les commandes"}
				{elseif $row.type === Plugin\HelloAsso\SearchResults::FORM_TYPE}
					{linkbutton href="orders.php?id=%d"|args:$row.id shape="help" label="Voir les commandes"}
				{/if}
			</td>
		</tr>

	{/foreach}

	</tbody>
</table>

{if !$found}
	<p>Aucun résultat trouvé.</p>
{/if}


{include file="_foot.tpl"}
