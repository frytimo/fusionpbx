<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_access_control_add || $has_access_control_edit || $has_access_control_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($access_controls)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_access_control_name}
	{$th_access_control_default}
	<th class='hide-sm-dn'>{$text['label-access_control_description']}</th>
	{if $has_access_control_edit && $list_row_edit_button == 'true'}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$access_controls item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_access_control_add || $has_access_control_edit || $has_access_control_delete}
	<td class='checkbox'>
		<input type='checkbox' name='access_controls[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='access_controls[{$row@index}][uuid]' value='{$row.access_control_uuid|escape}' />
	</td>
	{/if}
	<td>
		{if $has_access_control_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.access_control_name|escape}</a>
		{else}
		{$row.access_control_name|escape}
		{/if}
	</td>
	<td>{$row.access_control_default|escape}</td>
	<td class='description overflow hide-sm-dn'>{$row.access_control_description|escape}</td>
	{if $has_access_control_edit && $list_row_edit_button == 'true'}
	<td class='action-button'>
		{$row._edit_button}
	</td>
	{/if}
</tr>
{/foreach}

</table>
</div>
<br />
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
