<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
*/

namespace extension\ka_extensions;

/**
	Occurs when a code calls response->redirect method but the redirect is disabled by a caller.
	It has to be handled by the caller to properly process the redirects.
	
	@package Service
*/
class ExceptionRedirect extends \Exception {

}