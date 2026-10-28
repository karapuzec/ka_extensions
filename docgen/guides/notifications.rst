Admin Notifications
===================

Ka Extensions adds a bell to the header of the OpenCart 3 admin area (next to the Kamod Health
Status icon). A module puts a notification there with ``\extension\ka_extensions\Notifications``,
e.g. when a limit is reached or a scheduled task fails.

.. code-block:: php

    use \extension\ka_extensions\Notifications;

    // for the logged in administrator, until he closes it
    Notifications::add('mymodule_import_failed', 'My Module: the import has failed, see the log.');

    // for all administrators, until somebody closes it or for one day
    Notifications::addForAll('mymodule_daily_limit', 'My Module: the daily limit is reached.', 86400);

    $text = Notifications::get('mymodule_daily_limit'); // the text or null

    Notifications::remove('mymodule_daily_limit');

- ``add(string $code, string $text, $ttl = 0)`` adds a notification for the logged in administrator.
  Without a logged in administrator (in the catalog, in a script) it is stored for all
  administrators, as ``addForAll()`` does. A second call with the same code updates the text,
  the date and the lifetime.
- ``addForAll(string $code, string $text, $ttl = 0)`` adds a notification for all administrators
  (``user_id`` = 0). There is one notification for everybody: when one administrator closes it,
  it is deleted for all of them.
- ``remove(string $code)`` removes the notification with this code of the logged in administrator
  and the one for all administrators.
- ``get(string $code)`` returns the text of the notification of the logged in administrator or,
  when there is none, of the one for all administrators; ``null`` when there is none or it has expired.
- ``$ttl`` is the lifetime in seconds, ``0`` means until an administrator closes the notification.
  Expired notifications are not shown and are deleted on the next write.

The text is a ready string in the language of the admin area made by the module (e.g. with
``$this->language->get()`` and ``sprintf()``). It is escaped on output: no HTML and no links.

The bell is shown only when there are notifications for the administrator and only to administrators
with access to the *Extensions > Ka Extensions* page. The cross of a notification deletes it
(a POST request to ``extension/extension/ka_extensions/deleteNotification``, the modify permission
of Ka Extensions is required).

The methods work in the admin area and in the catalog. The notifications are stored in the table
``ka_notification``; it is created when the *Ka Extensions* page is opened or on the first ``add()``.
Without the table the header shows no bell and ``get()`` returns ``null``.
