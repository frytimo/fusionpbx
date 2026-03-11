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
	if(f.xmlHttp != null){
		f.el = document.getElementById(id);
		f.xmlHttp.open("GET",url,true);
		f.xmlHttp.onreadystatechange = function(){f.stateChanged();};
		f.xmlHttp.send(null);
	}
}

loadXmlHttp.prototype.stateChanged=function () {
	var url = new URL(this.xmlHttp.responseURL);
	if (/login\.php$/.test(url.pathname)) {
		// You are logged out. Stop refresh!
		url.searchParams.set('path', '{/literal}{$request_uri|escape:'javascript'}{literal}');
		window.location.href = url.href;
		return;
	}

	if (this.xmlHttp.readyState == 4 && (this.xmlHttp.status == 200 || !/^http/.test(window.location.href)))
		document.getElementById('ajax_response').innerHTML = this.xmlHttp.responseText;

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
}

var requestTime = function() {
	var url = 'conferences_active_inc.php';
	new loadXmlHttp(url, 'ajax_response');
	setInterval(function(){new loadXmlHttp(url, 'ajax_response');}, 1777);
}

if (window.addEventListener) {
	window.addEventListener('load', requestTime, false);
}
else if (window.attachEvent) {
	window.attachEvent('onload', requestTime);
}
</script>
{/literal}
{include file='partials/_action_bar.tpl'}
{include file='partials/_modals.tpl'}
{include file='partials/_table.tpl'}
