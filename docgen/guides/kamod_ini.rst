==============
kamod.ini File
==============

The Kamod engine only processes extensions that contain a ``kamod.ini`` file within their controller directory.

For example, for a module named **CSV Product Import** developed by **Karapuz Team**, the file must be located at:

.. code-block:: text

   admin/controller/extension/ka_extensions/csv_product_import/kamod.ini

*Where ``ka_extensions`` is the vendor directory and ``csv_product_import`` is the extension directory.*
In general the path is ``admin/controller/extension/<vendor>/<module>/kamod.ini``.

The file is read with the PHP function ``parse_ini_file()``, so the usual ini syntax applies: values with spaces,
commas or special characters should be quoted.

Fields
======

+------------------------+----------------------------------+--------------------------------------------------------------+
| Field                  | Type and accepted forms          | Example                                                      |
+========================+==================================+==============================================================+
| ``module``             | string                           | ``module = "CSV Product Import"``                            |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``depends_on``         | list of module codes: array      | ``depends_on[] = "ka_extensions/ka_messages"``               |
|                        | (recommended), a single code or  |                                                              |
|                        | a comma-separated string         |                                                              |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``install_before``     | list of module codes, the same   | ``install_before[] = "ka_extensions/ka_multivendor"``        |
|                        | forms as ``depends_on``          |                                                              |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``install_after``      | list of module codes, the same   | ``install_after[] = "ka_extensions/ka_smart_search"``        |
|                        | forms as ``depends_on``          |                                                              |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``documentation_url``  | string (URL)                     | ``documentation_url = "https://www.ka-station.com/docs"``    |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``extension_page_url`` | string (URL)                     | ``extension_page_url = "https://www.ka-station.com/module"`` |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``license_code``       | string                           | ``license_code = "csv_product_import@oc3"``                  |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``name``               | string, not used by the library  | ``name = "CSV Product Import"``                              |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``version``            | string, not used by the library  | ``version = "1.0.0.0"``                                      |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``author``             | string, not used by the library  | ``author = "Karapuz Team"``                                  |
+------------------------+----------------------------------+--------------------------------------------------------------+
| ``support_email``      | string, not used by the library  | ``support_email = "support@ka-station.com"``                 |
+------------------------+----------------------------------+--------------------------------------------------------------+

Other fields are ignored.

Module Codes
============

``depends_on``, ``install_before`` and ``install_after`` contain lists of module codes. A module code is
``<vendor>/<module>``, the same as the module directory under ``admin/controller/extension/``,
for example ``ka_extensions/ka_messages``. A code without a vendor is treated as a Karapuz module:
``ka_messages`` means ``ka_extensions/ka_messages``. Writing the full code is recommended. The library itself is
``ka_extensions/ka_extensions``, so ``install_after = "ka_extensions"`` means the library.

The recommended form is an array with one code per line:

.. code-block:: ini

   depends_on[] = "ka_extensions/ka_smart_search"
   depends_on[] = "ka_extensions/ka_messages"

A single code and a comma-separated string are accepted as well, array items may contain comma-separated codes too.
Spaces around codes, empty items and duplicates are ignored:

.. code-block:: ini

   install_after = "ka_extensions/ka_smart_search, ka_extensions/ka_messages"

Do not mix both forms for one field (``field = ...`` and ``field[] = ...``): ``parse_ini_file()`` keeps only the last one.

Version constraints are not supported in these fields. A module that needs a minimum version of another module
checks it in its own installer.

Field Details
=============

``module``
  The display name of the extension. It is shown in the error message when the administrator tries to uninstall
  a module that this extension depends on. When it is empty, the module code is shown.

``depends_on``
  Modules required by the extension. The list is checked only on installation and uninstallation
  in *Extensions > Ka Extensions*:

  * the extension cannot be installed until all listed modules are installed;
  * a listed module cannot be uninstalled while the extension is installed.

  ``depends_on`` does not change the order of the modules in the kamod class chain. Use ``install_after`` for that.

``install_before``
  Modules whose child classes should come *after* the child classes of the current extension in the kamod class chain.

``install_after``
  Modules whose child classes should come *before* the child classes of the current extension in the kamod class chain.

  ``install_before`` and ``install_after`` only set the order of child classes when several modules override
  the same class. The order is applied when the kamod cache is rebuilt. These fields do not change the installation
  order and they are not dependencies: the listed modules do not have to be installed. When none of the listed
  modules overrides a class, the extension keeps its usual position for that class.

  *Example:* both ``ka_extensions/ka_smart_search`` and ``ka_extensions/ka_smart_search_messages`` override
  the same controller. With

  .. code-block:: ini

     install_after[] = "ka_extensions/ka_smart_search"

  in ``ka_smart_search_messages/kamod.ini`` the class of ``ka_smart_search_messages`` is placed after the class of
  ``ka_smart_search`` in the chain, so it extends that class and its methods are called first.

``documentation_url``
  A link to the extension's documentation page. This link is displayed on the extensions list page in the back-end.

``extension_page_url``
  A link to the extension's marketplace page. This link is displayed on the extensions list page in the back-end.

``license_code``
  Optional. A full code of the 'license code' at ka-station.com server. It may contain a suffix after the '@'
  character. When it is empty, the module code is used.

``name``, ``version``, ``author``, ``support_email``
  Optional and currently not used by the library, reserved for future functionality. The library takes the module
  version from the ``$extension_version`` property of the module installer, not from ``version``.

Minimal Example
===============

Below is an example of a minimally sufficient ``kamod.ini`` file:

.. code-block:: ini

   module = "My test module1"
