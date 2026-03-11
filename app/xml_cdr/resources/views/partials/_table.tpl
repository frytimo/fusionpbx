{$text['description']} &nbsp; {$text['description_search']}
<br /><br />

{$search_form_html}

{$col_overflow_style_html}

<form id="form_list" method="post">
<input type="hidden" id="action" name="action" value="">

<div class="card">
<table class="list">
{$col_headers_html}
{if $rendered_rows}
	{foreach from=$rendered_rows item=row_html}
		{$row_html}
	{/foreach}
{/if}
</table>
</div>
<br />
<div align="center">{$paging_controls}</div>
<input type="hidden" name="{$token.name|escape}" value="{$token.hash|escape}">
</form>
