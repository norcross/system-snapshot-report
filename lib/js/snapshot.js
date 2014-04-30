
jQuery(document).ready( function($) {

//********************************************************
// highlight stuff on click
//********************************************************

	$( 'div.system-snapshot-wrap' ).on( 'click', 'input.snapshot-highlight', function (event) {

		$( 'div.system-snapshot-wrap' ).find( 'textarea#system-snapshot-textarea' ).focus().select();

	});

//********************************************************
// you're still here? it's over. go home.
//********************************************************

});
