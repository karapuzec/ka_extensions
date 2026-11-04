/*
	$Project$
	$Author$

	$Version$ ($Revision$)

	DEPRECATED. The library does not add this file to the pages any more: the header adds kaextensions.js
	(the class KaExtensions) and creates the object ka_extensions. The file is kept for third-party code
	that includes it: it creates the object only when it does not exist yet (kaextensions.js has to be
	loaded before it).
*/

if (typeof ka_extensions === 'undefined' && typeof KaExtensions !== 'undefined') {
	var ka_extensions = new KaExtensions();
}
