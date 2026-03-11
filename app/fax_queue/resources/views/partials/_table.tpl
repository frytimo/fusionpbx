<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $permission.fax_queue_add || $permission.fax_queue_edit || $permission.fax_queue_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($fax_queue)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	<th class='center shrink'>{$text['label-date']}</th>
	<th class='center shrink hide-md-dn'>{$text['label-time']}</th>
	{$th_hostname}
	{$th_fax_caller_id_name}
	{$th_fax_caller_id_number}
	{$th_fax_number}
	{$th_fax_email_address}
	{$th_insert_user}
	{$th_fax_status}
	{$th_fax_retry_date}
	{$th_fax_notify_date}
	{$th_fax_retry_count}
	{if $permission.fax_queue_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$fax_queue item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $permission.fax_queue_add || $permission.fax_queue_edit || $permission.fax_queue_delete}
	<td class='checkbox'>
		<input type='checkbox' name='fax_queue[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='fax_queue[{$row@index}][fax_queue_uuid]' value='{$row.fax_queue_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $permission.fax_queue_all}
	<td>{$row.domain_name|escape}</td>
	{/if}
	<td nowrap='nowrap'>{$row.fax_date_formatted|escape}</td>
	<td class='hide-md-dn' nowrap='nowrap'>{$row.fax_time_formatted|escape}</td>
	{if $permission.fax_queue_all}
	<td class='hide-md-dn'>{$row.hostname|escape}</td>
	{/if}
	<td class='hide-md-dn'>{$row.fax_caller_id_name|escape}</td>
	<td>{$row.fax_caller_id_number|escape}</td>
	<td>{$row.fax_number|escape}</td>
	<td>{$row._fax_email_address|escape}</td>
	<td>{$row.insert_user|escape}</td>
	<td>{$row._status_label|escape}</td>
	<td>{$row.fax_retry_date_formatted|escape} {$row.fax_retry_time_formatted|escape}</td>
	<td>{$row.fax_notify_date_formatted|escape} {$row.fax_notify_time_formatted|escape}</td>
	<td>{$row.fax_retry_count|escape}</td>
	{if $permission.fax_queue_edit && $list_row_edit_button}
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
