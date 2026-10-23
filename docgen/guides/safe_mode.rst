'Safe Mode' with Ka Extensions
==============================

Ka Extensions for OpenCart 4 and Ka Extensions for OpenCart 3 (since version 4.1.1.0)
include a safe operation mode ("Safe Mode") for administrators. It can be used when
the store has completely crashed and other recovery options do not work.

Safe Mode works in the admin area only, the storefront is not affected. In Safe Mode the
admin uses default OpenCart files with minimal inclusion of modified files. It ignores:

- Files in the ``kamod`` cache (the Ka Extensions page is not available)
- Modification cache for controllers, models and libraries (for OpenCart 3). The modified
  ``system/startup.php`` is still used, it starts Safe Mode
- VQMod files (partially)

This mode should only be used for fixing or adjusting store settings. Avoid removing
the Ka Extensions library while in Safe Mode, as this will immediately disable the mode.

How to Activate Safe Mode
-------------------------

To open the store in Safe Mode, add the ``route=ka_safe_mode`` parameter to the
store admin URL.

For example, if your admin URL is:

::

   https://www.mystore.com/admin/index.php

Then open:

::

   https://www.mystore.com/admin/index.php?route=ka_safe_mode

Additional Parameter for OpenCart 3
-----------------------------------

In Ka Extensions version 4.1.1.3 (for OpenCart 3) and newer, an additional
``code`` parameter is required. This code is defined on the *Ka Extensions settings* page
(latin letters and numbers). Safe Mode is disabled while the code is empty.
The code is stored in ``system/config/kamod.php``.

The URL will look like this (replace ``NNNNN`` with your actual code):

::

   https://www.mystore.com/admin/index.php?route=ka_safe_mode&code=NNNNN

Login Behavior
--------------

When Safe Mode is activated, you will be prompted to log in with an administrator account.
After a successful login the dashboard is opened in Safe Mode.

How to Exit Safe Mode
---------------------

Safe Mode is kept by the ``ka_safe_mode_code`` browser cookie until the browser is closed.
To exit Safe Mode, close your browser and open it again, or delete the cookie.
Changing or clearing the code on the settings page ends Safe Mode for all browsers.
