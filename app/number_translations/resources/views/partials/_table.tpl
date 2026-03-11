<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_number_translation_add || $has_number_translation_edit || $has_number_translation_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($number_translations)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_number_translation_name}
	{$th_number_translation_enabled}
	<th class='hide-sm-dn'>{$text['label-number_translation_description']}</th>
	{if $has_number_translation_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$number_translations item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_number_translation_add || $has_number_translation_edit || $has_number_translation_delete}
	<td class='checkbox'>
		<input type='checkbox' name='number_translations[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='number_translations[{$row@index}][uuid]' value='{$row.number_translation_uuid|escape}' />
	</td>
	{/if}
	<td>
		{if $has_number_translation_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.number_translation_name|escape}</a>
		{else}
		{$row.number_translation_name|escape}
		{/if}
	</td>
	{if $has_number_translation_edit}
	<td class='no-link center'>
		<input type='hidden' name='number_translations[{$row@index}][number_translation_enabled]' value='{$row.number_translation_enabled|escape}' />
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.number_translation_description|escape}</td>
	{if $has_number_translation_edit && $list_row_edit_button}
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
