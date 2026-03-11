<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_conference_add || $has_conference_edit || $has_conference_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($conferences)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_conference_name}
	{$th_conference_extension}
	{$th_conference_profile}
	{$th_conference_order}
	<th style='text-align: center;'>{$text['label-tools']}</th>
	{$th_conference_enabled}
	{$th_conference_description}
	{if $has_conference_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$conferences item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_conference_add || $has_conference_edit || $has_conference_delete}
	<td class='checkbox'>
		<input type='checkbox' name='conferences[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='conferences[{$row@index}][uuid]' value='{$row.conference_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_conference_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td><a href='{$row._list_row_url|escape}'>{$row._conference_name_display|escape}</a>&nbsp;</td>
	<td>{$row.conference_extension|escape}&nbsp;</td>
	<td>{$row.conference_profile|escape}&nbsp;</td>
	<td class='center'>{$row.conference_order|escape}&nbsp;</td>
	<td class='no-link center'>
		{$row._tools_inner}
	</td>
	{if $has_conference_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.conference_description|escape}&nbsp;</td>
	{if $has_conference_edit && $list_row_edit_button}
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
