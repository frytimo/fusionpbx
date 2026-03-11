<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_voicemail_edit || $has_voicemail_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($voicemails)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_voicemail_id}
	{$th_voicemail_mail_to}
	{$th_voicemail_file}
	{$th_voicemail_local_after_email}
	{$th_transcription}
	{$th_tools}
	{$th_voicemail_enabled}
	{$th_voicemail_description}
	{if $has_voicemail_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$voicemails item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_voicemail_edit || $has_voicemail_delete}
	<td class='checkbox'>
		<input type='checkbox' name='voicemails[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='voicemails[{$row@index}][uuid]' value='{$row.voicemail_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_voicemail_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>
		{if $has_voicemail_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.voicemail_id|escape}</a>
		{else}
		{$row.voicemail_id|escape}
		{/if}
	</td>
	<td class='hide-sm-dn overflow' style='max-width: 175px;'>{$row.voicemail_mail_to|escape}&nbsp;</td>
	<td class='center hide-md-dn'>{$row._file_attached_label|escape}</td>
	<td class='center hide-md-dn'>{$row._local_after_email_display}&nbsp;</td>
	{if $show_transcription_col}
	<td class='center'>{$row._transcription_display}&nbsp;</td>
	{/if}
	{if $has_voicemail_message_view || $has_voicemail_greeting_view}
	<td class='no-link no-wrap'>
		{$row._tools_html}
	</td>
	{/if}
	{if $has_voicemail_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.voicemail_description|escape}&nbsp;</td>
	{if $has_voicemail_edit && $list_row_edit_button}
	<td class='action-button'>
		{$row._edit_button}
	</td>
	{/if}
</tr>
{/foreach}

</table>
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
</div>
