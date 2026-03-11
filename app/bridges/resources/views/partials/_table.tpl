<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_bridge_add || $has_bridge_edit || $has_bridge_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($bridges)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_bridge_name}
	{$th_bridge_destination}
	{$th_bridge_enabled}
	<th class='hide-sm-dn'>{$text['label-bridge_description']}</th>
	{if $has_bridge_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$bridges item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_bridge_add || $has_bridge_edit || $has_bridge_delete}
	<td class='checkbox'>
		<input type='checkbox' name='bridges[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='bridges[{$row@index}][uuid]' value='{$row.bridge_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_bridge_all}
	<td>{$row.domain_name|escape}</td>
	{/if}
	<td>
		{if $has_bridge_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.bridge_name|escape}</a>
		{else}
		{$row.bridge_name|escape}
		{/if}
	</td>
	<td>{$row.bridge_destination|escape}</td>
	{if $has_bridge_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.bridge_description|escape}</td>
	{if $has_bridge_edit && $list_row_edit_button}
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
