Upgrading Modules
=================

When new files of an installed module are uploaded, the module does not need to be uninstalled and
installed again. *Extensions > Ka Extensions* shows a yellow **Refresh** button next to the module
when the version of its files is greater than the installed version, with a hint like
``Files: 1.0.1.0, installed: 1.0.0.0``. The button upgrades the module, its settings stay.

The installed version
---------------------

- The version of the files is ``protected $extension_version`` of the module installer (the class
  extending ``ControllerInstaller`` or ``ControllerSettings``). Increase it in every release.
- On installation the version is stored as the hidden value ``version`` of the module (see below).
- A module installed by an older library has no stored version, it is ``0.0.0.0``, so the button is
  shown once for such modules.
- Versions are compared with ``version_compare()``.
- A module without ``$extension_version`` (the default ``0.0.0``) never gets the button.

What the button does
--------------------

The request is sent by POST and needs the *modify* permission of Ka Extensions (and the modules the
module depends on, as on installation). Then:

1. ``upgradeExtension($from_version, $to_version)`` of the module installer is called with the
   installed version and the version of the files;
2. on success the new version is stored, the kamod cache is marked for a rebuild (new kamod files of
   the module are applied) and the module page gets the access and modify permissions;
3. on failure an error is shown, the installed version is not changed and the button stays, so
   the upgrade can be started again.

By default ``upgradeExtension()`` calls ``install()``: the database is patched by ``$tables`` (new
tables, fields and indexes), new settings get their default values (saved values are kept) and new
pages of ``getExtensionPages()`` get the permissions. Override it for data migrations or when
``install()`` cannot be repeated safely:

.. code-block:: php

    public function upgradeExtension($from_version, $to_version) {

        if (!parent::upgradeExtension($from_version, $to_version)) {
            return false;
        }

        if (version_compare($from_version, '1.0.1.0', '<')) {
            // migration of the data to 1.0.1.0, safe to run again
        }

        return true;
    }

The method is called ``upgradeExtension()`` and not ``upgrade()`` because some released modules
already have their own ``upgrade()`` without parameters.

Rules for modules
-----------------

- ``$extension_version`` is required and is increased in every release.
- ``install()`` must be safe to run again on an installed module, or the module overrides
  ``upgradeExtension()``. Never drop tables or replace user data there.
- A field added to an existing table needs a ``query`` (``ALTER TABLE``) in ``$tables``.
- Read new settings with a default value: an upgraded store may not have them until the settings page
  is saved, e.g. ``$this->config->get('mymodule_limit') ?? 10``.
- Uninstallation does not delete the module settings (``ControllerSettings::uninstall()`` keeps them),
  so the next installation uses them again. Remove them in the module ``uninstall()`` only when it
  is really needed.

Hidden values
-------------

Values that are not fields of the settings page (the installed version, counters) are stored in the
setting table in the group ``<setting code>_hidden``, under the keys ``<setting code>_hidden_<key>``.
Saving the settings page does not touch them.

The class ``\extension\ka_extensions\HiddenSettings`` works in the admin area and in the catalog:

.. code-block:: php

    use \extension\ka_extensions\HiddenSettings;

    HiddenSettings::set('mymodule', 'last_run', date('Y-m-d'));
    $last_run = HiddenSettings::get('mymodule', 'last_run');          // '2026-10-28'
    $limit    = HiddenSettings::get('mymodule', 'limit', 100);         // 100 when not stored
    $requests = HiddenSettings::increase('mymodule', 'requests');     // +1, returns the new value
    HiddenSettings::set('mymodule', 'last_run', null);                 // removes the key

- ``get($ext_code, $key, $default = null)`` returns the value or ``$default`` when it is not stored;
- ``set($ext_code, $key, $value)`` writes one key, the other hidden values of the module are not
  rewritten; arrays are stored as JSON (``serialized`` = 1), other values as strings, ``null``
  removes the key;
- ``increase($ext_code, $key, $add = 1)`` changes a numeric value with one ``UPDATE`` in the database
  (``value = value + $add``), so parallel requests do not lose increments; a missing value is
  created with ``$add``. It returns the new value.

In the admin area the setting model has the same methods as wrappers of this class:

.. code-block:: php

    $this->load->model('setting/setting');

    $this->model_setting_setting->setConfigHidden('mymodule', 'requests', 15);
    $requests = $this->model_setting_setting->getConfigHidden('mymodule', 'requests'); // '15'

The values belong to the default store (``store_id`` 0). They are loaded into the store config as
``<setting code>_hidden_<key>`` at the start of a request like other settings; the methods above
always read the database.
