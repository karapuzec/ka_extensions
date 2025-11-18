<?php
// HTTP
define('HTTP_SERVER', 'http://localhost/ka_ext/oc3000a1/admin/');
define('HTTP_CATALOG', 'http://localhost/ka_ext/oc3000a1/');

// HTTPS
define('HTTPS_SERVER', 'http://localhost/ka_ext/oc3000a1/admin/');
define('HTTPS_CATALOG', 'http://localhost/ka_ext/oc3000a1/');

// DIR
define('STORE_ROOT_DIR', rtrim(dirname(dirname(__FILE__)), '/'));
define('DIR_APPLICATION', STORE_ROOT_DIR . '/admin/');
define('DIR_SYSTEM', STORE_ROOT_DIR . '/system/');
define('DIR_IMAGE', STORE_ROOT_DIR . '/image/');
define('DIR_STORAGE', DIR_SYSTEM . 'storage/');
define('DIR_CATALOG', STORE_ROOT_DIR . '/catalog/');
define('DIR_LANGUAGE', DIR_APPLICATION . 'language/');
define('DIR_TEMPLATE', DIR_APPLICATION . 'view/template/');
define('DIR_CONFIG', DIR_SYSTEM . 'config/');
define('DIR_CACHE', DIR_STORAGE . 'cache/');
define('DIR_DOWNLOAD', DIR_STORAGE . 'download/');
define('DIR_LOGS', DIR_STORAGE . 'logs/');
define('DIR_MODIFICATION', DIR_STORAGE . 'modification/');
define('DIR_SESSION', DIR_STORAGE . 'session/');
define('DIR_UPLOAD', DIR_STORAGE . 'upload/');

// DB
define('DB_DRIVER', 'mysqli');
define('DB_HOSTNAME', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_DATABASE', 'ka_ext_oc3000a1');
define('DB_PORT', '3306');
define('DB_PREFIX', 'oc_');

// OpenCart API
define('OPENCART_SERVER', 'http://www.opencart.com/');
define('OPENCART_USERNAME', '');
define('OPENCART_SECRET', '');
define('KA_STORE_URL', 'http://localhost/ka-station/oc1513/');