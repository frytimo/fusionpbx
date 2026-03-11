<style>
	#test_result_layer {
		z-index: 999999;
		position: absolute;
		left: 0px;
		top: 0px;
		right: 0px;
		bottom: 0px;
		text-align: center;
		vertical-align: middle;
		}
	#test_result_container {
		display: block;
		overflow: auto;
		background-color: #fff;
		padding: 25px 25px;
		{if $is_mobile}margin: 0;{else}margin: auto 10%;{/if}
		text-align: left;
		-webkit-box-shadow: 0px 1px 20px #888;
		-moz-box-shadow: 0px 1px 20px #888;
		box-shadow: 0px 1px 20px #888;
		}
</style>

<div id='test_result_layer' style='display: none;'>
	<table cellpadding='0' cellspacing='0' border='0' width='100%' height='100%'>
		<tr>
			<td align='center' valign='middle'>
				<span id='test_result_container'></span>
			</td>
		</tr>
	</table>
</div>

{include file='partials/_action_bar.tpl'}
{include file='partials/_modals.tpl'}
{include file='partials/_table.tpl'}

<script>
	$('#form_test').submit(function(event) {
		event.preventDefault();
		$.ajax({
			url: $(this).attr('action'),
			type: $(this).attr('method'),
			data: new FormData(this),
			processData: false,
			contentType: false,
			cache: false,
			success: function(response){
				$('#test_result_container').html(response);
				$('#test_result_layer').fadeIn(400);
				$('span#form_test').fadeOut(400, function(){
					$('#test_button').fadeIn(400);
					$('#to').val('');
				});
			}
		});
	});
</script>
