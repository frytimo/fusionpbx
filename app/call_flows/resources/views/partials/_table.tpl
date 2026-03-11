<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' id='toggle_field' name='toggle_field' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_call_flow_add || $has_call_flow_edit || $has_call_flow_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($rows)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{if $th_domain_name}{$th_domain_name}{/if}
	{$th_call_flow_name}
	{$th_call_flow_extension}
	{$th_call_flow_feature_code}
	{$th_call_flow_status}
	{if $has_call_flow_context}{$th_call_flow_context}{/if}
	{$th_call_flow_enabled}
	{$th_call_flow_description}
	{if $has_call_flow_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$rows item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_call_flow_add || $has_call_flow_edit || $has_call_flow_delete}
	<td class='checkbox'>
		<input type='checkbox' name='call_flows[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='call_flows[{$row@index}][uuid]' value='{$row.call_flow_uuid|escape}' />
	</td>
	{/if}
	{if $th_domain_name}
	<td>{$row._domain_display}</td>
	{/if}
	<td><a href='{$row._list_row_url|escape}'>{$row.call_flow_name|escape}</a>&nbsp;</td>
	<td>{$row.call_flow_extension|escape}&nbsp;</td>
	<td>{$row.call_flow_feature_code|escape}&nbsp;</td>
	{if $has_call_flow_edit}
	<td class='no-link'>{$row._status_toggle_button}</td>
	{else}
	<td>{$row._status_label}</td>
	{/if}
	{if $has_call_flow_context}
	<td>{$row.call_flow_context|escape}&nbsp;</td>
	{/if}
	{if $has_call_flow_edit}
	<td class='no-link center'>{$row._enabled_toggle_button}</td>
	{else}
	<td class='center'>{$row._enabled_label|escape}</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.call_flow_description|escape}&nbsp;</td>
	{if $has_call_flow_edit && $list_row_edit_button}
	<td class='action-button'>{$row._edit_button}</td>
	{/if}
</tr>
{/foreach}

</table>
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
</div>
