<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>

{foreach from=$streams item=row}
	{if $row._show_category}
		<b><i>{$row.music_on_hold_name|escape}</i></b>{if $row._is_global}&nbsp;&nbsp;&nbsp;({$text['label-global']}){/if}<br />
	{/if}
	<div class='card'>
		<table class='list'>
			<tr class='list-header'>
				{if $has_music_on_hold_delete}
				<th class='checkbox'>
					<input type='checkbox' id='checkbox_all_{$row.music_on_hold_uuid|escape}' name='checkbox_all' onclick="list_all_toggle('{$row.music_on_hold_uuid|escape}'); document.getElementById('checkbox_all_{$row.music_on_hold_uuid|escape}_hidden').value = this.checked ? 'true' : ''; checkbox_on_change(this);">
					<input type='hidden' id='checkbox_all_{$row.music_on_hold_uuid|escape}_hidden' name='moh[{$row.music_on_hold_uuid|escape}][checked]'>
				</th>
				{/if}
				{$th_domain_header}
				<th class='pct-50'>{$row._stream_details}</th>
				<th class='center shrink'>{$text['label-tools']}</th>
				<th class='right hide-xs no-wrap pct-20'>{$text['label-file-size']}</th>
				<th class='right hide-sm-dn pct-30'>{$text['label-uploaded']}</th>
			</tr>
			{foreach from=$row._files item=file}
			<tr class='list-row' id='recording_progress_bar_{$file.row_uuid}' style='display: none;' onclick="recording_seek(event,'{$file.row_uuid}')"><td id='playback_progress_bar_background_{$file.row_uuid}' class='playback_progress_bar_background' colspan='5'><span class='playback_progress_bar' id='recording_progress_{$file.row_uuid}'></span></td></tr>
			<tr class='list-row' style='display: none;'><td></td></tr>
			<tr class='list-row' href="javascript:recording_play('{$file.row_uuid}','{$file.file_name|escape:'url'}');">
				{if $has_music_on_hold_delete}
				<td class='checkbox'>
					<input type='checkbox' name='moh[{$row.music_on_hold_uuid|escape}][{$file.x}][checked]' id='checkbox_{$file.x}' class='checkbox_{$row.music_on_hold_uuid|escape}' value='true' onclick="checkbox_on_change(this); if (!this.checked) { document.getElementById('checkbox_all_{$row.music_on_hold_uuid|escape}').checked = false; }">
					<input type='hidden' name='moh[{$row.music_on_hold_uuid|escape}][{$file.x}][file_name]' value="{$file.file_name|escape}" />
				</td>
				{/if}
				{if $show == 'all' && $has_music_on_hold_all}
				<td>{$row._domain_label|escape}</td>
				{/if}
				<td class='overflow'>{$file.file_name|escape}</td>
				<td class='button center no-link no-wrap'>
					<audio id='recording_audio_{$file.row_uuid}' style='display: none;' preload='none' ontimeupdate="update_progress('{$file.row_uuid}')" onended="recording_reset('{$file.row_uuid}');" src='music_on_hold.php?action=download&id={$row.music_on_hold_uuid}&file={$file.file_name|escape:'url'}' type='{$file.file_type}'></audio>
					{$file.btn_play}
					{$file.btn_dl}
				</td>
				<td class='right no-wrap hide-xs'>{$file.file_size|escape}</td>
				<td class='right no-wrap hide-sm-dn'>{$file.file_date|escape}</td>
			</tr>
			{/foreach}
		</table>
	</div>
	<br />
{/foreach}

<input type='hidden' name='{$token.name|escape}' value='{$token.hash|escape}'>
</form>
