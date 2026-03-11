<script language='JavaScript' type='text/javascript'>
	function check_file_type(file_input) {
		file_ext = file_input.value.substr((~-file_input.value.lastIndexOf('.') >>> 0) + 2);
		if (file_ext != 'mp3' && file_ext != 'wav' && file_ext != 'ogg' && file_ext != '') {
			display_message("{$text['message-unsupported_file_type']|escape}", 'negative', '2750');
		}
	}
</script>
{include file='partials/_action_bar.tpl'}
{include file='partials/_modals.tpl'}
{$description_html}<br /><br />
{include file='partials/_table.tpl'}
