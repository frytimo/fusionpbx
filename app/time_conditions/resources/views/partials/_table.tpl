<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_time_condition_edit || $has_time_condition_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($time_conditions)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_dialplan_name}
	{$th_dialplan_number}
	{$th_dialplan_context}
	{$th_dialplan_order}
	{$th_dialplan_enabled}
	{$th_dialplan_description}
	{if $has_time_condition_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$time_conditions item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_time_condition_add || $has_time_condition_edit || $has_time_condition_delete}
	<td class='checkbox'>
		<input type='checkbox' name='time_conditions[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='time_conditions[{$row@index}][uuid]' value='{$row.dialplan_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_time_condition_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>
		{if $has_time_condition_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.dialplan_name|escape}</a>
		{else}
		{$row.dialplan_name|escape}
		{/if}
	</td>
	<td>{if $row.dialplan_number != ''}{$row.dialplan_number|escape}{else}&nbsp;{/if}</td>
	{if $has_time_condition_context}<td>{$row.dialplan_context|escape}</td>{/if}
	<td class='center'>{$row.dialplan_order|escape}</td>
	{if $has_time_condition_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.dialplan_description|escape}&nbsp;</td>
	{if $has_time_condition_edit && $list_row_edit_button}
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
