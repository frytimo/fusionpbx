<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_email_queue_add || $has_email_queue_edit || $has_email_queue_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($email_queue)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	<th class='center shrink'>{$text['label-date']}</th>
	<th class='center shrink hide-md-dn'>{$text['label-time']}</th>
	<th class='shrink hide-md-dn'>{$text['label-hostname']}</th>
	<th class='shrink hide-md-dn'>{$text['label-email_from']}</th>
	{$th_email_to}
	{$th_email_subject}
	{$th_email_status}
	{$th_email_retry_count}
	<th class='hide-md-dn'>{$text['label-email_action_after']}</th>
	{if $has_email_queue_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$email_queue item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_email_queue_add || $has_email_queue_edit || $has_email_queue_delete}
	<td class='checkbox'>
		<input type='checkbox' name='email_queue[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='email_queue[{$row@index}][email_queue_uuid]' value='{$row.email_queue_uuid|escape}' />
	</td>
	{/if}
	{if $has_email_queue_edit}
	<td nowrap='nowrap'><a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.email_date_formatted|escape}</a></td>
	<td nowrap='nowrap' class='center shrink hide-md-dn'><a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.email_time_formatted|escape}</a></td>
	{else}
	<td nowrap='nowrap'>{$row.email_date_formatted|escape}</td>
	<td nowrap='nowrap'>{$row.email_time_formatted|escape}</td>
	{/if}
	<td class='hide-md-dn'>{$row.hostname|escape}</td>
	<td class='shrink hide-md-dn'>{$row.email_from|escape}</td>
	<td class='overflow' style='width: 20%; max-width: 200px;'>{$row.email_to|escape}</td>
	<td class='overflow' style='width: 30%; max-width: 200px;'>{$row._email_subject_decoded|escape}</td>
	<td>{$row._email_status_label|escape}</td>
	<td>{$row.email_retry_count|escape}</td>
	<td class='hide-md-dn'>{$row.email_action_after|escape}</td>
	{if $has_email_queue_edit && $list_row_edit_button}
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
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
