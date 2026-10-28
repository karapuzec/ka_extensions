<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	Builds multipart/related for inline images of an email. It is called by the OpenCart mail adaptors
	patched by the library (ka_extensions/system/library/mail/mail.php.xml and smtp.php.xml) only when
	the email has an html part and inline images added by Mail::addInlineImage().

	@internal
*/
class MailInline {

	/*
		Wraps the first part of an OpenCart email (multipart/alternative of the text and html parts)
		into multipart/related and appends the inline images to it.

		$message     - the email body built by the adaptor before the attachments
		$boundary    - the boundary of the top multipart/mixed part
		$attachments - [<name> => <file>] attachments of the email
		$inline      - [<name> => ['mime' => <mime type>]] inline images

		The line ending of the message is kept ("\r\n" or "\n"). An unknown structure of the message
		is returned unchanged.
	*/
	public static function wrapRelated($message, $boundary, $attachments, $inline) {

		$head = '--' . $boundary;
		if (strpos($message, $head) !== 0) {
			return $message;
		}

		$eol = (substr($message, strlen($head), 2) == "\r\n") ? "\r\n" : "\n";
		$head .= $eol;

		$related = $boundary . '_rel';

		$result  = $head;
		$result .= 'Content-Type: multipart/related; type="multipart/alternative"; boundary="' . $related . '"' . $eol . $eol;
		$result .= '--' . $related . $eol;
		$result .= substr($message, strlen($head));

		foreach ($attachments as $name => $file) {
			if (empty($inline[$name]['mime']) || !is_file($file)) {
				continue;
			}

			$content = file_get_contents($file);
			if ($content === false) {
				continue;
			}

			$result .= '--' . $related . $eol;
			$result .= 'Content-Type: ' . $inline[$name]['mime'] . '; name="' . $name . '"' . $eol;
			$result .= 'Content-Transfer-Encoding: base64' . $eol;
			$result .= 'Content-Disposition: inline; filename="' . $name . '"' . $eol;
			$result .= 'Content-ID: <' . urlencode($name) . '>' . $eol . $eol;
			$result .= chunk_split(base64_encode($content), 76, $eol);
		}

		$result .= '--' . $related . '--' . $eol;

		return $result;
	}
}
