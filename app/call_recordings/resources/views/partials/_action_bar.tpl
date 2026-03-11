<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-call_recordings']}</b></div>
	<div class='actions'>
		{$btn_download}
		{$btn_transcribe}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			{if $has_call_recording_all}
				{if $show == 'all'}
					<input type='hidden' name='show' value='all'>
				{else}
					{$btn_show_all}
				{/if}
			{/if}
			<input type='text' class='txt list-search' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown="$('#btn_reset').hide(); $('#btn_search').show();">
			{$btn_search}
			{$btn_reset}
			{if $paging_controls_mini != ''}
				<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
			{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
