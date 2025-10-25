<?php
/*
	$Project$
	$Author$

	$Version$ ($Revision$)
	
	This class can apply patches to a file before using it with kamod. Only replace and regexp replace operations
	are supported at this moment.
	
	Sample patch file format:
	
	<changes>
		<operation type="replace">
			<search><![CDATA[return $twig->render(]]></search>
			<text><![CDATA[//ka-extensions: pass the twig to a child class to add a custom extension
			$this->extendTwig($twig);
			return $twig->render(]]></text>
		</operation>
		<operation type="replace">
			<search regexp="true"><![CDATA[]]></search>
			<text><![CDATA[//ka-extensions: pass the twig to a child class to add a custom extension
			$this->extendTwig($twig);
			return $twig->render(]]></text>
		</operation>
	</changes>
	
	The file has to be named as the original file you would like to patch and located in a corresponding
	kamod directory. For example, to patch this file:
	
	<oc-root>system\library\template\twig.php
	
	The xml file should be placed here:
	<extension-dir>\kamod\system\library\template\twig.php.xml
*/

namespace extension\ka_extensions;

class KaPatch {

	protected $root_dir;
	
	protected $log; // An object with log() function to log errors.
	
	protected $patch_file;
	
	public function __construct($root_dir, $log) {
		$this->root_dir = $root_dir;
		$this->log      = $log;
	}

	protected function log($msg, $type = 'I') {
		$this->log->log($msg, $type);
	}

	
	public function findAndApply($target_file, $patches) {
	
		$contents = file_get_contents(\VQMod::modCheck($target_file));

		$contents = $this->applyXmlPatches($contents, $patches);
		
		file_put_contents($target_file, $contents);		
	}
	
	
	public function applyXmlPatches($contents, $patches) {
	
		if (empty($patches)) {
			return $contents;
		}		

		foreach ($patches as $patch) {
			$contents = $this->applyXml($contents, $patch);
		}
		
		return $contents;
	}

	
	public function applyXml($contents, $patch) {
		$xml_file = $this->root_dir . $patch;

		// ignore patching a non-existing file
		if (empty($contents)) {
			return '';
		}
		
		if (!file_exists($xml_file)) {
			$this->log("KaPatch could not find xml file: " . $xml_file, 'E');
			return $contents;
		}
		
		$this->patch_file = $xml_file;

		$xml = simplexml_load_file($xml_file, null, LIBXML_NOCDATA);
		
		// check version compatibility of the patch
		//
		if (empty($xml['version'])) {
			$this->log("KaPatch failed to parse xml file: " . $xml_file, 'E');
			return $contents;
		}
		
		$version = (string)$xml['version'];
		
		if (empty($version)) {
			$this->log("kamod version is not specified in the file" . $xml_file . ". The patch is skipped", 'E');
			return;
		}
		
		if (version_compare($version, KamodBuilder::KAMOD_VERSION, ">")) {
			$this->log("The patch '$xml_file' requires version '" . $version . "'. The installed kamod version '" . KamodBuilder::KAMOD_VERSION . "'. The patch is skipped", 'E');
			return;
		}

		$major_version       = explode(".", $version);
		$major_kamod_version = explode(".", KamodBuilder::KAMOD_VERSION);
		
		if ($major_version[0] != $major_kamod_version[0]) {
			$this->log("The patch '$xml_file' requires version '" . $version . "'. The installed kamod version '" . KamodBuilder::KAMOD_VERSION . "'. The patch is skipped", 'E');
			return;
		}
		
		// apply operations from the patch
		//
		foreach ($xml->changes->operation as $op) {
			if ($op['type'] == 'replace') {
				$contents = $this->applyOperationReplace($contents, $op, (string)$op->text[0]);
			}
		}
		
		return $contents;
	}
	

	protected function applyOperationReplace($contents, $op, $text) {
		
		$search = $op->search;
	
		$count = 0;
	
		if (!empty($search['regexp'])) {
			// Ungreedy, multiline, the dot includes new lines
			$result = preg_replace("/" . trim((string)$search[0]) . "/Ums", $text, $contents, -1, $count);
			if (empty($count) && empty($op['skip'])) {
				KamodBuilder::log('Kapatch did not find the pattern "' . $search . '" from the file ' . $this->patch_file, 'E');
			}
		} else {
			$result = str_replace((string)$search[0], $text, $contents, $count);
			if (empty($count) && empty($op['skip'])) {
				KamodBuilder::log('Kapatch did not find the pattern "' . $search . '" from the file ' . $this->patch_file, 'E');
			}
		}
		
		return $result;
	}
}