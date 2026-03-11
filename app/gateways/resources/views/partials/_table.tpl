<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_gateway_add || $has_gateway_edit || $has_gateway_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($gateways)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_gateway}
	<th class='hide-sm-dn'>{$text['label-proxy']}</th>
	{$th_context}
	{$th_register}
	{$th_esl_status}
	{$th_esl_action}
	{$th_esl_state}
	{$th_hostname}
	{$th_enabled}
	{$th_description}
	{if $has_gateway_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$gateways item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_gateway_add || $has_gateway_edit || $has_gateway_delete}
	<td class='checkbox'>
		<input type='checkbox' name='gateways[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='gateways[{$row@index}][uuid]' value='{$row.gateway_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_gateway_all}
	<td>{$row._domain_name|escape}</td>
	{/if}
	<td>
		{if $has_gateway_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.gateway|escape}</a>
		{else}
		{$row.gateway|escape}
		{/if}
	</td>
	<td>{$row.proxy|escape}</td>
	<td>{$row.context|escape}</td>
	<td>{$row._register_display}</td>
	{$row._esl_cells}
	<td class='hide-sm-dn'>{$row.hostname|escape}</td>
	{if $has_gateway_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.description|escape}&nbsp;</td>
	{if $has_gateway_edit && $list_row_edit_button}
	<td class='action-button'>
		{$row._edit_button}
	</td>
	{/if}
</tr>
{/foreach}

</table>
<br /><br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
</div>
