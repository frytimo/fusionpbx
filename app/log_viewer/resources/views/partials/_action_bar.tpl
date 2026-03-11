<div class="action_bar" id="action_bar">
	<div class="heading">
		<b>{$text['title-log_viewer']|escape}</b>
	</div>
	<div class="actions">
		<form name="frm" id="frm" method="post" action="log_viewer.php">
			<select name="n" class="formfld" style="width: auto;">
				{$file_options_html}
			</select>
			<input class="formfld" style="width: 175px; margin-left: 15px;" type="text" name="filter" maxlength="255" value="{$filter|escape}" placeholder="{$text['label-filter']|escape}">
			<input type="checkbox" name="line_number" value="1" {if $line_number == 1}checked="checked"{/if}> {$text['label-line_number']|escape}
			<input type="checkbox" name="sort" value="desc" {if $sort == 'desc'}checked="checked"{/if}> {$text['label-sort']|escape}
			<select name="size" class="formfld" style="width: auto; margin-left: 15px;">
				<option value="32" {if $size == '32'}selected="selected"{/if}>32 {$text['label-kb']|escape}</option>
				<option value="64" {if $size == '64'}selected="selected"{/if}>64 {$text['label-kb']|escape}</option>
				<option value="128" {if $size == '128'}selected="selected"{/if}>128 {$text['label-kb']|escape}</option>
				<option value="256" {if $size == '256'}selected="selected"{/if}>256 {$text['label-kb']|escape}</option>
				<option value="512" {if $size == '512' || !$size}selected="selected"{/if}>512 {$text['label-kb']|escape}</option>
				<option value="1024" {if $size == '1024'}selected="selected"{/if}>1 {$text['label-mb']|escape}</option>
				<option value="2048" {if $size == '2048'}selected="selected"{/if}>2 {$text['label-mb']|escape}</option>
				<option value="5120" {if $size == '5120'}selected="selected"{/if}>5 {$text['label-mb']|escape}</option>
				<option value="10240" {if $size == '10240'}selected="selected"{/if}>10 {$text['label-mb']|escape}</option>
				<option value="max" {if $size == 'max'}selected="selected"{/if}>{$text['label-max']|escape}</option>
			</select>
			{$btn_update}
			{$btn_download}
		</form>
	</div>
	<div style="clear: both;"></div>
</div>
