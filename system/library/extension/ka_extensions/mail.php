<?php
/*
	$Project: Ka Extensions $
	$Author: karapuz team <support@ka-station.com> $

	$Version: 4.1.1.0 $ ($Revision: 569 $)
*/
	
namespace extension\ka_extensions;

class Mail {
	public $mail;
	public $sender;
	public $data;
	public $images;
	public $registry;
	public $store_config;
	public $config;
	public $log;
	public $load;
	public $language;

	/*
		$registry_or_language_id - it can be registry object, null, or language_id (number or string). The registry 
			object is for compatibility with previous modules. language_id is a recommended parameter.

		language_id can be an id or langauge code.
	*/
	function __construct($registry_or_language_id = null, $store_id = 0) {
	
		$language = null;
		$registry = null;
	
		// init registry variable and fetch the language if possible
		//
		if (is_object($registry_or_language_id)) {
			$registry = $registry_or_language_id;
		} else {
			$registry = \KaGlobal::getRegistry();
			
			if (!is_numeric($registry_or_language_id)) { // this case for codes like 'en-gb'
				$language_id = \KaLanguage::getLanguageIdByCode($registry_or_language_id);
				if (!empty($language_id)) {
					$language = \KaLanguage::getLanguage($registry_or_language_id);
				}
			} elseif (!empty($registry_or_language_id)) { // this case for a real language_id
				$language = \KaLanguage::getLanguage($registry_or_language_id);
			}
		}
		
		// init language variable
		//
		if (!empty($language)) {
			$this->language = $language;
		} else {
			$this->language = $registry->get('language');
		}
		
		// init our mail settings
		//
		$this->config   = $registry->get('config');
		$this->db       = $registry->get('db');
		$this->request  = $registry->get('request');
		$this->session  = $registry->get('session');
		$this->log      = $registry->get('log');
		$this->load     = $registry->get('load');
		$this->registry = $registry;
	
		// init standard Opencart mail object
		//
		$mail_engine = $this->config->get('config_mail_engine');
		if (empty($mail_engine)) {
			$this->mail = new \Mail();
		} else {
			$this->mail = new \Mail($mail_engine);
		}
		$this->mail->parameter = $this->config->get('config_mail_parameter');
		$this->mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$this->mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$this->mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$this->mail->smtp_port     = $this->config->get('config_mail_smtp_port');
		$this->mail->smtp_timeout  = $this->config->get('config_mail_smtp_timeout');

		// get store information
		//
		$store_info = \KaStore::getStoreInfo($store_id);
		
		$this->data['store_name'] = $store_info['store_name'];
		$this->data['sender']     = $store_info['sender'];
		$this->data['store_url']  = $store_info['store_url'] . 'index.php?route=account/login';

		// get store config information
		//
		$this->store_config = \KaStore::getStoreConfig($store_id);
	}


/*
	$tpl has to contain the template name without the extension, Example:
		extension/ka_extensions/product_warranty/mail/product_warranty_created
*/		
	public function send($from, $to, $subject, $tpl, $extra = array()) {

		if (empty($from)) {
			$from = $this->store_config->get('config_email');
		}

		if (empty($this->data['sender'])) {
			$sender = html_entity_decode($this->store_config->get('config_name'));
		} else {
			$sender = html_entity_decode($this->data['sender']);
		}
		
		// pass headers to the email
		//
		if (!empty($extra['headers'])) {
			$this->mail->setHeaders($extra['headers']);
		}

		// HTML Mail
		
		$subject = $this->language->get($subject);
		$logo = $this->store_config->get('config_logo');
		if (is_file(DIR_IMAGE . $logo)) {
			$this->images['logo'] = $logo;
		}
		
		$html = $text = '';

		// load a text file
		//
		$template = $tpl . '_txt';
		try {
			// the view substitutes the current template directory itself
			$text = $this->loadViewForLanguage($template, $this->data);
			if (!empty($text) && !empty($extra['send_content'])) {
				$this->language->load('extension/ka_extensions/common/mail/common');
				$this->language->load('extension/ka_extensions/common/mail/common_txt');
				$this->data['header']  = $this->loadViewForLanguage('extension/ka_extensions/common/mail/header_txt', $this->data);
				$this->data['footer']  = $this->loadViewForLanguage('extension/ka_extensions/common/mail/footer_txt', $this->data);
				$this->data['content'] = $text;
				$text = $this->load->view('extension/ka_extensions/common/mail/content_txt', $this->data);
			}
		} catch (\Exception $e) {
		
		}
		
		if (!empty($this->images)) {
			foreach ($this->images as $ik => $iv) {
	      		if (!$this->config->get('ka_extensions_mail_images_are_enclosed')) {
					$this->data[$ik] = $this->data['_images'][$ik] = $this->store_config->get('config_url') . 'image/' . $iv;
	      		} else {
			      	if (!empty($iv) && file_exists(DIR_IMAGE . $iv)) {
			      		$filename = DIR_IMAGE . $iv;
			      		$this->data[$ik] = $this->data['_images'][$ik] = 'cid:' . urlencode(basename($filename));
						$this->mail->addAttachment($filename);
				  	}
				}
		  	}
		}
		
		// load an html file
		try {
			$html = $this->loadViewForLanguage($tpl, $this->data);
			if (!empty($html) && !empty($extra['send_content'])) {
				$this->language->load('extension/ka_extensions/common/mail/common');
				
				$this->data['header']  = $this->loadViewForLanguage('extension/ka_extensions/common/mail/header', $this->data);
				$this->data['footer']  = $this->loadViewForLanguage('extension/ka_extensions/common/mail/footer', $this->data);
				$this->data['content'] = $html;
				$html = $this->loadViewForLanguage('extension/ka_extensions/common/mail/content', $this->data);
			}
		} catch (\Exception $e) {
			if (empty($text)) {
				$this->log->write("WARNING: failed to load html: $tpl. Error: " . $e->getMessage());
			}
		}
		
		if (empty($html) && empty($text)) {
			$this->log->write("WARNING: template is not found: $tpl");
			return false;
		}
		
		if (!empty($extra['reply_to'])) {
			$this->mail->setReplyTo($extra['reply_to']);
		}
		
		$this->mail->setTo($to);
		$this->mail->setFrom($from);
		$this->mail->setSender($sender);
		$this->mail->setSubject($subject);
		$this->mail->setText($text);
		$this->mail->setHtml($html);
		$this->mail->send();

		return true;
	}
	
	public function addAttachment($filename, $name = '') {
		if (!empty($name)) {
			$this->mail->addNamedAttachment($filename, $name);
		} else {
			$this->mail->addAttachment($filename);
		}
	}
	
	public function sendContent($to, $subject, $tpl, $from = '', $extra = []) {
		$extra['send_content'] = 1;
		$result = $this->send($from, $to, $subject, $tpl, $extra);
		return $result;
	}

	/*
		Tries to load a template for the specific language. First, it tries to load a template
		in the same directory but with '_<language code>' suffix like:
		mail/order_de.twig
		
		If it does not exist, it loads the standard template as usual
		mail/order.twig
		
		The function may cause an exception when the standard template does not exist.
	*/
	public function loadViewForLanguage($template, $data) {
	
		$result = '';
	
		$tpl = $template . '_' . $this->language->get('code');

		// supress notices and warnings when the template is not found
		$old_er = error_reporting();
		error_reporting(E_ERROR);
		
		// a 3rd party module 'template switcher' generates an error when the template file is not found
		// we disable it here to prevent this error
		$this->config->set('module_template_switcher_status', false);

		$org_language = $this->registry->get('language');
		$this->registry->set('language', $this->language);
		
		try {
			$result = $this->load->view($tpl, $data);
		} catch (\Exception $e) {
			try {
				$result = $this->load->view($template, $data);
			} catch (\Exception $e) {
				$result = '';
			}
		}
		$this->registry->set('language', $org_language);		
		
		// restore the error reporting level
		error_reporting($old_er);
		
		return $result;
	}
}