<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_sip_profile_add || $has_sip_profile_edit || $has_sip_profile_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($sip_profiles)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_sip_profile_name}
	{$th_sip_profile_hostname}
	{$th_sip_profile_enabled}
	{$th_sip_profile_description}
	{if $has_sip_profile_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$sip_profiles item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_sip_profile_add || $has_sip_profile_edit || $has_sip_profile_delete}
	<td class='checkbox'>
		<input type='checkbox' name='sip_profiles[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='sip_profiles[{$row@index}][uuid]' value='{$row.sip_profile_uuid|escape}' />
	</td>
	{/if}
	<td class='no-wrap'>
		{if $has_sip_profile_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.sip_profile_name|escape}</a>
		{else}
		{$row.sip_profile_name|escape}
		{/if}
	</td>
	<td>{$row.sip_profile_hostname|escape}&nbsp;</td>
	{if $has_sip_profile_edit}
	<td class='no-link center'>
		{$row._toggle_button}
		{$row._row_toggle_modal}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.sip_profile_description|escape}&nbsp;</td>
	{if $has_sip_profile_edit && $list_row_edit_button}
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
