<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['header-devices']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		{$btn_import}
		{$btn_export}
		{$btn_vendors}
		{$btn_profiles}
		{$btn_add}
		{$btn_toggle}
		{$btn_delete}
		<form id='form_search' class='inline' method='get'>
			{if $has_device_all}
				{if $show == 'all'}
					<input type='hidden' name='show' value='all'>
				{else}
					{$btn_show_all}
				{/if}
			{/if}
			<select class='formfld' name='fields' id='select_fields' style='width: auto; margin-left: 15px;' onchange="if (document.getElementById('search').value != '') { this.form.submit(); }">
				<option value='' selected='selected' disabled hidden>{$text['label-fields']}...</option>
				<option value=''></option>
				<option value=''>{$text['label-default']}</option>
				<option value='lines'{if $fields == 'lines'} selected='selected'{/if}>{$text['label-lines']}</option>
				<option value='keys'{if $fields == 'keys'} selected='selected'{/if}>{$text['label-keys']}</option>
				<option value='settings'{if $fields == 'settings'} selected='selected'{/if}>{$text['label-settings']}</option>
				<option value='all'{if $fields == 'all'} selected='selected'{/if}>{$text['label-all']}</option>
			</select>
			<input type='text' class='txt list-search' name='search' id='search' style='margin-left: 0 !important;' value="{$search|escape}" placeholder="{$text['label-search']}" onkeydown=''>
			{$btn_search}
			{if $paging_controls_mini != ''}
				<span style='margin-left: 15px;'>{$paging_controls_mini}</span>
			{/if}
		</form>
	</div>
	<div style='clear: both;'></div>
</div>
