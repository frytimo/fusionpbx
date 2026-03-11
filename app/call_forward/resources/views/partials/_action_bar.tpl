{if $is_included}
<div class='action_bar sub'>
	<div class='heading'><b>{$text['header-call_forward']}</b></div>
	<div class='actions'>
		{$btn_view_all}
	</div>
	<div style='clear: both;'></div>
</div>
{else}
<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['header-call_forward']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_call_forward}
		{$btn_follow_me}
		{$btn_dnd}
		{$btn_show_all}
		<form id='form_search' class='inline' method='get'>
			{if $show == 'all' && $has_call_forward_all}
				<input type='hidden' name='show' value='all'>
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
{/if}
