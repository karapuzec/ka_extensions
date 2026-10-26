Third-party libraries used by the Ka Extensions library, copied as they are (only the code,
composer.json and the license of each package):

- jbroadway/urlify 1.2.5        https://github.com/jbroadway/urlify        (BSD-3-Clause, see LICENSE)
- voku/portable-ascii 2.1.1     https://github.com/voku/portable-ascii     (MIT, see LICENSE.txt)

They are loaded by \extension\ka_extensions\KaURLify (kaurlify.php), there is no composer autoloader.
PHP 7.2 or newer.

Local changes, to keep the package small (the code of the libraries is not changed):

- voku/stop-words 2.0.1 (a dependency of urlify) is not bundled. urlify uses it only to remove
  stop words from urls, which KaURLify does not do; KaURLify::remove_words() keeps its own list.
- portable-ascii: the transliteration tables of the CJK and Hangul Unicode blocks are removed
  (src/voku/helper/data/x030-x032, x04d-x09f, x0ac-x0d7, x0f9-x0fa: Kana, Bopomofo, Hangul jamo,
  enclosed CJK, CJK ideographs, Hangul syllables, CJK compatibility ideographs). Chinese, Japanese
  and Korean characters are dropped from urls (a missing table is skipped by portable-ascii).
  The other tables (Latin, Greek, Cyrillic, Hebrew, Arabic, Indic, Thai, Yi, unit symbols,
  fullwidth forms...) are kept.
- portable-ascii: the non-technical comment at the top of the ASCII class
  (src/voku/helper/ASCII.php) is removed.
