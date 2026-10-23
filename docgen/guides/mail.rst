Sending Emails
==============

``\extension\ka_extensions\Mail`` sends emails from twig templates with the store mail settings
(engine, SMTP parameters, sender, logo). ``\KaMail`` is a deprecated alias of this class.

Basic Usage
-----------

.. code-block:: php

    $mail = new \extension\ka_extensions\Mail($language_id, $store_id);

    $mail->data['order_id'] = $order_id;

    $mail->send('', $email, 'Order %s is shipped', 'extension/mycomp/mymodule/mail/order_shipped');

Constructor parameters:

- ``$language_id`` - language id or code (``'en-gb'``); emails are rendered in this language.
  The registry object is still accepted for old modules;
- ``$store_id`` - store whose name, email, logo and URL are used (default ``0``).

``send($from, $to, $subject, $tpl, $extra = [])``:

- ``$from`` - sender email, the store email when empty;
- ``$subject`` - a language variable or text;
- ``$tpl`` - template path without the extension. ``<tpl>.twig`` is the HTML part,
  ``<tpl>_txt.twig`` is the text part; at least one of them must exist;
- ``$extra`` - ``headers`` (extra headers like ``Message-ID``, ``In-Reply-To``), ``reply_to``,
  ``header_template``, ``footer_template``, ``content_template``.

It returns ``false`` when no template is found.

Data available in templates: ``store_name``, ``sender``, ``store_url`` and everything added to
``$mail->data``. The store logo is available as ``logo``.

Common Header and Footer
------------------------

``sendContent($to, $subject, $tpl, $from = '', $extra = [])`` wraps your template into the common
header, content and footer templates:

::

    extension/ka_extensions/common/mail/header.twig, footer.twig, content.twig (+ _txt versions)

Other templates can be set with ``header_template``, ``footer_template``, ``content_template`` in ``$extra``.

Templates per Language
----------------------

A template with the language code suffix is used first if it exists, e.g. ``order_shipped_de-de.twig``,
otherwise ``order_shipped.twig``.

Attachments
-----------

.. code-block:: php

    $mail->addAttachment(DIR_DOWNLOAD . $filename, 'invoice.pdf');

The second parameter is the file name shown in the email (optional).

Settings and Logging
--------------------

Ka Extensions settings:

- **log emails** (``ka_extensions_log_emails``) - every email is written to ``ka_mail_log.log``
  in ``DIR_LOGS`` with the result;
- **images are enclosed** (``ka_extensions_mail_images_are_enclosed``) - images are attached to the email
  (``cid:``) instead of links to the store.

Errors
------

Sending errors are written to the store error log and suppressed, so a broken mail server does not break
the page. To handle them yourself, switch it off on the OpenCart mail object:

.. code-block:: php

    $mail->mail->setSuppressExceptions(false);
    try {
        $mail->send('', $email, $subject, $tpl);
    } catch (\Exception $e) {
        // ...
    }
