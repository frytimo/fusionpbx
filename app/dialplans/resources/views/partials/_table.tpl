<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='app_uuid' name='app_uuid' value='{$app_uuid|escape}'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='context' value="{$context|escape}">
<input type='hidden' name='search' value="{$search|escape}">
<input type='hidden' name='order_by' value="{$order_by|escape}">
<input type='hidden' name='order' value="{$order|escape}">

<table class='list'>
<tr class='list-header'>
	{if $show_checkbox}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($dialplans)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_name}
	{$th_number}
	{if $has_dialplan_context}{$th_context_col}{/if}
	{$th_order_col}
	{$th_enabled}
	{$th_description}
	{if $has_edit_column}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$dialplans item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $show_checkbox}
	<td class='checkbox'>
		<input type='checkbox' name='dialplans[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='dialplans[{$row@index}][uuid]' value='{$row.dialplan_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_dialplan_all}
	<td>{$row._domain|escape}</td>
	{/if}
	<td>
		{if $row._list_row_url}
		<a href='{$row._list_row_url|escape}'>{$row.dialplan_name|escape}</a>
		{else}
		{$row.dialplan_name|escape}
		{/if}
	</td>
	<td>{if $row._number}{$row._number|escape}{else}&nbsp;{/if}</td>
	{if $has_dialplan_context}
	<td>{$row.dialplan_context|escape}</td>
	{/if}
	<td class='center'>{$row.dialplan_order|escape}</td>
	{if $row._has_toggle}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row.dialplan_enabled|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row._dialplan_description|escape}&nbsp;</td>
	{if $has_edit_column}
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
