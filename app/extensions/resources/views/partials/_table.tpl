<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
{if $order_by}<input type='hidden' name='order_by' value='{$order_by|escape}'>{/if}
{if $order}<input type='hidden' name='order' value='{$order|escape}'>{/if}
<input type='hidden' name='page' value='{$page|escape}'>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_extension_enabled || $has_extension_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($extensions)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_registered}
	{$th_extension}
	{$th_effective_cid_name}
	{$th_outbound_cid_name}
	{$th_outbound_cid_number}
	{$th_call_group}
	{$th_device_address}
	{$th_device_template}
	{$th_user_context}
	{$th_enabled}
	{$th_description}
	{if $has_extension_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$extensions item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_extension_enabled || $has_extension_delete}
	<td class='checkbox'>
		<input type='checkbox' name='extensions[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='extensions[{$row@index}][uuid]' value='{$row.extension_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_extension_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	{if $has_extension_registered}
	{$row._reg_cell}
	{/if}
	<td>
		{if $has_extension_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.extension|escape}</a>
		{else}
		{$row.extension|escape}
		{/if}
	</td>
	<td class='hide-xs'>{$row.effective_caller_id_name|escape}&nbsp;</td>
	{if $has_outbound_caller_id_name}
	<td class='hide-sm-dn'>{$row.outbound_caller_id_name|escape}&nbsp;</td>
	{/if}
	{if $has_outbound_caller_id_number}
	<td class='hide-md-dn'>{$row.outbound_caller_id_number|escape}&nbsp;</td>
	{/if}
	{if $has_extension_call_group}
	<td>{$row.call_group|escape}&nbsp;</td>
	{/if}
	{if $has_extension_device_address}
	<td class='hide-md-dn'><a href='{$row._device_edit_url|escape}'>{$row.device_address|escape}</a></td>
	{/if}
	{if $has_extension_device_template}
	<td class='hide-md-dn'><a href='{$row._device_edit_url|escape}'>{$row.device_template|escape}</a></td>
	{/if}
	{if $has_extension_user_context}
	<td>{$row.user_context|escape}</td>
	{/if}
	{if $has_extension_enabled}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.description|escape}</td>
	{if $has_extension_edit && $list_row_edit_button}
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
