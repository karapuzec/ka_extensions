FAQ
===

Why are module settings controllers stored in ``controller/extension/ka_extensions/``?
--------------------------------------------------------------------------------------

The list of modules on the *Extensions > Ka Extensions* page is built by scanning
installer files of a vendor directory:

::

    admin/controller/extension/<vendor>/*.php

Every ``*.php`` file there is treated as a module installer (settings controller).
The module's own files (subcontrollers, kamod classes) are kept in the
``admin/controller/extension/<vendor>/<module>/`` subdirectory, so they do not appear in that list.
This layout is required by the architecture of the extension list.

Where are generated kamod classes stored?
-----------------------------------------

In ``system/storage/cache.kamod/``. You can browse it to see the class hierarchy that kamod generated.
The cache is rebuilt by *Extensions > Modifications > Refresh*, on the Ka Extensions page or on every
request when ``KAMOD_DEBUG`` is defined (see :doc:`useful_constants`).

My kamod class is ignored. What should I check?
-----------------------------------------------

- the module directory has the ``admin/controller/extension/<vendor>/<module>/active.kamod`` file (kamod is enabled for the module) and ``kamod.ini``
  (see :doc:`kamod_ini`);
- the kamod cache was refreshed;
- the store is not in :doc:`safe_mode`.
