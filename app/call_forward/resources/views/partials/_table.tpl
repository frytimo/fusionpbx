{if !$is_included}
<form id='form_list' method='post'>
{if $show == 'all' && $has_call_forward_all}
<input type='hidden' name='show' value='all'>
{/if}
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">
{/if}

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if !$is_included}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle();'{if empty($rows)} style='visibility: hidden;'{/if}>
	</th>
	{if $th_domain_name}{$th_domain_name}{/if}
	{/if}
	{$th_extension}
	{if $has_call_forward}{$th_call_forward}{/if}
	{if $has_follow_me}{$th_follow_me}{/if}
	{if $has_do_not_disturb}{$th_dnd}{/if}
	<th class='{if $is_included}hide-md-dn{else}hide-sm-dn{/if}'>{$text['label-description']}</th>
	{if $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$rows item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if !$is_included}
	<td class='checkbox'>
		<input type='checkbox' name='extensions[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='extensions[{$row@index}][uuid]' value='{$row.extension_uuid|escape}' />
	</td>
	{if $th_domain_name}
	<td>{$row._domain_display}</td>
	{/if}
	{/if}
	<td><a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.extension|escape}</a></td>
	{if $has_call_forward}
	<td>{$row._forward_all_display}&nbsp;</td>
	{/if}
	{if $has_follow_me}
	<td>{$row._follow_me_display}&nbsp;</td>
	{/if}
	{if $has_do_not_disturb}
	<td>{$row._dnd_display}&nbsp;</td>
	{/if}
	<td class='description overflow {if $is_included}hide-md-dn{else}hide-sm-dn{/if}'>{$row.description|escape}&nbsp;</td>
	{if $list_row_edit_button}
	<td class='action-button'>{$row._edit_button}</td>
	{/if}
</tr>
{/foreach}

</table>
</div>

{if !$is_included}
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
{/if}
