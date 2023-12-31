{include file="admin/_head.tpl" title="Statistiques" current="plugin_%s"|args:$plugin.id}

{include file="%s_nav.tpl"|args:$plugin_tpl current="stats"}

<figure>
    <img src="?graph=years" alt="" />
</figure>

<figure>
    <img src="?graph=exit" alt="" />
</figure>

<figure>
    <img src="?graph=entry" alt="" />
</figure>

<table class="list">
    <tbody>
        {foreach from=$stats_years item="row"}
        <tr>
            <th>{$row.year}</th>
            <td>{$row.type}</td>
            <td>{$row.details}</td>
            <td>{$row.nb}</td>
        </tr>
        {/foreach}
    </tbody>
</table>

<table class="list">
    <tbody>
        {foreach from=$stats_months item="row"}
        <tr>
            <th>{$row.month}</th>
            <td>{$row.type}</td>
            <td>{$row.details}</td>
            <td>{$row.nb}</td>
        </tr>
        {/foreach}
    </tbody>
</table>

{include file="admin/_foot.tpl"}