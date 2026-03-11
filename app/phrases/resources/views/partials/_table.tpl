<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_phrase_add || $has_phrase_edit || $has_phrase_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($phrases)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_phrase_name}
	{$th_phrase_language}
	{$th_phrase_enabled}
	{$th_phrase_description}
	{if $has_phrase_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$phrases item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_phrase_add || $has_phrase_edit || $has_phrase_delete}
	<td class='checkbox'>
		<input type='checkbox' name='phrases[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='phrases[{$row@index}][uuid]' value='{$row.phrase_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_phrase_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>
		{if $has_phrase_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.phrase_name|escape}</a>
		{else}
		{$row.phrase_name|escape}
		{/if}
	</td>
	<td>{$row.phrase_language|escape}&nbsp;</td>
	{if $has_phrase_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.phrase_description|escape}&nbsp;</td>
	{if $has_phrase_edit && $list_row_edit_button}
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
