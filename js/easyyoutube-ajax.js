jQuery(document).ready(function($) {
	$('#easyyoutube_import').submit(function(e) {
		e.preventDefault();
		$('#easyyoutube_loading').show();
		$('#easyyoutube_refresh').attr('disabled', true);

		data = {
			action: 'wds_get_results',
			wds_yt_nonce: wds_vars.wds_yt_nonce
		};

		$.post(ajaxurl, data, function(response) {
			$('#easyyoutube_results').html(response);
			$('#easyyoutube_loading').hide();
			$('#easyyoutube_refresh').attr('disabled', false);
		});
	});
});
