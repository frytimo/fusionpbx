<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-extension_settings']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_back}
		{$btn_add}
		{$btn_copy}
		{$btn_toggle}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			<input type='text' class='txt list-search' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown='list_search_reset();'>
			{$btn_search}
			{$btn_reset}
			{if $paging_controls_mini != ''}
				<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
			{/if}
			<input type='hidden' name='id' value='{$extension_uuid|escape}'>
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
