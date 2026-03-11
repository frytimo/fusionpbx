{literal}<script type="text/javascript">
	//define refresh function, initial start
	var refresh = 1980;
	var source_url = 'calls_active_inc.php?';
	var timer_id;
{/literal}
{if $show == 'all'}
	{literal}source_url = source_url + '&show=all';{/literal}
{/if}
{if $debug}
	{literal}source_url = source_url + '&debug';{/literal}
{/if}
{literal}
	function ajax_get() {
		url = source_url + '&eavesdrop_dest=' + ((document.getElementById('eavesdrop_dest')) ? document.getElementById('eavesdrop_dest').value : '');
		$.ajax({
			url: url,
			success: function(response){
				$("#ajax_response").html(response);
				const table = document.getElementById('calls_active');
				var row_count = table.rows.length;
				if (row_count > 0) { row_count = row_count - 1; }
				const calls_active_count = document.getElementById('calls_active_count');
				calls_active_count.innerHTML = row_count;
			}
		});
		timer_id = setTimeout(ajax_get, refresh);
	}

	refresh_start();

//refresh controls
	function refresh_stop() {
		clearTimeout(timer_id);
		document.getElementById('refresh_state').innerHTML = "{/literal}{$btn_refresh_paused|escape:'javascript'}{literal}";
	}

	function refresh_start() {
		if (document.getElementById('refresh_state')) { document.getElementById('refresh_state').innerHTML = "{/literal}{$btn_refresh_active|escape:'javascript'}{literal}"; }
		ajax_get();
	}

//eavesdrop call
	function eavesdrop_call(ext, chan_uuid) {
		if (ext != '' && chan_uuid != '') {
			cmd = get_eavesdrop_cmd(ext, chan_uuid, document.getElementById('eavesdrop_dest').value);
			if (cmd != '') {
				send_cmd(cmd);
			}
		}
	}

	function get_eavesdrop_cmd(ext, chan_uuid, destination) {
		url = "calls_exec.php?action=eavesdrop&ext=" + ext + "&chan_uuid=" + chan_uuid + "&destination=" + destination;
		return url;
	}

//used by eavesdrop function
	function send_cmd(url) {
		if (window.XMLHttpRequest) {
			xmlhttp=new XMLHttpRequest();
		}
		else {
			xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.open("GET",url,false);
		xmlhttp.send(null);
		document.getElementById('cmd_response').innerHTML=xmlhttp.responseText;
	}
</script>
{/literal}
{include file='partials/_action_bar.tpl'}
{include file='partials/_modals.tpl'}
{include file='partials/_table.tpl'}
