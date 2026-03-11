<form id='form_list' method='POST'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='type' value="{$destination_type|escape}">
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_destination_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($destinations)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_destination_type}
	{$th_destination_prefix}
	{$th_destination_trunk_prefix}
	{$th_destination_area_code}
	{$th_destination_number}
	{$th_destination_actions}
	{$th_destination_cid_name_prefix}
	{$th_destination_context}
	{$th_destination_caller_id_name}
	{$th_destination_caller_id_number}
	{$th_destination_enabled}
	{$th_destination_description}
	{if $has_destination_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$destinations item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_destination_delete}
	<td class='checkbox'>
		<input type='checkbox' name='destinations[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='destinations[{$row@index}][uuid]' value='{$row.destination_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_destination_all}
	<td>{$row._domain_label|escape}</td>
	{/if}
	<td>{$row._type_label|escape}&nbsp;</td>
	<td class='center'>{$row.destination_prefix|escape}&nbsp;</td>
	{if $th_destination_trunk_prefix != ''}
	<td>{$row.destination_trunk_prefix|escape}&nbsp;</td>
	{/if}
	{if $th_destination_area_code != ''}
	<td>{$row.destination_area_code|escape}&nbsp;</td>
	{/if}
	<td class='no-wrap'>
		{if $has_destination_edit}
		<a href='{$row._list_row_url|escape}'>{$row._formatted_number|escape}</a>
		{else}
		{$row._formatted_number|escape}
		{/if}
	</td>
	{if $show != 'all'}
	<td class='overflow' style='min-width: 125px;'>{$row.actions}&nbsp;</td>
	{/if}
	{if $th_destination_cid_name_prefix != ''}
	<td>{$row.destination_cid_name_prefix|escape}&nbsp;</td>
	{/if}
	{if $th_destination_context != ''}
	<td>{$row.destination_context|escape}&nbsp;</td>
	{/if}
	{if $th_destination_caller_id_name != ''}
	<td>{$row.destination_caller_id_name|escape}&nbsp;</td>
	<td>{$row.destination_caller_id_number|escape}&nbsp;</td>
	{/if}
	<td>{$row._enabled_label|escape}&nbsp;</td>
	<td class='description overflow hide-sm-dn'>{$row.destination_description|escape}&nbsp;</td>
	{if $has_destination_edit && $list_row_edit_button}
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
