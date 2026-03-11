<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

{assign var="prev_category" value=""}
{foreach from=$modules item=row}
{if $row.module_category != $prev_category}
<div class='card'>
<table class='list'>
<tr><td colspan='7' class='no-link'>{if $prev_category != ''}<br />{/if}<b>{$row.module_category|escape}</b></td></tr>
<tr class='list-header'>
	{if $has_module_edit || $has_module_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all_{$row._modifier}' name='checkbox_all' onclick="list_all_toggle('{$row._modifier}'); checkbox_on_change(this);"{if empty($modules)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	<th>{$text['label-label']}</th>
	<th class='hide-xs'>{$text['label-status']}</th>
	{if $esl_connected}
	<th class='center'>{$text['label-action']}</th>
	{/if}
	<th class='center'>{$text['label-enabled']}</th>
	<th class='hide-sm-dn' style='min-width: 40%;'>{$text['label-description']}</th>
	{if $has_module_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>
{assign var="prev_category" value=$row.module_category}
{/if}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_module_edit || $has_module_delete}
	<td class='checkbox'>
		<input type='checkbox' name='modules[{$row@index}][checked]' id='checkbox_{$row@index}' class='checkbox_{$row._modifier}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all_{$row._modifier}').checked = false; }">
		<input type='hidden' name='modules[{$row@index}][uuid]' value='{$row.module_uuid|escape}' />
	</td>
	{/if}
	<td>
		{if $has_module_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.module_label|escape}</a>
		{else}
		{$row.module_label|escape}
		{/if}
	</td>
	{if $esl_connected}
	<td class='hide-xs'>{$row._status_html}</td>
	{if $has_module_edit}
	<td class='no-link center'>{$row._action_button}</td>
	{/if}
	{else}
	<td class='hide-xs'>{$row._status_html}</td>
	{/if}
	{if $has_module_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.module_description|escape}&nbsp;</td>
	{if $has_module_edit && $list_row_edit_button}
	<td class='action-button'>
		{$row._edit_button}
	</td>
	{/if}
</tr>
{foreachelse}
<div class='card'>
<table class='list'>
{/foreach}

</table>
</div>
<br />
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
