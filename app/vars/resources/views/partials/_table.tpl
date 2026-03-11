<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>

{foreach from=$vars item=row}
{if $row._show_category_header}
<tr>
	<td colspan='7' class='no-link'>
		{if $row._category_needs_br}<br />{/if}<b>{$row.var_category|escape}</b>
	</td>
</tr>
<tr class='list-header'>
	{if $has_var_add || $has_var_edit || $has_var_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all_{$row._category_modifier|escape}' name='checkbox_all' onclick="list_all_toggle('{$row._category_modifier|escape}'); checkbox_on_change(this);"{if empty($vars)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_var_name}
	{$th_var_value}
	{$th_var_hostname}
	{$th_var_enabled}
	<th class='hide-sm-dn'>{$text['label-description']|escape}</th>
	{if $has_var_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>
{/if}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_var_add || $has_var_edit || $has_var_delete}
	<td class='checkbox'>
		<input type='checkbox' name='vars[{$row@index}][checked]' id='checkbox_{$row@index}' class='checkbox_{$row._category_modifier|escape}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all_{$row._category_modifier|escape}').checked = false; }">
		<input type='hidden' name='vars[{$row@index}][uuid]' value='{$row.var_uuid|escape}' />
	</td>
	{/if}
	<td class='overflow'>
		{if $has_var_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.var_name|escape}</a>
		{else}
		{$row.var_name|escape}
		{/if}
	</td>
	<td class='overflow'>{$row.var_value}</td>
	<td class='hide-sm-dn'>{$row.var_hostname}&nbsp;</td>
	{if $has_var_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.var_description|escape}&nbsp;</td>
	{if $has_var_edit && $list_row_edit_button}
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
