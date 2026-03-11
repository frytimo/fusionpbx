<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_call_recording_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($call_recordings)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_caller_id_name}
	{$th_caller_id_number}
	{$th_caller_destination}
	{$th_destination_number}
	{$th_call_recording_name}
	{if $has_call_recording_play || $has_call_recording_download}
	<th class='shrink center'>{$text['label-recording']}</th>
	{/if}
	{$th_call_recording_length}
	{$th_call_recording_date}
	{$th_call_direction}
	{if $has_xml_cdr_details}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$call_recordings item=row}
{$row._progress_bar_html}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_call_recording_delete}
	<td class='checkbox'>
		<input type='checkbox' name='call_recordings[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='call_recordings[{$row@index}][uuid]' value='{$row.call_recording_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_call_recording_all}
	<td class='overflow hide-sm-dn shrink'>{$row.domain_name|escape}</td>
	{/if}
	<td class='hide-sm-dn shrink'>{$row.caller_id_name|escape}</td>
	<td class='shrink'>{$row._caller_id_number_fmt}</td>
	<td class='hide-sm-dn shrink'>{$row._caller_destination_fmt}</td>
	<td class='shrink'>{$row._destination_number_fmt}</td>
	<td class='overflow hide-sm-dn nowrap'>{$row.call_recording_name|escape}</td>
	{if $has_call_recording_play || $has_call_recording_download}
	<td class='middle button center no-link no-wrap'>
		{$row._tools_html}
	</td>
	{/if}
	<td class='right hide-sm-dn shrink'>{$row._duration}</td>
	<td class='overflow center no-wrap'>{$row.call_recording_date_formatted|escape} <span class='hide-sm-dn'>{$row.call_recording_time_formatted|escape}</span></td>
	<td class='left hide-sm-dn shrink'>{$row._direction_label}</td>
	{if $has_xml_cdr_details}
	<td class='action-button'>
		{$row._cdr_button}
	</td>
	{/if}
</tr>
{/foreach}

</table>
</div>
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name}' value='{$token.hash}'>
</form>
