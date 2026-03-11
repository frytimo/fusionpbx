<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-access_controls']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_reload}
		{$btn_add}
		{$btn_copy}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			<input type='text' class='txt list-search' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown=''>
			{$btn_search}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
