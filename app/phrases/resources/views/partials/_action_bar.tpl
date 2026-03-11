<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['header_phrases']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_add}
		{$btn_copy}
		{$btn_toggle}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			{if $has_phrase_all}
				{if $show == 'all'}
					<input type='hidden' name='show' value='all'>
				{else}
					{$btn_show_all}
				{/if}
			{/if}
			<input type='text' class='txt list-search' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown=''>
			{$btn_search}
			{if $paging_controls_mini != ''}
				<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
			{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
