/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	The class of the library object ka_extensions (created at the end of this file): messages, labels. The labels
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
			default of this.labels, the key itself for an unknown label.
			params - optional substitutions like the PHP Language::getka($key, $params) of the library
			(str_replace): every key of the object is replaced with its value in the text, in the order
			of the keys, all occurrences, e.g.
				getLabel('txt_deleted', {'%count%': 5})   // 'Deleted: %count%' -> 'Deleted: 5'
		*/
		this.getLabel = (key, params) => {
			let text = this.labels[key] ?? key;
			if (params && typeof params === 'object') {
				text = String(text);
				for (const [search, value] of Object.entries(params)) {
					if (search !== '') {
						text = text.split(search).join(String(value));
					}
				}
			}
			return text;
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

		/*
			Form validation of the library: called by the footer of the page (ka_extensions.initFormValidation()),
			unless KA_DISABLE_FORM_VALIDATION is defined. When the page is loaded, every form named by the form
			attribute of a button (<button form="form-id">, e.g. the Save buttons of the page header) gets a submit
			handler: when a [required] field of the form is not valid (checkValidity()), the submit is cancelled and
			the error message txt_field_validation_error is shown with the names of the fields (the label.control-label
			of the .form-group of the field, the data-title attribute, the name attribute or the tag name). A second call does nothing.
		*/
		this.initFormValidation = () => {

			if (this.form_validation_initialized) {
				return;
			}
			this.form_validation_initialized = true;

			$(document).ready(() => {

				$('button[form]').each((idx, button) => {

					var formid = $(button).attr('form');
					var $form = $('#' + formid);
					if (!$form) {
						return;
					}

					$form.on('submit', (e) => {

						var $form = $(e.currentTarget);

						var error = '';

						$form.find('[required]').each((idx, el) => {
							if (!el.checkValidity()) {
								var name = $(el).closest('.form-group').find('label.control-label').text();

								if (!name) {
									name = $(el).data('title');
								}
								if (!name) {
									name = $(el).attr('name');
								}
								if (!name) {
									name = el.tagName;
								}

								if (error) {
									error += ', ';
								}
								error += (name);
							}
						});

						if (error) {
							this.showMessage(this.getLabel('txt_field_validation_error') + error, 'E');
							e.preventDefault();
							return;
						}
					});
				});
			});
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

// The library object is created by this file, so it exists with any theme (also a theme with its own
// header.twig / footer.twig without the library parts), only when it is missing (one object).
if (typeof ka_extensions === 'undefined') {
	var ka_extensions = new KaExtensions();
}
