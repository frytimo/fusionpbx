<div class='card'>
<table class='list'>
<tr class='list-header'>
	{$th_feature_code}
	{$th_feature_name}
	<th class='hide-sm-dn'>{$text['label-description']}</th>
	{if $has_feature_codes_raw}
	<th class='hide-sm-dn'>{$text['label-raw_dialplan']}</th>
	{/if}
</tr>

{foreach from=$features item=row}
<tr class='list-row'>
	<td>{$row._feature_code|escape}</td>
	<td>{$row._feature_name|escape}</td>
	<td class='description hide-sm-dn'>{$row._feature_description|escape}</td>
	{if $has_feature_codes_raw}
	<td class='description hide-sm-dn'><code>{$row._raw_display|escape}</code></td>
	{/if}
</tr>
{foreachelse}
<tr class='list-row'>
	<td colspan='{if $has_feature_codes_raw}4{else}3{/if}' style='text-align: center;'>{$text['label-no_features']}</td>
</tr>
{/foreach}

</table>
</div>
