<form id='form_list' method='post'>
<input type='hidden' id='action' name='action' value=''>
<input type='hidden' name='search' value="{$search|escape}">
<input type='hidden' name='profile' value='{$profile|escape}'>

<div class='card'>
<table class='list'>
<tr class='list-header'>
	<th class='checkbox'>
		<input type='checkbox' id='checkbox_all' name='checkbox_all' onclick='list_all_toggle();'{if empty($registrations)} style='visibility: hidden;'{/if}>
	</th>
	<th>{$text['label-user']}</th>
	<th class='pct-25'>{$text['label-agent']}</th>
	<th class='hide-md-dn'>{$text['label-contact']}</th>
	<th class='hide-sm-dn'>{$text['label-lan_ip']}</th>
	<th class='hide-sm-dn'>{$text['label-ip']}</th>
	<th class='hide-sm-dn'>{$text['label-port']}</th>
	<th class='hide-md-dn'>{$text['label-hostname']}</th>
	<th class='pct-35' style='width: 35%;'>{$text['label-status']}</th>
	<th class='hide-md-dn'>{$text['label-ping']}</th>
	<th class='hide-md-dn'>{$text['label-sip_profile_name']}</th>
	<td class='action-button'>&nbsp;</td>
</tr>

{foreach from=$registrations item=row}
<tr class='list-row' href='#'>
	<td class='checkbox'>
		<input type='checkbox' name='registrations[{$row._index}][checked]' id='checkbox_{$row._index}' value='true' onclick="if (!this.checked) { document.getElementById('checkbox_all').checked = false; }">
		<input type='hidden' name='registrations[{$row._index}][user]' value='{$row.user|escape}' />
		<input type='hidden' name='registrations[{$row._index}][profile]' value='{$row.sip_profile_name|escape}' />
		<input type='hidden' name='registrations[{$row._index}][agent]' value='{$row.agent|escape}' />
		<input type='hidden' name='registrations[{$row._index}][host]' value='{$row.host|escape}' />
		<input type='hidden' name='registrations[{$row._index}][domain]' value='{$row['sip-auth-realm']|escape}' />
	</td>
	<td class=''>{$row._user_html}</td>
	<td class='' title="{$row.agent|escape}"><span class='cursor-help'>{$row.agent|escape}</span></td>
	<td class='hide-md-dn' title='{$row.contact|escape}'>{$row._contact_display}</td>
	<td class='hide-sm-dn no-link'><a href='https://{$row._lan_ip_url}' target='_blank'>{$row['lan-ip']|escape}</a></td>
	<td class='hide-sm-dn no-link'><a href='https://{$row._network_ip_url}' target='_blank'>{$row['network-ip']|escape}</a></td>
	<td class='hide-sm-dn'>{$row['network-port']|escape}</td>
	<td class='hide-md-dn'>{$row.host|escape}</td>
	<td class='' title="{$row.status|escape}"><span class='cursor-help'>{$row._status|escape}</span></td>
	<td class='hide-md-dn'>{$row['ping-time']|escape}</td>
	<td class='hide-md-dn' nowrap='nowrap'>{$row.sip_profile_name|escape}</td>
	<td class='action-button'>
		{$row._tools_html}
	</td>
</tr>
{/foreach}

</table>
</div>
<br />
<div align='center'>{$paging_controls}</div>
<input type='hidden' name='{$token.name}' value='{$token.hash}'>
</form>
