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
	

    this.showMessage =(text, type) => {
	
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
			$('body').prepend(`
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

	    window.setTimeout(function() {
	        $('.alert-dismissible').fadeTo(1000, 0, function() {
	            $(this).remove();
	        });
	    }, 5000);

	}	
}