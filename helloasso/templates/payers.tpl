{include file="_head.tpl" title="Liste des payeur/euse·s"}

{include file="./_menu.tpl" current="payers" show_export=true}

{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}

		<tr>
			<td class="num">{if $row.id}<a href="{$admin_url}users/details.php?id={$row.id|intval}">{$row.number}</a>{else}-{/if}</td>
			<td>{if $row.id}<a href="{$admin_url}users/details.php?id={$row.id|intval}">{$row.name}</a>{else}{$row.name}{/if}</td>
			<td>{$row.email}</td>
			<td class="actions">
				{if $row.id}
					{linkbutton href="payer.php?id=%d"|args:$row.id shape="help" label="Détails"}
				{elseif $plugin.config.user_match_type === Plugin\HelloAsso\Users::USER_MATCH_EMAIL}
					{linkbutton href="payer.php?email=%s"|args:$row.ref shape="help" label="Détails"}
				{else}
					{linkbutton href="payer.php?first_name=%s&last_name=%s"|args:$row.first_name:$row.last_name shape="help" label="Détails"}
				{/if}
			</td>
		</tr>

	{/foreach}

	</tbody>
</table>

{$list->getHTMLPagination()|raw}

{include file="_foot.tpl"}
