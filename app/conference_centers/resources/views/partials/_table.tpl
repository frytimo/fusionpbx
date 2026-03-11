<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_conference_center_edit || $has_conference_center_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($conference_centers)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_conference_center_name}
	{$th_conference_center_extension}
	{$th_conference_center_greeting}
	{$th_conference_center_pin_length}
	{$th_conference_center_enabled}
	{$th_conference_center_description}
	{if $has_conference_center_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$conference_centers item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_conference_center_edit || $has_conference_center_delete}
	<td class='checkbox'>
		<input type='checkbox' name='conference_centers[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='conference_centers[{$row@index}][uuid]' value='{$row.conference_center_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_conference_center_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td><a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.conference_center_name|escape}</a>&nbsp;</td>
	<td>{$row.conference_center_extension|escape}&nbsp;</td>
	<td>{$row.conference_center_greeting|escape}&nbsp;</td>
	<td class='center'>{$row.conference_center_pin_length|escape}&nbsp;</td>
	{if $has_conference_center_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.conference_center_description|escape}&nbsp;</td>
	{if $has_conference_center_edit && $list_row_edit_button}
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
