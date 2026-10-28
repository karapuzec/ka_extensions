<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
*/

namespace extension\ka_extensions;

/**
	@internal
*/
class ControllerCommonHeader extends \ControllerCommonHeader {

	use TraitController;

	public function index() {

		$this->disableRender();
		parent::index();
		$this->enableRender();
	
		$this->document->addScript('view/javascript/extension/ka_extensions/common.js');
		$this->document->addStyle('view/stylesheet/extension/ka_extensions/stylesheet.css');

		$data     = $this->getRenderData();
		$template = $this->getRenderTemplate();
	
		$kamodel_kamod = $this->load->kamodel('extension/ka_extensions/ka_extensions/kamod');
		
		$last_errors_total = $kamodel_kamod->getLastErrorsTotal();
		$data['kalog_errors_total'] = $last_errors_total;
		$data['kalog_errors_link'] = $this->url->linka('tool/log/kamod_log');

		$data = array_merge($data, $this->getNotificationsData($data));
	
		return $this->load->view($template, $data);
	}


	/*
		Data of the notification bell (\extension\ka_extensions\Notifications, see
		header/notifications.twig): the notifications of the logged in administrator and the ones for all
		administrators, for administrators with access to the Ka Extensions page only.
	*/
	protected function getNotificationsData($data) {

		$result = array('ka_notifications' => array());

		if (empty($data['logged']) || !$this->user->hasPermission('access', 'extension/extension/ka_extensions')) {
			return $result;
		}

		try {
			$notifications = Notifications::getList();
		} catch (\Exception $e) {
			// the header works without the table or with a broken one
			return $result;
		}

		if (empty($notifications)) {
			return $result;
		}

		// a separate language object: the strings do not change the texts of the page
		$language = new \Language($this->config->get('config_admin_language'));
		$language->load('extension/ka_extensions/ka_extensions/notifications');

		$result['ka_notifications'] = $notifications;
		$result['ka_notifications_title'] = $language->get('txt_ka_notifications_title');
		$result['ka_notification_close']  = $language->get('txt_ka_notification_close');
		$result['ka_notification_delete'] = $this->url->linka('extension/extension/ka_extensions/deleteNotification');

		return $result;
	}
}
