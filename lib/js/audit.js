
jQuery(document).ready( function($) {

// **************************************************************
//  get the jQuery version and add it to the page
// **************************************************************

    $('div.reaktiv-audit-wrap').each(function() {

		var version;
		var updated;

		version	= $().jquery;
		updated	= $(this).find('textarea#reaktiv-audit-textarea').val().replace('MYJQUERYVERSION', version);

		$('textarea#reaktiv-audit-textarea').val(updated);

    });

//********************************************************
// highlight stuff on click
//********************************************************

	$('div.reaktiv-audit-wrap').on('click', 'input#reaktiv-highlight', function (event) {

		var infobox	= $('div.reaktiv-audit-wrap').find('textarea#reaktiv-audit-textarea');

		$(infobox).focus();
		$(infobox).select();

	});

//********************************************************
// you're still here? it's over. go home.
//********************************************************

});
