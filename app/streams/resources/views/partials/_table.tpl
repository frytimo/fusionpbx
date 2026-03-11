<div class='card'>
<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">

<table class='list'>
<tr class='list-header'>
	{if $has_stream_add || $has_stream_edit || $has_stream_delete}
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle(); checkbox_on_change(this);'{if empty($streams)} style='visibility: hidden;'{/if}>
	</th>
	{/if}
	{$th_domain_name}
	{$th_stream_name}
	<th class='pct-60'>{$text['label-play']}</th>
	{$th_stream_enabled}
	{$th_stream_description}
	{if $has_stream_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$streams item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	{if $has_stream_add || $has_stream_edit || $has_stream_delete}
	<td class='checkbox'>
		<input type='checkbox' name='streams[{$row@index}][checked]' id='checkbox_{$row@index}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='streams[{$row@index}][uuid]' value='{$row.stream_uuid|escape}' />
	</td>
	{/if}
	{if $show == 'all' && $has_stream_all}
	<td>{$row._domain_name}</td>
	{/if}
	<td class='no-wrap'>
		{if $has_stream_edit}
		<a href='{$row._list_row_url|escape}' title="{$text['button-edit']|escape}">{$row.stream_name|escape}</a>
		{else}
		{$row.stream_name|escape}
		{/if}
	</td>
	<td class='no-wrap button'>
		{$row._audio_html}
	</td>
	{if $has_stream_edit}
	<td class='no-link center'>
		{$row._toggle_button}
	</td>
	{else}
	<td class='center'>
		{$row._enabled_label|escape}
	</td>
	{/if}
	<td class='description overflow hide-sm-dn'>{$row.stream_description|escape}&nbsp;</td>
	{if $has_stream_edit && $list_row_edit_button}
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
