{include file="admin/_head.tpl" title=$plugin.nom current="plugin_%s"|args:$plugin.id}

{include file="%s/templates/admin/_menu.tpl"|args:$plugin_root current="bookings"}

{if !$cat}
	<section class="booking_categories">
		{foreach from=$categories item="cat"}
		<article>
			<h2><a href="?cat={$cat.id}">{$cat.nom}</a></h2>
			{$cat.introduction|raw|format_skriv}
		</article>
		{/foreach}
	</section>

{else}
	<dl class="slots">
		{foreach from=$bookings item="booking"}
			{if $booking.date_change}
				<dt>
					{$booking.date|strftime:"%A %e %B %Y"}
				</dt>
			{/if}
			{if $booking.hour_change}
			<dd class="hour">
				<b>{$booking.date|strftime:"%H:%M"}</b>
			</dd>
			{/if}
			<dd class="spots">
				<span class="actions">
					<a href="?cat={$booking.categorie}&amp;delete={$booking.id}" title="Supprimer" class="icn" data-action="delete">✘</a>
				</span>
				<strong>{$booking.nom}</strong>
				{if $booking.champ}
					— {$booking.champ}
				{/if}
			</dd>
		{/foreach}
	</dl>

	<script type="text/javascript">
	{literal}
	document.querySelectorAll('a[data-action="delete"]').forEach(function (e) {
		e.onclick = function () { return confirm("Supprimer ?"); };
	});
	{/literal}
	</script>

	{include file="%s/templates/_form.tpl"|args:$plugin_root hide_description=true ask_name=true booking=null title="Réserver pour un adhérent"}
{/if}


{include file="admin/_foot.tpl"}
