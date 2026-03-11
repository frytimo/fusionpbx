<script language='javascript' type='text/javascript'>
	var fade_speed = 400;
	function toggle_select(select_id) {
		$('#'+select_id).fadeToggle(fade_speed, function() {
			document.getElementById(select_id).selectedIndex = 0;
			document.getElementById(select_id).focus();
		});
	}
</script>
<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-feature_codes']}</b></div>
	<div class='actions'>
		{$btn_export}
	</div>
	<div style='clear: both;'></div>
</div>
