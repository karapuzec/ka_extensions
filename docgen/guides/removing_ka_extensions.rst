Removing Ka Extensions
======================

Ka Extensions is a set of PHP/Twig files along with an OCMOD modification.
Before removing it, make sure that no other extensions developed by our
company are installed, as they may depend on Ka Extensions functionality.

Removing the Ka Extensions library is similar to removing any other extension.
In most cases, it is sufficient to delete only the modification, as the remaining
files do not affect the store when the modification is removed.

Steps to Remove the Ka Extensions Modification
---------------------------------------------

1. Open the *Modifications* page in the store admin panel and delete the
   ``ka-extensions`` record.

2. Refresh modifications.

3. Clear the Twig cache. See this article for details:
   https://www.ka-station.com/tickets/kb/faq.php?id=24

4. Clear the VQMod cache and ``vqmod/*.cache`` files (if applicable).

5. Clear the Lightning plugin cache (if applicable).

6. Clear theme caches (for example, Journal 3 cache) if used.

7. Be aware that additional caching may exist at the server level, but it
   usually refreshes automatically.

8. Check the website in an incognito/private browser window to ensure that
   all changes are reflected and no cached data is shown.

These steps are usually sufficient to ensure that a standard OpenCart store
is no longer affected by the Ka Extensions library.

Removing Library Files (Optional)
--------------------------------

You may also remove files included with Ka Extensions. Since the file list may
change between versions, it is recommended to compare your store files with the
contents of the Ka Extensions archive.

All files (not directories) located in the ``upload`` directory of the archive
belong to Ka Extensions and can be safely deleted.