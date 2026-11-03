<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions\ka_extensions;

/**
	@internal
*/
class ModelKamod extends \extension\ka_extensions\Model {

	protected $kamod_manager;

	protected function onLoad() {
		$this->kamod_manager = \extension\ka_extensions\KamodManager::getInstance();
	}

	/*
		Complete rebuild of the theme cache and saving it in theme.kamod directory. Keeping templates there
		helps us to patch them and inherite. Also they are supposed to work slightly faster because there is no
		need to generate a cache for them everytime.
	*/
	public function rebuildThemeCache() {
	
		$this->kamod_manager = \extension\ka_extensions\KamodManager::getInstance();
		$this->kamod_manager->emptyThemeCache();
		
		// all theme editor changes of all stores. The core model getThemes() is the paged list of the theme editor
		// history (10 rows by default), so the older changes were missing in the cache. The core model saves one row
		// per store, theme and template; if there are more, the newest one is saved last and wins
		$themes = $this->db->query("SELECT store_id, theme, route, code FROM `" . DB_PREFIX . "theme` ORDER BY date_added ASC, theme_id ASC")->rows;
		if (empty($themes)) {
			return;
		}
	
		foreach ($themes as $theme) {
			$code = html_entity_decode($theme['code'], ENT_QUOTES, 'UTF-8');
			$this->kamod_manager->storeThemeFile($theme['store_id'], $theme['theme'], $theme['route'], $code);

			// a theme without its own copy of the template shows the template of the default theme folder
			if ($theme['theme'] != 'default' && !is_file(DIR_CATALOG . 'view/theme/' . $theme['theme'] . '/template/' . $theme['route'] . '.twig')) {
				$this->kamod_manager->storeInheritedThemeFile($theme['store_id'], $theme['theme'], $theme['route'], $code);
			}
		}
		
		$this->kamod_manager->rebuildTwigCache();
	}
	
	
	public function getLastErrorsTotal() {
		// we load kamod_manager here because we changed how onLoad works, and it does not work after uploading
		// a new 'ka extensions' version (till the code is refreshed)
		//
		$this->kamod_manager = \extension\ka_extensions\KamodManager::getInstance();
		$total = $this->kamod_manager->getLastErrorsTotal();
		
		return $total;
	}
	
}

class_alias(__NAMESPACE__ . '\ModelKamod', 'ModelExtensionKaExtensionsKaExtensionsKamod');