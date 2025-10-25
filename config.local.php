<?php
// HTTP
define('HTTP_SERVER', 'http://ka.local/ka_ext/oc3000a1/');

// HTTPS
define('HTTPS_SERVER', 'http://ka.local/ka_ext/oc3000a1/');

// DIR
define('DIR_APPLICATION', 'T:/home/ka.local/www/ka_ext/oc3000a1/catalog/');
define('DIR_SYSTEM', 'T:/home/ka.local/www/ka_ext/oc3000a1/system/');
define('DIR_IMAGE', 'T:/home/ka.local/www/ka_ext/oc3000a1/image/');

define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/theme/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_SYSTEM . 'storage/cache/');
define('DIR_DOWNLOAD', DIR_SYSTEM . 'storage/download/');
define('DIR_LOGS', DIR_SYSTEM . 'storage/logs/');
define('DIR_MODIFICATION', DIR_SYSTEM . 'storage/modification/');
define('DIR_UPLOAD', DIR_SYSTEM . 'storage/upload/');

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'ka_ext_oc3000a1');
define('DB_PORT', '3306');
define('DB_PREFIX', 'oc_');