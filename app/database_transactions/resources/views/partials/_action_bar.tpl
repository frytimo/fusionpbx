<div class='action_bar' id='action_bar'>
	<div class='heading'><b>{$text['title-database_transactions']}</b><div class='count'>{$num_rows|number_format}</div></div>
	<div class='actions'>
		<form id='form_search' class='inline' method='get'>
			{if $users}
			<select class='formfld' name='user_uuid' onchange="document.getElementById('form_search').submit();">
				<option value=''>{$text['label-user']}...</option>
				<option value=''>{$text['label-all']}</option>
				{foreach from=$users key=uuid item=username}
				<option value='{$uuid|escape}'{if $user_uuid == $uuid} selected='selected'{/if}>{$username|escape}</option>
				{/foreach}
			</select>
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
