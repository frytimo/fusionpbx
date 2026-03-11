<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>

{foreach from=$extension_settings item=row}
	{if $row._show_type_header}
	<tr>
		<td align='left' colspan='999'>&nbsp;</td>
	</tr>
	<tr>
		<td align='left' colspan='999' nowrap='nowrap'><b>{$row._label_extension_setting_type|escape}</b></td>
	</tr>
	<tr class='list-header'>
		{if $has_extension_setting_add || $has_extension_setting_edit || $has_extension_setting_delete}
		<th class='checkbox'>
			<input type='checkbox' id='checkbox_all_{$row._extension_setting_type_lower}' name='checkbox_all' onclick="list_all_toggle('{$row._extension_setting_type_lower}'); checkbox_on_change(this);">
		</th>
		{/if}
		<th>{$text['label-extension_setting_type']}</th>
		<th>{$text['label-extension_setting_name']}</th>
		<th>{$text['label-extension_setting_value']}</th>
		<th class='center'>{$text['label-extension_setting_enabled']}</th>
		<th class='hide-sm-dn'>{$text['label-extension_setting_description']}</th>
		{if $has_extension_setting_edit && $list_row_edit_button}
		<td class='action-button'>&nbsp;</td>
		{/if}
	</tr>
	{/if}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_extension_setting_add || $has_extension_setting_edit || $has_extension_setting_delete}
	<td class='checkbox'>
		<input type='checkbox' name='extension_settings[{$row@index}][checked]' id='checkbox_{$row@index}' class='checkbox_{$row._extension_setting_type_lower}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all_{$row._extension_setting_type_lower}').checked = false; }">
		<input type='hidden' name='extension_settings[{$row@index}][uuid]' value='{$row.extension_setting_uuid|escape}' />
	</td>
	{/if}
	<td>{$row.extension_setting_type|escape}</td>
	<td>{$row.extension_setting_name|escape}</td>
	<td>{$row.extension_setting_value|escape}</td>
	{if $has_extension_setting_edit}
	<td class='no-link center'>
		<input type='hidden' name='number_translations[{$row@index}][extension_setting_enabled]' value='{$row.extension_setting_enabled|escape}' />
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.extension_setting_description|escape}</td>
	{if $has_extension_setting_edit && $list_row_edit_button}
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
