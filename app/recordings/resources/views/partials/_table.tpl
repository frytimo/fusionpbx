<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_recording_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($recordings)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_recording_name}
	{$th_recording_filename}
	{if $has_recording_play || $has_recording_download}
	<th class='center shrink'>{$text['label-tools']}</th>
	{/if}
	<th class='center'>{if $recording_storage_type == 'base64'}{$text['label-size']}{else}{$text['label-file_size']}{/if}</th>
	<th class='center hide-md-dn'>{$text['label-date']}</th>
	{$th_recording_description}
	{if $has_recording_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$recordings item=row}
{$row._progress_bar_html}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_recording_delete}
	<td class='checkbox'>
		<input type='checkbox' name='recordings[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='recordings[{$row@index}][uuid]' value='{$row.recording_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_recording_all}
	<td>{$row._domain_label|escape}</td>
	{/if}
	<td>
		{if $has_recording_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.recording_name|escape}</a>
		{else}
		{$row.recording_name|escape}
		{/if}
	</td>
	{if $recording_storage_type != 'base64'}
	<td class='hide-md-dn'>{$row._filename_html}</td>
	{/if}
	{if $has_recording_play || $has_recording_download}
	<td class='middle button center no-link no-wrap'>
		{$row._tools_html}
	</td>
	{/if}
	<td class='center no-wrap'>{$row._file_size|escape}</td>
	<td class='center hide-md-dn'>{$row.date_formatted|escape} {$row.time_formatted|escape}</td>
	<td class='description overflow hide-sm-dn'>{$row.recording_description|escape}&nbsp;</td>
	{if $has_recording_edit && $list_row_edit_button}
	<td class='action-button'>
		{$row._edit_button}
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
