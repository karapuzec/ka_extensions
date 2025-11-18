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
	
		$this->kamod_manager->emptyThemeCache();
		
		$this->load->model('design/theme');
		
		$themes = $this->model_design_theme->getThemes();
		if (empty($themes)) {
			return;
		}
	
		foreach ($themes as $theme) {
			$code = html_entity_decode($theme['code'], ENT_QUOTES, 'UTF-8');
			$this->kamod_manager->storeThemeFile($theme['store_id'], $theme['theme'], $theme['route'], $code);
		}
		
		$this->kamod_manager->rebuildTwigCache();
	}
	
	
	public function getLastErrorsTotal() {
		$total = $this->kamod_manager->getLastErrorsTotal();
		
		return $total;
	}
	
}

class_alias(__NAMESPACE__ . '\ModelKamod', 'ModelExtensionKaExtensionsKaExtensionsKamod');