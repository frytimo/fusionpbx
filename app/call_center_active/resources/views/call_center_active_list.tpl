{literal}<script type="text/javascript">
	function loadXmlHttp(url, id) {
		var f = this;
		f.xmlHttp = null;
		/*@cc_on @*/
		/*@if(@_jscript_version >= 5)
		try {f.ie = window.ActiveXObject}catch(e){f.ie = false;}
		@end @*/
		if (window.XMLHttpRequest&&!f.ie||/^http/.test(window.location.href))
			f.xmlHttp = new XMLHttpRequest();
		else if (/(object)|(function)/.test(typeof createRequest))
			f.xmlHttp = createRequest();
		else {
			f.xmlHttp = null;
			/*@cc_on @*/
			/*@if(@_jscript_version >= 5)
			try{f.xmlHttp=new ActiveXObject("Msxml2.XMLHTTP");}
			catch (e){try{f.xmlHttp=new ActiveXObject("Microsoft.XMLHTTP");}catch(e){f.xmlHttp=null;}}
			@end @*/
		}
		if(f.xmlHttp != null) {
			f.el = document.getElementById(id);
			f.xmlHttp.open("GET",url,true);
			f.xmlHttp.onreadystatechange = function(){f.stateChanged();};
			f.xmlHttp.send(null);
		}
	}

	loadXmlHttp.prototype.stateChanged=function () {
		var url = new URL(this.xmlHttp.responseURL);

		//logged out stop the refresh
		if (/login\.php$/.test(url.pathname)) {
			url.searchParams.set('path', '{/literal}{$request_uri|escape:'javascript'}{literal}');
			window.location.href = url.href;
			return;
		}

		if (this.xmlHttp.readyState == 4 && (this.xmlHttp.status == 200 || !/^http/.test(window.location.href))) {
			document.getElementById('ajax_response').innerHTML = this.xmlHttp.responseText;
		}

		//link table rows
		$('.tr_hover tr,.list tr').each(function(i,e) {
			$(e).children('td:not(.list_control_icon,.list_control_icons,.tr_link_void,.list-row > .no-link,.list-row > .checkbox,.list-row > .button,.list-row > .action-button)').on('click', function() {
				var href = $(this).closest('tr').attr('href');
				var target = $(this).closest('tr').attr('target');
				if (href) {
					if (target) { window.open(href, target); }
					else { window.location = href; }
				}
			});
		});

		//filter agent list based on status
		document.querySelectorAll('tr[data-agent-status]').forEach(table_row => {
			const filter = '{/literal}{$agent_status|escape:'javascript'}{literal}';

			if (filter === 'available' && !table_row.getAttribute('data-agent-status').includes('Available')) {
				table_row.style.display = 'none';
			}
			else if (filter === 'not_available' && table_row.getAttribute('data-agent-status').includes('Available')) {
				table_row.style.display = 'none';
			}
			else if (filter === 'on_break' && !table_row.getAttribute('data-agent-status').includes('On Break')) {
				table_row.style.display = 'none';
			}
			else if (filter === 'logged_out' && !table_row.getAttribute('data-agent-status').includes('Logged Out')) {
				table_row.style.display = 'none';
			}
		});
	}

	var requestTime = function() {
		var url = 'call_center_active_inc.php?queue_name={/literal}{$queue_name|escape:'url'}{literal}&name={/literal}{$name|escape:'url'}{literal}&agent_status={/literal}{$agent_status|escape:'url'}{literal}';
		new loadXmlHttp(url, 'ajax_response');
		setInterval(function(){new loadXmlHttp(url, 'ajax_reponse');}, {/literal}{$refresh}{literal});
	}

	if (window.addEventListener) {
		window.addEventListener('load', requestTime, false);
	}
	else if (window.attachEvent) {
		window.attachEvent('onload', requestTime);
	}

	function send_command(url) {
		if (window.XMLHttpRequest) {
			xmlhttp=new XMLHttpRequest();
		}
		else {
			xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
		}
		xmlhttp.open("GET", url, false);
		xmlhttp.send(null);
	}
</script>
{/literal}
{include file='partials/_action_bar.tpl'}
{include file='partials/_modals.tpl'}
{include file='partials/_table.tpl'}
