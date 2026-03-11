<div class='card'>
<table class='list'>
<tr class='list-header'>
	{$th_domain_name}
	{$th_username}
	{$th_app_name}
	{$th_transaction_code}
	{$th_transaction_address}
	{$th_transaction_type}
	{$th_transaction_date}
	{if $has_database_transaction_edit && $list_row_edit_button}
	<td class='action-button'>&nbsp;</td>
	{/if}
</tr>

{foreach from=$transactions item=row}
<tr class='list-row' href='{$row._list_row_url|escape}'>
	<td>{$row.domain_name|escape}&nbsp;</td>
	<td>{$row.username|escape}&nbsp;</td>
	<td>
		{if $has_database_transaction_edit}
		<a href='{$row._list_row_url|escape}'>{$row.app_name|escape}</a>
		{else}
		{$row.app_name|escape}
		{/if}
		&nbsp;</td>
	<td>{$row.transaction_code|escape}&nbsp;</td>
	<td>{$row.transaction_address|escape}&nbsp;</td>
	<td>{$row.transaction_type|escape}&nbsp;</td>
	<td>{$row.transaction_date|escape}&nbsp;</td>
	{if $has_database_transaction_edit && $list_row_edit_button}
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
