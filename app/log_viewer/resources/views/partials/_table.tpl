<div style="margin-top: 20px;">
	<table width="100%" border="0" cellpadding="6" cellspacing="0">
		<tr>
			<td style="background-color: #1c1c1c; padding: 15px; font-family: monospace; font-size: 12px; line-height: 1.4; word-break: break-all; word-wrap: break-word; white-space: pre-wrap;">
				{if $has_log_view}
					{$log_html}
				{else}
					<span style="color: #fff;">{$text['message-access_denied']|escape}</span>
				{/if}
			</td>
		</tr>
	</table>
</div>
