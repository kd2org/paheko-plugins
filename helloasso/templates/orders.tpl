{include file="_head.tpl" title="%s — %s"|args:$form.org_name,$form.label}

{include file="./_menu.tpl" current="home" current_sub="orders" show_export=true}

{if $_GET.ok}
	<p class="confirm block">
		Correspondances mises à jour.
	</p>
{/if}

{include file="common/dynamic_list_head.tpl"}

	{foreach from=$list->iterate() item="row"}

		<tr>
			<th class="num"><a href="order.php?id={$row.id}">{$row.id}</a></th>
			<td>{$row.date|date}</td>
			<td>{$row.label}</td>
			<td class="money">{$row.amount|money_currency|raw}</td>
			<td>
				{if $row.id_payer && $row.payer}
					<a href="{$admin_url}users/details.php?id={$row.payer.id|intval}">{$row.payer.nom}</a>
				{else}
					{$row.payer_name}
				{/if}
			</td>
			<td>{$row.status}</td>
			<td class="num"><a href="{$plugin_admin_url}payment.php?ref={$row.id_payment}">{$row.id_payment}</a></td>
			<td class="actions">
				{linkbutton href="order.php?id=%s"|args:$row.id shape="help" label="Détails"}
			</td>
		</tr>

	{/foreach}

	</tbody>
</table>

{$list->getHTMLPagination()|raw}

{if $session->canAccess($session::SECTION_ACCOUNTING, $session::ACCESS_ADMIN) && $form->customFields()}
	{if $form->need_config}
		<p class="alert block">
			Une configuration des correspondances suivantes est requise pour la synchronisation des membres.
		</p>
	{/if}

	<form method="POST" action="{$self_url}">
		<fieldset>
			<legend>Correspondance des champs HelloAsso</legend>
			<p class="help block">Fait le lien entre les données saisies sur le formulaire HelloAsso et les champs membres de Paheko.</p>
			<dl>
				{foreach from=$form->customFields() item='field'}
					{input type="select" name="custom_fields[%d]"|args:$field->id label=$field->name options=$dynamic_fields required=true default=$field->id_dynamic_field}
				{/foreach}
			</dl>
		</fieldset>
		{csrf_field key=$csrf_key}
		{button type="submit" name="custom_fields_config" label="Enregistrer" class="main"}
	</form>
{/if}

{include file="_foot.tpl"}
