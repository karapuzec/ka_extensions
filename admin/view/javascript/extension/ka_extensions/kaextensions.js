/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	The class of the library object ka_extensions (created in common.js): messages, labels, overlays,
	posting of urls. The labels are the English defaults; the language values come from the footer
	(setLabels(), getLabel()).
*/

class KaExtensions {

	constructor() {

		this.labels = {
			'txt_info'   : 'Info',
			'txt_success': 'Success',
			'txt_warning': 'Warning',
			'txt_error'  : 'Error',
			// the confirmation of legacy delete links (the same text as the macro button_row_delete)
			'txt_confirm': 'Are you sure?',
			'txt_field_validation_error': 'Some data is not filled in. Please check the form fields: '
		};

		/*
			Returns a label: the language value set by setLabels() (the footer of the page), the English
			default of this.labels, the key itself for an unknown label
		*/
		this.getLabel = (key) => {
			return this.labels[key] ?? key;
		}

	
		this.resetForm = (id) => {
			$('#' + id + ' input, #' + id + ' select').val('');
			location = $('#' + id).attr('action');
		}
	

		this.showMessage =(text, type = 'I', delay = 5000) => {    
	
	    	var labels = this.labels;
    
			var style = 'info';
			var title = this.getLabel('txt_info');
			var icon  = 'fa-info-circle';

			if (type) {
				if (type == 'D' || type == 'E') {
					style = 'danger';
					title = this.getLabel('txt_error');
					icon  = 'fa-exclamation-circle';
				} else if (type == 'S') {
					style = 'success';
					title = this.getLabel('txt_success');
					icon  = 'fa-check-circle';
				
				} else if (type == 'W') {
					style = 'warning';
					title = this.getLabel('txt_warning');
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

		/*
			Merges language labels (key => text) into this.labels, later calls override earlier values
			(like array_merge). The footer of the page calls it with the labels of Document::addKaJsLabels(),
			a module can call it with its own labels.
		*/
		this.setLabels = (labels) => {
			Object.assign(this.labels, labels);
		}

		// $.ka.alert(text, type) - a short form of showMessage()
		//
		if (!$.ka) {
			$.ka = {};
		}
		$.ka.alert = (text, type) => {
			this.showMessage(text, type);
		}

		/*
			Links with the data-ka-post attribute are posted (after the data-confirm question when it is set)
			instead of opening them.

			<a href="..." data-ka-post="1" data-confirm="Are you sure?">Delete</a>

			Legacy row delete links of list templates (a.btn-danger to .../deleteRecord without onclick and
			data-ka-post) are posted too after the default question: deleteRecord() accepts POST only since
			4.1.1.29. Other links are not changed. The handler is delegated to the document, so it works for
			links added later and when the object is created in the head of the page.
		*/
		var self = this;
		$(document).on('click', 'a[data-ka-post], a.btn-danger[href*="/deleteRecord"]:not([onclick])', function(event) {

			event.preventDefault();

			var confirm_text = $(this).attr('data-confirm');
			if (typeof $(this).attr('data-ka-post') == 'undefined') {
				confirm_text = self.getLabel('txt_confirm');
			}
			if (confirm_text && !confirm(confirm_text)) {
				return;
			}

			self.postUrl($(this).attr('href'));
		});
	}
}
