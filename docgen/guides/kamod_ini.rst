==============
kamod.ini File
==============

The Kamod engine only processes extensions that contain a ``kamod.ini`` file within their controller directory. 

For example, for a module named **CSV Product Import** developed by **Karapuz Team**, the file must be located at:

.. code-block:: text

   /admin/extension/ka_extensions/csv_product_import/kamod.ini

*Where ``ka_extensions`` is the vendor directory and ``csv_product_import`` is the extension directory.*

Supported Fields
================

The ``kamod.ini`` file supports the following configuration fields:

``depends_on``
  Accepts either a string or an array. Each entry defines a required extension dependency.
  
  *Example:*
  .. code-block:: ini

     depends_on[] = 'ka_extensions/ka_scheduler'

``documentation_url``
  A text field containing a link to the extension's documentation page. This link is displayed on the extensions list page in the back-end.

``extension_page_url``
  A text field containing a link to the extension's marketplace page. This link is displayed on the extensions list page in the back-end.

``install_before``
  A comma-separated string (not an array) of extensions that should be installed *after* the current extension. These dependencies are optional; however, if they are present, the current extension will be positioned earlier in the execution chain. This directly affects the parent/child class inheritance chain in method calls.

  *Example:*
  .. code-block:: ini

     install_before = "ka_extensions/extension1,ka_extensions/extension2"

``install_after``
  A comma-separated string (not an array) of extensions that should be installed *before* the current extension. These dependencies are optional; however, if they are present, the current extension will be positioned later in the execution chain. This directly affects the parent/child class inheritance chain in method calls.

  *Example:*
  .. code-block:: ini

     install_after = "ka_extensions/extension1,ka_extensions/extension2"

``licese_code``
  Optional. This is a full code from the 'license code' at ka-station.com server. It may contain a suffix after '@' character.

     
Reserved Fields
===============

The following fields are optional and currently unused, but are reserved for future functionality:

* ``support_email``: The support contact email address.
* ``author``: The company or vendor name.
* ``name``: The display name of the extension.
* ``version``: The current version of the extension.

Minimal Example
===============

Below is an example of a minimally sufficient ``kamod.ini`` file:

.. code-block:: ini

   name = "My test module1"