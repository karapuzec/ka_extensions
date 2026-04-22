Ka Extensions: Supported Constants
=================================

Overview
--------

**Ka Extensions** provides several useful constants that assist in development and debugging.


KAMOD_DEBUG
-----------

When the ``KAMOD_DEBUG`` constant is set to ``1``, the Kamod cache is automatically regenerated on every page reload.

This is useful during development, as it removes the need to manually refresh the cache after making changes.

**Important:**

- This constant does **not** refresh the modifications cache.


KALOG_MISSED_LABELS
-------------------

When the ``KALOG_MISSED_LABELS`` constant is set to ``1``, Ka Extensions generates a log file named:

::

    missed_labels.log

This file is created in the ``DIR_LOGS`` directory and contains a list of all language variables that are missing translations.


KA_DEBUG_DEPRECATED
-------------------

When the ``KA_DEBUG_DEPRECATED`` constant is set to ``1``, Ka Extensions throws errors whenever deprecated
features are used. The Ka Extensions library is evolving, and from time to time some features become deprecated,
so it is recommended to remove them from the code.

This flag is intended for developers and should not be used in a production environment.