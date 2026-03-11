<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<div class='card'>
<table class='list'>
<tr class='list-header'>
	{if $has_conference_profile_add || $has_conference_profile_edit || $has_conference_profile_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($conference_profiles)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_profile_name}
	{$th_profile_enabled}
	<th class='hide-sm-dn'>{$text['label-profile_description']}</th>
	{if $has_conference_profile_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$conference_profiles item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_conference_profile_add || $has_conference_profile_edit || $has_conference_profile_delete}
	<td class='checkbox'>
		<input type='checkbox' name='conference_profiles[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='conference_profiles[{$row@index}][uuid]' value='{$row.conference_profile_uuid|escape}' />
	</td>
	{/if}
	<td>
		{if $has_conference_profile_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.profile_name|escape}</a>
		{else}
		{$row.profile_name|escape}
		{/if}
	</td>
	{if $has_conference_profile_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.profile_description|escape}</td>
	{if $has_conference_profile_edit && $list_row_edit_button}
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
