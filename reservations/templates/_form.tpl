{if $booking}

	<form method="post" action="{$self_url}">
		<fieldset class="mine">
			<legend>Ma réservation</legend>
			<dl>
				<dt>Vous avez réservé le créneau suivant&nbsp;:</dt>
				<dd class="date">{$booking.date|strftime:"%A %e %B %Y à %H:%M"}</dd>
				{if $booking.nom_categorie}<dd>Créneau : {$booking.nom_categorie}</dd>{/if}
				{if $booking.numero}<dd>Numéro de membre : {$booking.numero}</dd>{/if}
				{if $booking.nom}<dd>Nom : {$booking.nom}</dd>{/if}
				{if $booking.champ}<dd>{$cat.champ.title} : {$booking.champ}</dd>{/if}
				<dd><input type="submit" name="cancel" value="Annuler ma réservation" /></dd>
			</dl>
		</fieldset>
	</form>

{else}

	{if isset($categories)}

		<section class="booking_categories">
			{if isset($categories)}<h3>Merci de sélectionner un type de créneau&nbsp;:</h3>{/if}

			{foreach from=$categories item="cat"}
			<article>
				<h2><a href="?cat={$cat.id}">{$cat.nom}</a></h2>
				{$cat.introduction|raw|format_skriv}
			</article>
			{/foreach}
		</section>

	{else}

		<form method="post" action="{$self_url}">

	{if empty($hide_description)}
		<article>
			{$cat.description|raw|format_skriv}
		</article>
	{/if}


	<fieldset>
		<legend>{if !empty($title)}{$title}{else}Créneaux disponibles{/if}</legend>
		{if !count($slots)}
			<p class="alert">Aucun créneau disponible.</p>
		{else}
			<dl class="slots">
				{foreach from=$slots item="slot"}
					{if $slot.date_change}
						<dt>{$slot.timestamp|strftime:"%A %e %B %Y"}</dt>
					{/if}
					<dd class="hour available_{$slot.available}">
						{if $slot.available && !$booking && $slot.bookable}
							<input type="radio" class="n-radio" name="slot" value="{$slot.id}={$slot.date}" id="f_{$slot.id}" /> <label for="f_{$slot.id}">{$slot.heure}</label>
						{else}
							{$slot.heure}
						{/if}
					</dd>
					<dd class="spots available_{$slot.available}">
						<em>{$slot.available} places disponibles</em>
					</dd>
				{/foreach}
			</dl>

			{if !$booking}
			<dl class="info">
				{if !empty($ask_name)}
					<dt><label for="f_nom">Prénom et nom</label> <b>obligatoire</b></dt>
					<dd><input type="text" name="nom" id="f_nom" placeholder="Prénom et nom" /></dd>
				{/if}
				{if !empty($cat->champ->type)}
					{*html_champ_membre config=$cat.champ name="champ"*}
				{/if}
			</dl>
			{/if}

			{if !$booking}
			<p class="submit">
				<input type="submit" name="book" value="Confirmer la réservation" />
			</p>
			{/if}
		{/if}
	</fieldset>

	</form>

	{/if}
{/if}