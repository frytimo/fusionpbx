<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['header-registrations']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_refresh}
		{$btn_unregister}
		{$btn_provision}
		{$btn_reboot}
		<form id='form_search' class='inline' method='get'>
			{if $has_registration_all}
				{if $show == 'all'}
					<input type='hidden' name='show' value='{$show|escape}'>
					{$btn_show_local}
				{else}
					{$btn_show_all}
				{/if}
				{if $profile != ''}
					<input type='hidden' name='profile' value='{$profile|escape}'>
					{$btn_all_profiles}
				{/if}
			{/if}
			{if !$reload}
				<input type='text' class='txt list-search' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown=''>
				{$btn_search}
			{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
