<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_ring_group_add || $has_ring_group_edit || $has_ring_group_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($ring_groups)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_ring_group_name}
	{$th_ring_group_extension}
	{$th_ring_group_strategy}
	{$th_ring_group_forward}
	{$th_ring_group_enabled}
	{$th_ring_group_description}
	{if $has_ring_group_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$ring_groups item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_ring_group_add || $has_ring_group_edit || $has_ring_group_delete}
	<td class='checkbox'>
		<input type='checkbox' name='ring_groups[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='ring_groups[{$row@index}][uuid]' value='{$row.ring_group_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_ring_group_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>
		{if $has_ring_group_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.ring_group_name|escape}</a>
		{else}
		{$row.ring_group_name|escape}
		{/if}
	</td>
	<td>{$row.ring_group_extension|escape}&nbsp;</td>
	<td>{$row._strategy_label|escape}&nbsp;</td>
	<td>{$row._forward_display}&nbsp;</td>
	{if $has_ring_group_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.ring_group_description|escape}&nbsp;</td>
	{if $has_ring_group_edit && $list_row_edit_button}
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
