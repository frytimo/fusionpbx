<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-fax_queue']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_back}
		{$btn_resend}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			{if $permission.fax_queue_all}
				{if $show == 'all'}
					<input type='hidden' name='show' value='all'>
				{else}
					{$btn_show_all}
				{/if}
			{/if}
			<select class='formfld' name='fax_status' style='margin-left: 15px;'>
				<option value='' selected='selected' disabled hidden>{$text['label-fax_status']}...</option>
				<option value=''></option>
				<option value='waiting'{if $fax_status == 'waiting'} selected='selected'{/if}>{$text['label-waiting']|capitalize}</option>
				<option value='sending'{if $fax_status == 'sending'} selected='selected'{/if}>{$text['label-sending']|capitalize}</option>
				<option value='trying'{if $fax_status == 'trying'} selected='selected'{/if}>{$text['label-trying']|capitalize}</option>
				<option value='sent'{if $fax_status == 'sent'} selected='selected'{/if}>{$text['label-sent']|capitalize}</option>
				<option value='busy'{if $fax_status == 'busy'} selected='selected'{/if}>{$text['label-busy']|capitalize}</option>
				<option value='failed'{if $fax_status == 'failed'} selected='selected'{/if}>{$text['label-failed']|capitalize}</option>
			</select>
			<input type='text' class='txt list-search' style='margin-left: 0;' name='search' id='search' value="{$search|escape}" placeholder="{$text['label-search']|escape}">
			{$btn_search}
			{if $paging_controls_mini != ''}
				<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
			{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
