<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_call_block_add || $has_call_block_edit || $has_call_block_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($rows)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{if $th_domain_name}{$th_domain_name}{/if}
	{$th_direction}
	{$th_extension}
	{$th_name}
	{$th_country_code}
	{$th_number}
	{$th_count}
	{$th_action}
	{$th_enabled}
	{$th_date}
	<th class='hide-md-dn pct-20'>{$text['label-description']}</th>
	{if $has_call_block_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$rows item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_call_block_add || $has_call_block_edit || $has_call_block_delete}
	<td class='checkbox'>
		<input type='checkbox' name='call_blocks[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='call_blocks[{$row@index}][uuid]' value='{$row.call_block_uuid|escape}' />
	</td>
	{/if}
	{if $th_domain_name}{$row._domain_cell}{/if}
	<td class='center'>{$row._direction_image}</td>
	<td class='center'>{$row._extension_display}</td>
	<td>{$row.call_block_name|escape}</td>
	{if $has_call_block_edit}
	<td><a href='{$row._list_row_url|escape}'>{$row.call_block_country_code|escape}</a></td>
	{else}
	<td>{$row.call_block_country_code|escape}</td>
	{/if}
	{if $has_call_block_edit}
	<td><a href='{$row._list_row_url|escape}'>{$row._number_formatted}</a></td>
	{else}
	<td>{$row._number_formatted}</td>
	{/if}
	<td class='center hide-sm-dn'>{$row.call_block_count|escape}</td>
	<td>{$row._action_display}</td>
	{if $has_call_block_edit}
	<td class='no-link center'>{$row._toggle_button}</td>
	{else}
	<td class='center'>{$row._enabled_label|escape}</td>
	{/if}
	<td class='no-wrap'>{$row.date_formatted|escape} <span class='hide-sm-dn'>{$row.time_formatted|escape}</span></td>
	<td class='description overflow hide-md-dn'>{$row.call_block_description|escape}</td>
	{if $has_call_block_edit && $list_row_edit_button}
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
