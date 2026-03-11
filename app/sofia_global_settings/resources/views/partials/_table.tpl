<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_sofia_global_setting_add || $has_sofia_global_setting_edit || $has_sofia_global_setting_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($sofia_global_settings)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_global_setting_name}
	{$th_global_setting_value}
	{$th_global_setting_enabled}
	<th class='hide-sm-dn'>{$text['label-global_setting_description']}</th>
	{if $has_sofia_global_setting_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$sofia_global_settings item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_sofia_global_setting_add || $has_sofia_global_setting_edit || $has_sofia_global_setting_delete}
	<td class='checkbox'>
		<input type='checkbox' name='sofia_global_settings[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='sofia_global_settings[{$row@index}][sofia_global_setting_uuid]' value='{$row.sofia_global_setting_uuid|escape}' />
	</td>
	{/if}
	<td>
		{if $has_sofia_global_setting_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.global_setting_name|escape}</a>
		{else}
		{$row.global_setting_name|escape}
		{/if}
	</td>
	<td>{$row.global_setting_value|escape}</td>
	{if $has_sofia_global_setting_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.global_setting_description|escape}</td>
	{if $has_sofia_global_setting_edit && $list_row_edit_button}
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
