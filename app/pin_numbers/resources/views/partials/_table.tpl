<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_pin_number_add || $has_pin_number_edit || $has_pin_number_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle();'{if empty($pin_numbers)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_pin_number}
	{$th_accountcode}
	{$th_enabled}
	{$th_description}
	{if $has_pin_number_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$pin_numbers item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_pin_number_add || $has_pin_number_edit || $has_pin_number_delete}
	<td class='checkbox'>
		<input type='checkbox' name='pin_numbers[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='pin_numbers[{$row@index}][uuid]' value='{$row.pin_number_uuid|escape}' />
	</td>
	{/if}
	<td>
		{if $has_pin_number_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.pin_number|escape}</a>
		{else}
		{$row.pin_number|escape}
		{/if}
	</td>
	<td>{$row.accountcode|escape}&nbsp;</td>
	{if $has_pin_number_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.description|escape}&nbsp;</td>
	{if $has_pin_number_edit && $list_row_edit_button}
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
