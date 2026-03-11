<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">
<input type='hidden' name='fields' value="{$fields|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_device_edit || $has_device_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($devices)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_device_address}
	{$th_device_label}
	{if $device_alternate}
	{$th_device_alternate}
	{/if}
	{$th_device_vendor}
	{$th_device_template}
	<th>{$text['label-device_profiles']}</th>
	{$th_device_enabled}
	{$th_device_status}
	{$th_device_description}
	{if $has_device_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$devices item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_device_edit || $has_device_delete}
	<td class='checkbox'>
		<input type='checkbox' name='devices[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='devices[{$row@index}][uuid]' value='{$row.device_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_device_all}
	<td>{$row.domain_name|escape}</td>
	{/if}
	<td class='no-wrap'>
		{if $has_device_edit}
		<a href='{$row._list_row_url|escape}'>{$row._device_address_display|escape}</a>
		{else}
		{$row._device_address_display|escape}
		{/if}
	</td>
	<td>{$row.device_label|escape}&nbsp;</td>
	{if $device_alternate}
		{if $row.device_uuid_alternate}
		<td class='no-link'>
			<a href='device_edit.php?id={$row.device_uuid_alternate|escape}'>{$row.alternate_label|escape}</a>
		</td>
		{else}
		<td>&nbsp;</td>
		{/if}
	{/if}
	<td>{$row.device_vendor|escape}&nbsp;</td>
	<td>{$row.device_template|escape}&nbsp;</td>
	<td>{$row._device_profile_name|escape}&nbsp;</td>
	{if $has_device_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='no-link'>
		{$row._provisioned_cell}
	</td>
	<td class='description overflow hide-sm-dn'>{$row.device_description|escape}&nbsp;</td>
	{if $has_device_edit && $list_row_edit_button}
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
