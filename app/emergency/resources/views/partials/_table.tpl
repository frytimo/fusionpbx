<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{$th_domain_name}
	<th class='left'>{$text['label-emergency_date']}</th>
	<th class='left'>{$text['label-emergency_time']}</th>
	<th class='left'>{$text['label-emergency_extension']}</th>
	<th class='left'>{$text['label-emergency_event']}</th>
	{if $has_xml_cdr_recording && ($has_xml_cdr_recording_play || $has_xml_cdr_recording_download)}
	<th class='center'>{$text['label-recording']}</th>
	{/if}
	{if $has_xml_cdr_status || $has_xml_cdr_hangup_cause}
	<th class='left'>{$text['label-emergency_call_status']}</th>
	{/if}
</tr>

{foreach from=$emergency_logs item=row}
<tr class='list-row'>
	{if $show == 'all' && $has_emergency_logs_view_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>{$row.date_formatted|escape}</td>
	<td>{$row.time_formatted|escape}</td>
	<td>{$row.extension|escape}</td>
	<td>{$row.event|escape}</td>
	{if $has_xml_cdr_recording && ($has_xml_cdr_recording_play || $has_xml_cdr_recording_download)}
	<td class='middle button center no-link no-wrap'>
		{$row._recording_button}
		{$row._download_button}
	</td>
	{/if}
	{if $has_xml_cdr_status || $has_xml_cdr_hangup_cause}
	<td>{$row._status_link}</td>
	{/if}
</tr>
{/foreach}

</table>
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
</div>
