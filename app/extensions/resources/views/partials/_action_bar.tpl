<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['header-extensions']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_import}
		{$btn_export}
		{$btn_add}
		{$btn_toggle}
		{$btn_delete}
		{$btn_delete_options}
		<form id='form_search' class='inline' method='get'>
			{if $has_extension_all}
				{if $show == 'all'}
					<input type='hidden' name='show' value='all'>
				{else}
					{$btn_show_all}
				{/if}
			{/if}
			{if $order_by}<input type='hidden' name='order_by' value='{$order_by|escape}'>{/if}
			{if $order}<input type='hidden' name='order' value='{$order|escape}'>{/if}
			<input type='text' class='txt list-search' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown=''>
			{$btn_search}
			{if $paging_controls_mini != ''}
				<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
			{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
