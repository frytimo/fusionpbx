<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-email_queue']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		<form id='form_test' class='inline' method='post' action='email_test.php' target='_blank'>
		{$btn_test}
		<span id='form_test' style='display: none;'>
			<input type='text' class='txt' style='width: 150px;' name='to' id='to' placeholder='recipient@domain.com'>
			{$btn_send}
		</span>
		</form>
		{$btn_resend}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
		<select class='formfld' style='margin-left: 15px;' name='email_status'>
			{$email_status_options_html}
		</select>
		<input type='text' class='txt list-search' style='margin-left: 0;' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" />
		{$btn_search}
		{if $paging_controls_mini != ''}
			<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
		{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
