<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['header-agents']}</b></div>
	<div class='actions'>
		<form id='frm' name='frm' method='GET'>
			<input type='hidden' name='queue_name' value='{$queue_name|escape}'>
			<input type='hidden' name='name' value='{$name|escape:'url'}'>
			<select class='formfld' name='agent_status' id='agent_status' onchange="document.getElementById('frm').submit();">
				<option value='' selected disabled hidden>{$text['label-status']}...</option>
				<option value=''></option>
				<option value='available'{if $agent_status === 'available'} selected{/if}>{$text['label-available']}</option>
				<option value='not_available'{if $agent_status === 'not_available'} selected{/if}>{$text['label-not_available']}</option>
				<option value='on_break'{if $agent_status === 'on_break'} selected{/if}>{$text['label-on_break']}</option>
				<option value='logged_out'{if $agent_status === 'logged_out'} selected{/if}>{$text['label-logged_out']}</option>
			</select>
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
