<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' id='voicemail_id' name='voicemail_id' value='{$voicemail_id|escape}'>

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_voicemail_greeting_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($greetings)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	<th class='shrink center'>{$text['label-selected']}</th>
	{$th_greeting_id}
	{$th_greeting_name}
	{if !$storage_is_base64}
	{$th_greeting_filename}
	{/if}
	{if $has_voicemail_greeting_play || $has_voicemail_greeting_download}
	<th class='center'>{$text['label-tools']}</th>
	{/if}
	<th class='center no-wrap hide-xs'>{$text['label-size']}</th>
	{if !$storage_is_base64}
	<th class='center no-wrap hide-xs'>{$text['label-uploaded']}</th>
	{/if}
	{$th_description}
	{if $has_voicemail_greeting_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$greetings item=row}
{$row._progress_bar_html}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_voicemail_greeting_delete}
	<td class='checkbox'>
		<input type='checkbox' name='voicemail_greetings[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="if (!this.checked) { document.getElementById('checkbox_all').checked = false; } checkbox_on_change(this);">
		<input type='hidden' name='voicemail_greetings[{$row@index}][uuid]' value='{$row.voicemail_greeting_uuid|escape}' />
	</td>
	{/if}
	<td class='center no-link'>
		{$row._radio_button}
	</td>
	<td class='center'>{$row.greeting_id|escape}</td>
	<td class='no-wrap'>
		{if $has_voicemail_greeting_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.greeting_name|escape}</a>
		{else}
		{$row.greeting_name|escape}
		{/if}
	</td>
	{if !$storage_is_base64}
	<td class='hide-sm-dn'>{$row.greeting_filename|escape}</td>
	{/if}
	{if $has_voicemail_greeting_play || $has_voicemail_greeting_download}
	<td class='middle button center no-link no-wrap'>
		{$row._tools_html}
	</td>
	{/if}
	<td class='center no-wrap hide-xs'>{$row._file_size}</td>
	{if !$storage_is_base64}
	<td class='center no-wrap hide-xs'>{$row._file_date}</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.greeting_description|escape}&nbsp;</td>
	{if $has_voicemail_greeting_edit && $list_row_edit_button}
	<td class='action-button'>
		{$row._edit_button}
	</td>
	{/if}
</tr>
{/foreach}

</table>
</div>
<br />
<input type='hidden' name='{$token.name}' value='{$token.hash}'>
</form>
