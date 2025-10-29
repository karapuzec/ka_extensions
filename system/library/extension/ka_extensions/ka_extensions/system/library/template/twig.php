<?php
namespace extension\ka_extensions\template;

use \extension\ka_extensions\KaGlobal;
use \extension\ka_extensions\KamodManager;

class Twig extends \Template\Twig {

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
				$this->prependPath($fs_mod_loader, DIR_MODIFICATION . 'admin/view/template/extension/ka_extensions/common', 'ka_common');
			}
			
			// include kamod general cache
			if (is_dir($kamod_cache_dir . $admin_dirname)) {
				$fs_mod_loader->prependPath($kamod_cache_dir . $admin_dirname . '/view/template');
				$this->prependPath($fs_mod_loader, $kamod_cache_dir . $admin_dirname . '/view/template/extension/ka_extensions/common', 'ka_common');
			}

			// include 'store specific' cache (when the admin modified templates)
			if (is_dir($store_cache_dir . $admin_dirname)) {
				$fs_mod_loader->prependPath($store_cache_dir . $admin_dirname . '/view/template');
				$this->prependPath($fs_mod_loader, $store_cache_dir . $admin_dirname . '/view/template/extension/ka_extensions/common', 'ka_common');
			}
			
			// add '@ka_common' shortcut
			if (is_dir(DIR_TEMPLATE . 'extension/ka_extensions/common')) {
				$fs_mod_loader->addPath(DIR_TEMPLATE . 'extension/ka_extensions/common', 'ka_common');
			}
			
		} else {
		
			$theme_cache_dir = KamodManager::getThemeCacheDir($store_id);
		
			$theme_dir = KaGlobal::getRegistry()->get('config')->get('config_theme');
		
			$fs_mod_after_loader->addPath(DIR_TEMPLATE);
			
			// find a cache file modified by administrator
			if (is_dir($theme_cache_dir . 'catalog/view/theme/')) {
				$fs_mod_loader->prependPath($theme_cache_dir . 'catalog/view/theme');
				
				$this->prependPath($fs_mod_loader, $theme_cache_dir . 'catalog/view/theme/default/template/extension/ka_extensions/common', 'ka_common');
				
				if ($theme_dir != 'default') {
					$this->prependPath($fs_mod_loader, $theme_cache_dir . 'catalog/view/theme/'. $theme_dir . '/template/extension/ka_extensions/common', 'ka_common');
				}
			}
			
			// the main modification location
			if (is_dir(DIR_MODIFICATION . 'catalog/view/theme')) {
//				$fs_mod_loader->prependPath(DIR_MODIFICATION . 'catalog/view/theme');
			}
			
			// include kamod general cache
			if (is_dir($kamod_cache_dir . 'catalog/view/theme')) {
				$fs_mod_loader->prependPath($kamod_cache_dir . 'catalog/view/theme');
				if ($theme_dir != 'default') {
					$this->prependPath($fs_mod_loader, $kamod_cache_dir . 'catalog/view/theme/default/template/extension/ka_extensions/common', 'ka_common');
				}
				$this->prependPath($fs_mod_loader, 'catalog/view/theme/' . $theme_dir . '/template/extension/ka_extensions/common', 'ka_common');
			}
			
			// include 'store specific' cache (when the admin modified templates)
			if (is_dir($store_cache_dir . 'catalog/view/theme')) {
				$fs_mod_loader->prependPath($store_cache_dir . 'catalog/view/theme');
				if ($theme_dir != 'default') {
					$this->prependPath($fs_mod_loader, $store_cache_dir . 'catalog/view/theme/default/template/extension/ka_extensions/common', 'ka_common');
				}
				$this->prependPath($fs_mod_loader, $store_cache_dir . 'catalog/view/theme/' . $theme_dir . '/template/extension/ka_extensions/common', 'ka_common');
			}
			
			// add '@ka_common' shortcut
			if (is_dir(DIR_TEMPLATE . $theme_dir . '/template/extension/ka_extensions/common')) {
				$fs_mod_loader->addPath(DIR_TEMPLATE . $theme_dir . '/template/extension/ka_extensions/common', 'ka_common');
				if ($theme_dir != 'default') {
					if (is_dir(DIR_TEMPLATE . 'default/template/extension/ka_extensions/common')) {
						$fs_mod_loader->addPath(DIR_TEMPLATE . 'default/template/extension/ka_extensions/common', 'ka_common');
					}
				}
			}
		}
		
		// include shared templates directory
		if (is_dir(DIR_SYSTEM . 'shared_template')) {
			$fs_mod_loader->addPath(DIR_SYSTEM . 'shared_template/');
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
	
	
	protected function prependPath($loader, $path, $namespace = null) {
	
		if (!is_dir($path)) {
			return;
		}
	
		if (empty($namespace)) {
			$loader->prependPath($path);
		} else {
			$loader->prependPath($path, $namespace);
		}	
	}
	
}