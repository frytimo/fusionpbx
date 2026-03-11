<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_fax_extension_add || $has_fax_extension_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($result)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_fax_name}
	{$th_fax_extension}
	{$th_fax_email}
	<th>{$text['label-tools']}</th>
	{$th_fax_description}
	{if $has_fax_extension_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$result item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_fax_extension_add || $has_fax_extension_delete}
	<td class='checkbox'>
		<input type='checkbox' name='fax_servers[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='fax_servers[{$row@index}][uuid]' value='{$row.fax_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_fax_extension_view_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>
		{if $has_fax_extension_edit}
		<a href='{$row._list_row_url|escape}'>{$row.fax_name|escape}</a>
		{else}
		{$row.fax_name|escape}
		{/if}
	</td>
	<td>{$row.fax_extension|escape}</td>
	<td class='overflow' style='min-width: 25%;'>{$row._fax_email|escape}&nbsp;</td>
	<td class='no-link no-wrap'>{$row._tools_html}</td>
	<td class='description overflow hide-sm-dn'>{$row.fax_description|escape}&nbsp;</td>
	{if $has_fax_extension_edit && $list_row_edit_button}
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
<input type='hidden' name='{$token.name}' value='{$token.hash}'>
</form>
