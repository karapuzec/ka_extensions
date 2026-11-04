/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	The object ka_extensions of the library. The class KaExtensions is in kaextensions.js, it is added to
	the page before this file.
*/

var ka_extensions = new KaExtensions();


(function ($) {

	if (!$.ka) {
		$.ka = {};
	}

    $.ka.alert = function(text, type) {
    	ka_extensions.showMessage(text, type);
    }
    
}(jQuery));


/*
	Links with the data-ka-post attribute are posted (after the data-confirm question when it is set)
	instead of opening them.

	<a href="..." data-ka-post="1" data-confirm="Are you sure?">Delete</a>

	Legacy row delete links of list templates (a.btn-danger to .../deleteRecord without onclick and
	data-ka-post) are posted too after the default question: deleteRecord() accepts POST only since
	4.1.1.29. Other links are not changed.
*/
$(document).on('click', 'a[data-ka-post], a.btn-danger[href*="/deleteRecord"]:not([onclick])', function(event) {

	event.preventDefault();

	var confirm_text = $(this).attr('data-confirm');
	if (typeof $(this).attr('data-ka-post') == 'undefined') {
		confirm_text = ka_extensions.getLabel('txt_confirm');
	}
	if (confirm_text && !confirm(confirm_text)) {
		return;
	}

	ka_extensions.postUrl($(this).attr('href'));
});
