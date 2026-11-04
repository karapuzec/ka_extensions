/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

var ka_extensions = new function () {

	this.labels = {
		'txt_info'   : 'Info',
		'txt_success': 'Success',
		'txt_warning': 'Warning',
		'txt_error'  : 'Error'
	};

	
	this.resetForm = (id) => {
		$('#' + id + ' input, #' + id + ' select').val('');
		location = $('#' + id).attr('action');
	}
	

	this.showMessage =(text, type = 'I', delay = 5000) => {    
	
    	var labels = this.labels;
    
		var style = 'info';
		var title = labels['txt_info'];
		var icon  = 'fa-info-circle';

		if (type) {
			if (type == 'D' || type == 'E') {
				style = 'danger';
				title = labels['txt_error'];
				icon  = 'fa-exclamation-circle';
			} else if (type == 'S') {
				style = 'success';
				title = labels['txt_success'];
				icon  = 'fa-check-circle';
				
			} else if (type == 'W') {
				style = 'warning';
				title = labels['txt_warning'];
				icon  = 'fa-exclamation-triangle';
			}
		}

		// add the alert container when it is not available
		//
		if ($('#ka-alert').length == 0) {
			$('#container').prepend(`
				<div id="ka-alert"></div>
			`);
		}

		// add the message
		//
		var str = `
	    	<div class="alert alert-${style} alert-dismissible" role="alert">
	    		<i class="fa ${icon}"></i>
	    		${text}
	    		<button type="button" class="close" data-dismiss="alert" aria-label="Close">
	    			<span aria-hidden="true">&times;</span>
	    		</button>
	    	</div>
	    `;
	    	
		$('#ka-alert').prepend(str);

		if (delay) {
			window.setTimeout(function() {
				$('.alert-dismissible').fadeTo(1000, 0, function() {
					$(this).remove();
				});
			}, delay);
		}
	}	
	
	/*
		Sends a POST request to the url as a hidden form (the page is reloaded with the answer).
		Used for links which change data, e.g. the delete buttons of list rows.
	*/
	this.postUrl = (url) => {
		$('<form method="post" style="display: none"></form>').attr('action', url).appendTo(document.body).submit();
	}

		this.overlays = new Array();
	
	this.showOverlay = (selector) => {
	
		$(selector).each((idx, el) => {
			
			var $el = $(el);
			var offset = $el.offset();
			var width  = $el.css('width');
			var height = $el.css('height');
			
			var $div = $('<div>').css({
				position: 'absolute',
				top: offset.top,
				left: offset.left,
				width: width,
				height: height,
				'background-color': 'rgba(0,0,0,0.5)',
				'z-index': 3900,
			});
			
			var ka_overlay_id = this.overlays.length;
			$div.attr('id', 'ka_overlay_' + ka_overlay_id).appendTo(document.body);
			this.overlays[ka_overlay_id] = $div;
			$el.data('ka_overlay_id', ka_overlay_id);
		});
	}
	
	
	this.removeOverlay = (selector) => {
	
		$(selector).each((idx, el) => {
		
			var $el = $(el);
			
			var ka_overlay_id = $el.data('ka_overlay_id');
			if (typeof ka_overlay_id == 'undefined' || ka_overlay_id === false) {
				return;
			}
			
			$('[data=ka_overlay_id]').removeAttr('ka_overlay_id');
			
			$('#ka_overlay_' + ka_overlay_id).remove();
		
			this.overlays[ka_overlay_id] = null;
		})
	}
}


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
	instead of opening them. Other links are not changed.

	<a href="..." data-ka-post="1" data-confirm="Are you sure?">Delete</a>
*/
$(document).on('click', 'a[data-ka-post]', function(event) {

	event.preventDefault();

	var confirm_text = $(this).attr('data-confirm');
	if (confirm_text && !confirm(confirm_text)) {
		return;
	}

	ka_extensions.postUrl($(this).attr('href'));
});
