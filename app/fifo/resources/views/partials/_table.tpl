<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_fifo_add || $has_fifo_edit || $has_fifo_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($fifo)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_fifo_name}
	{$th_fifo_extension}
	{$th_fifo_agent_status}
	{$th_fifo_agent_queue}
	{$th_fifo_order}
	{$th_fifo_enabled}
	<th class='hide-sm-dn'>{$text['label-fifo_description']}</th>
	{if $has_fifo_edit && $list_row_edit_button == 'true'}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$fifo item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_fifo_add || $has_fifo_edit || $has_fifo_delete}
	<td class='checkbox'>
		<input type='checkbox' name='fifo[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='fifo[{$row@index}][uuid]' value='{$row.fifo_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_fifo_all}
	<td>{$row.domain_name|escape}</td>
	{/if}
	<td>
		{if $has_fifo_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.fifo_name|escape}</a>
		{else}
		{$row.fifo_name|escape}
		{/if}
	</td>
	<td>{$row.fifo_extension|escape}</td>
	<td>{$row.fifo_agent_status|escape}</td>
	<td>{$row.fifo_agent_queue|escape}</td>
	<td>{$row.fifo_order|escape}</td>
	{if $has_fifo_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.fifo_description|escape}</td>
	{if $has_fifo_edit && $list_row_edit_button == 'true'}
	<td class='action-button'>
		{$row._edit_button}
	</td>
	{/if}
</tr>
{/foreach}

</table>
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name}' value='{$token.hash}'>
</form>
</div>
