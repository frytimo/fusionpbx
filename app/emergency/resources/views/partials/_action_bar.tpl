<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-emergency_logs']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			{if $has_emergency_logs_view_all}
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
