jQuery(document).ready(function($){
	$('.subsubsub').append('<li><a id="easyyoutube" href="#">Get EasyYoutube Shortcode</a>');
	$('#easyyoutube').on('click',function(e){
		e.preventDefault(); var ids = [];
		$('#the-list input[id^=cb-select]:checked').each(function(){
			ids.push($(this).val());
		});
		if(ids.length>0)
			alert('[easyyoutube ids="'+ids.toString()+'"]');
	});
});