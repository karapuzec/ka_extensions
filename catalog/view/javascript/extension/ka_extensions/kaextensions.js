/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	The class of the library object ka_extensions (created in common.js): messages, labels. The labels
	are the English defaults; the language values come from the footer (setLabels(),
	getLabel()).
*/

class KaExtensions {

	constructor() {

		this.labels = {
			'txt_info'   : 'Info',
			'txt_success': 'Success',
			'txt_warning': 'Warning',
			'txt_error'  : 'Error',
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
				$('body').prepend(`
					<div id="ka-alert"></div>
				`);
			}

			// add the message
			//
			var str = `
		    	<div class="alert alert-${style} alert-dismissible" role="alert">
		    		<i class="fa ${icon}"></i>
		    		<div class="l-alert-content">${text}</div>
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
	}
}
