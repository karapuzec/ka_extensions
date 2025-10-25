<?php
/*
	This file was inherited by kamod.
	More information can be found at https://www.ka-station.com/kamod
	
	Original file: system/library/extension/ka_extensions/ka_extensions/system/library/template/twig.php
*/
namespace extension\ka_extensions\library\template;

use \extension\ka_extensions\KaGlobal;
use \extension\ka_extensions\KamodManager;

require_once(__DIR__ . '/twig.1.kamod.php');

class Twig extends \Template\Twig_kamod  {

	protected function replaceLoader($loader) {
	
		$store_id = (int)KaGlobal::getRegistry()->get('config')->get('config_store_id');
	
		$kamod_cache_dir = KamodManager::getKamodTemplatesDir();
		$store_cache_dir = KamodManager::getKamodTemplatesDir($store_id);
		
		if (class_exists('\Twig\Loader\FilesystemLoader')) {
			$fs_mod_loader = new \Twig\Loader\FilesystemLoader();
			$fs_mod_after_loader = new \Twig\Loader\FilesystemLoader();
		} else {
			$fs_mod_loader = new \Twig_Loader_Filesystem();
			$fs_mod_after_loader = new \Twig_Loader_Filesystem();
		}		
	
		if (KaGlobal::isAdminArea()) {
		
			$admin_dirname = KaGlobal::getAdminDirName();

			$fs_mod_after_loader->addPath(DIR_TEMPLATE);
			
			// include the main modification location
			if (is_dir(DIR_MODIFICATION . 'admin/view/template')) {
				$fs_mod_loader->prependPath(DIR_MODIFICATION . 'admin/view/template');
			}
			
			// include kamod general cache
			if (is_dir($kamod_cache_dir . $admin_dirname)) {
				$fs_mod_loader->prependPath($kamod_cache_dir . $admin_dirname . '/view/template');
			}

			// include 'store specific' cache (when the admin modified templates)
			if (is_dir($store_cache_dir . $admin_dirname)) {
				$fs_mod_loader->prependPath($store_cache_dir . $admin_dirname . '/view/template');
			}
			
			// add '@ka_common' shortcut
			if (file_exists(DIR_TEMPLATE . 'extension/ka_extensions/common')) {
				$fs_mod_loader->addPath(DIR_TEMPLATE . 'extension/ka_extensions/common', 'ka_common');
			}
			
		} else {
		
			$theme_cache_dir = KamodManager::getThemeCacheDir($store_id);
		
			$theme_dir = KaGlobal::getRegistry()->get('config')->get('config_theme');
		
			$fs_mod_after_loader->addPath(DIR_TEMPLATE);
			
			// find a cache file modified by administrator
			if (is_dir($theme_cache_dir . 'catalog/view/theme/')) {
				$fs_mod_loader->prependPath($theme_cache_dir . 'catalog/view/theme');
			}
			
			// the main modification location
			if (is_dir(DIR_MODIFICATION . 'catalog/view/theme')) {
//				$fs_mod_loader->prependPath(DIR_MODIFICATION . 'catalog/view/theme');
			}
			
			// include kamod general cache
			if (is_dir($kamod_cache_dir . 'catalog/view/theme')) {
				$fs_mod_loader->prependPath($kamod_cache_dir . 'catalog/view/theme');
			}			

			// include 'store specific' cache (when the admin modified templates)
			if (is_dir($store_cache_dir . 'catalog/view/theme')) {
				$fs_mod_loader->prependPath($store_cache_dir . 'catalog/view/theme');
			}			
			
			// add '@ka_common' shortcut
			if (file_exists(DIR_TEMPLATE . $theme_dir . '/template/extension/ka_extensions/common')) {
				$fs_mod_loader->addPath(DIR_TEMPLATE . $theme_dir . '/template/extension/ka_extensions/common', 'ka_common');
			} else {
				if (file_exists(DIR_TEMPLATE . 'default/template/extension/ka_extensions/common')) {
					$fs_mod_loader->addPath(DIR_TEMPLATE . 'default/template/extension/ka_extensions/common', 'ka_common');
				}			
			}
		}
		
		if (class_exists('\Twig\Loader\ChainLoader')) {
			$chain_loader = new \Twig\Loader\ChainLoader(array($fs_mod_loader, $loader, $fs_mod_after_loader));
		} else {
			$chain_loader = new \Twig_Loader_Chain(array($fs_mod_loader, $loader, $fs_mod_after_loader));
		}
		
		return $chain_loader;
	}

	
	protected function extendTwig($twig) {
	
		// added compatibility with Twig 3
		//
		if (!class_exists('\Twig_Extension')) {
			@class_alias('\Twig\Extension\AbstractExtension', '\Twig_Extension');
			@class_alias('\Twig\TwigFunction', '\Twig_SimpleFunction');
		}
	
		$twig->addExtension(new \extension\ka_extensions\TwigExtension());
	}
}