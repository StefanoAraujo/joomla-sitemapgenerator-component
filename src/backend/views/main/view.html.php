<?php
/*
 * @copyright  Copyright (C) 2015 Marco Beierer. All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 */
defined('_JEXEC') or die('Restricted access');

require_once(JPATH_COMPONENT_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'shared_functions.php');

class SitemapGeneratorViewMain extends JViewLegacy {
	function display($tmpl = null) {
		JToolbarHelper::title(JText::_('COM_SITEMAPGENERATOR'));

		if (JFactory::getUser()->authorise('core.admin', 'com_sitemapgenerator')) {
			JToolbarHelper::preferences('com_sitemapgenerator');
		}

		$doc = JFactory::getDocument();
		$doc->addScript(JURI::root() . '/media/com_sitemapgenerator/js/angular.min.js', 'text/javascript', true);
		$doc->addScript(JURI::root() . '/media/com_sitemapgenerator/js/sitemap-vars.js?v=1', 'text/javascript', true);
		$doc->addScript(JURI::root() . '/media/com_sitemapgenerator/js/sitemap.js?v=5', 'text/javascript', true);

		$this->curlInstalled = function_exists('curl_version');

		$curlVersion = curl_version(); // temp var necessary for PHP 5.3
		$this->curlVersionOk = version_compare($curlVersion['version'], '7.18.1', '>=');

		$this->onLocalhost = preg_match('/^https?:\/\/(?:localhost|127\.0\.0\.1)/i', JURI::root()) === 1; // TODO improve localhost detection
		
		$params = JComponentHelper::getParams('com_sitemapgenerator');

		$this->hasToken = $params->get('token') != '';

		$this->multilangSupportEnabled = $params->get('multilang_support') == '1';
		$this->multilangSupportNecessary = isMultilangSupportNecessary();
		$this->isSEFMultilangSiteWithoutMultilangSupportEnabled = isMultilangSupportNecessary() && !$this->multilangSupportEnabled;

		if ($this->multilangSupportEnabled && $this->multilangSupportNecessary) {
			$this->sitemapsData = $this->loadSitemapsData();
		} else {
			$this->sitemapsData = $this->loadDefaultSitemapData();
		}

		if (count($this->sitemapsData) == 0) {
			$this->sitemapsData = $this->loadDefaultSitemapData();
		}
		
		$ajaxPlugin = JPluginHelper::getPlugin('ajax', 'sitemapgenerator'); // returns an empty array if not found; and an object if found
		$module = JModuleHelper::getModule('mod_sitemapgenerator'); // returns an dummy object with id = 0 if not found
		$this->discontinuedExtensionsInstalled = !is_array($ajaxPlugin) || $module->id != 0;

		$doc->addScriptDeclaration($this->getAngularBootstrapJS($this->sitemapsData));

		parent::display();
	}

	function getAngularBootstrapJS($sitemapsData) {
		$script = "jQuery(document).ready(function() {\n";
		foreach ($sitemapsData as $data) {
			$script .= "angular.bootstrap(document.getElementById('" . $data->identifier . "SitemapGenerator'), ['sitemapGeneratorApp']);\n";
		}
		$script .= "});";

		return $script;
	}

	function loadDefaultSitemapData() {
		$sitemaps = array();

		$sitemap = new stdClass();

		if (JFactory::getApplication()->input->getInt('dev', 0) === 1) {
			$sitemap->link = 'https://www.marcobeierer.com/';
		} else {
			$sitemap->link = JURI::root();
		}

		$sitemap->base64URL = $this->base64URL($sitemap->link);
		$sitemap->identifier = '';
		$sitemap->filename = 'sitemap.xml';

		$sitemaps[] = $sitemap;
		return $sitemaps;
	}

	function base64URL($url) {
		return urlencode(strtr(base64_encode($url), '+/', '-_')); // urlencode for =
	}

	function loadSitemapsData() {
		return loadMultilangData(function ($language, $langCode, $defaultLangCode, $sefRewrite) {
			$sitemap = new stdClass();

			$sitemap->link = JURI::root() . 'index.php/' . $language->sef . '/';
			if ($sefRewrite) {
				$sitemap->link = JURI::root() . $language->sef . '/';
			}

			$sitemap->base64URL = $this->base64URL($sitemap->link);

			$sitemap->identifier = '';
			$sitemap->filename = 'sitemap.xml';

			if ($langCode != $defaultLangCode) {
				$sitemap->identifier = substr($language->sef, 0, 3);
				$sitemap->filename = 'sitemap.' . $language->sef . '.xml';
			}

			return $sitemap;
		});
	}
}
