How To Make Module
==========================


Module Directory Structure
--------------------------

By default, the KaMod engine does not process module files automatically.
It must be explicitly enabled for each module.

"Ka Extensions" assumes that each vendor maintains their own namespace
within the ``extension`` directory. As a result, module files are distributed
across standard OpenCart directories using the following structure:

::

    /controller/extension/<vendor-name>/<module-name>
    /language/en-gb/extension/<vendor-name>/<module-name>
    /model/extension/<vendor-name>/<module-name>
    /view/template/extension/<vendor-name>/<module-name>

This structure ensures consistency and compatibility with OpenCart conventions.


Installer
---------

Each module must include an installer file located in the controller directory:

::

    /controller/extension/<vendor-name>/<module-name>.php

The installer is responsible for:

- Declaring the minimum required OpenCart version
- Declaring the minimum required "Ka Extensions" version
- Providing installation and uninstallation logic

All installers should inherit from the following base class:

::

    \extension\ka_extensions\ControllerSettings

This base class provides common functionality and simplifies module setup.

For simple modules, the installer can remain minimal and rely on inherited behavior.


Namespaces
----------

All PHP files within a module are expected to use namespaces.

The namespace should follow the module path:

::

    extension\<vendor-name>\<module-name>

Due to current limitations of "Ka Extensions", each PHP file must also define
a global class alias for its main class.

This alias ensures compatibility with OpenCart’s class autoloading system.

Example:

::

    class_alias(
        __NAMESPACE__ . '\ControllerProductField',
        'ControllerExtensionMyVendorProductField'
    )


Manifest File
-------------

Each module must include a ``kamod.ini`` manifest file located in the module's
controller directory.

The manifest file defines:

- Module name
- Module version
- Additional configuration parameters

When "Ka Extensions" detects modules with a valid manifest file, it automatically:

- Creates a dedicated page for the vendor in the admin panel
- Lists all available modules for that vendor
- Allows administrators to install and uninstall modules
- Provides access to module settings pages

Additionally, "Ka Extensions" automatically grants the necessary permissions
to administrators for accessing module configuration pages.