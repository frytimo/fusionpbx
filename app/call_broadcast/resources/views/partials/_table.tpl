<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_call_broadcast_add || $has_call_broadcast_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($rows)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{if $th_domain_name}{$th_domain_name}{/if}
	{$th_broadcast_name}
	{$th_broadcast_limit}
	{$th_broadcast_start_time}
	{$th_broadcast_description}
	{if $has_call_broadcast_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$rows item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_call_broadcast_add || $has_call_broadcast_delete}
	<td class='checkbox'>
		<input type='checkbox' name='call_broadcasts[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='call_broadcasts[{$row@index}][uuid]' value='{$row.call_broadcast_uuid|escape}' />
	</td>
	{/if}
	{if $th_domain_name}
	<td>{$row._domain_display}</td>
	{/if}
	<td>
		{if $has_call_broadcast_edit}
		<a href='{$row._list_row_url|escape}'>{$row.broadcast_name|escape}</a>
		{else}
		{$row.broadcast_name|escape}
		{/if}
	</td>
	<td>{$row.broadcast_concurrent_limit|escape}</td>
	<td>{$row._start_time_display}</td>
	<td class='description overflow hide-xs'>{$row.broadcast_description|escape}</td>
	{if $has_call_broadcast_edit && $list_row_edit_button}
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
