<?php
// import of genericPlugin
import('lib.pkp.classes.plugins.GenericPlugin');

use phpDocumentor\Reflection\Types\Null_;
use \PKP\components\forms\FormComponent;
use \PKP\components\forms\FieldHTML; // needed for function extendScheduleForPublication

/**
 * geoOJSPlugin, a generic Plugin for enabling geospatial properties in OJS 
 */
class geoOJSPlugin extends GenericPlugin
{
	public function register($category, $path, $mainContextId = NULL)
	{
		// Register the plugin even when it is not enabled
		$success = parent::register($category, $path, $mainContextId);
		// important to check if plugin is enabled before registering the hook, cause otherwise plugin will always run no matter enabled or disabled! 
		if ($success && $this->getEnabled()) {

			/* 
			Hooks are the possibility to intervene the application. By the corresponding function which is named in the HookRegistery, the application
			can be changed. 
			Further information here: https://docs.pkp.sfu.ca/dev/plugin-guide/en/categories#generic 
			*/

			// Hooks for changing the frontent Submit an Article 3. Enter Metadata 
			HookRegistry::register('Templates::Submission::SubmissionMetadataForm::AdditionalMetadata', array($this, 'extendSubmissionMetadataFormTemplate'));

			// Hooks for changing the Metadata right before Schedule for Publication (not working yet)
			HookRegistry::register('Form::config::before', array($this, 'extendScheduleForPublication'));
			HookRegistry::register('Template::Workflow::Publication', array($this, 'extendScheduleForPublication2'));

			// Hook for changing the article page 
			HookRegistry::register('Templates::Article::Main', array(&$this, 'extendArticleMainTemplate'));
			HookRegistry::register('Templates::Article::Details', array(&$this, 'extendArticleDetailsTemplate'));
			// Templates::Article::Main 
			// Templates::Article::Details
			// Templates::Article::Footer::PageFooter

			// Hook for creating and setting a new field in the database 
			HookRegistry::register('Schema::get::publication', array($this, 'addToSchema'));
			HookRegistry::register('Publication::edit', array($this, 'editPublication')); // Take care, hook is called twice, first during Submission Workflow and also before Schedule for Publication in the Review Workflow!!!

			$request = Application::get()->getRequest();
			$templateMgr = TemplateManager::getManager($request);

			/*
			In previous OJS versions, there was an option in the config.inc.php to enable or disable CDN. 
			As it was deprecated in OJS 3.3, we decide it to integrate it by a plugin setting: checkboxDisableCDN. 
			If the checkbox is checked the variable $checkboxDisableCDN is = "on" , CDN is disabled. Thus the plugins should not load any scripts or styles from a third-party website.
			Old configuration option which is disabled: Config::getVar('general', 'enable_cdn'). 
			*/
			$request = Application::get()->getRequest();
			$context = $request->getContext();
			$checkboxDisableCDN = $this->getSetting($context->getId(), 'checkboxDisableCDN');

			if ($checkboxDisableCDN !== "on") {
				// if the user allows CDN 
				$urlLeafletCSS = 'https://unpkg.com/leaflet@1.6.0/dist/leaflet.css';
				$urlLeafletJS = 'https://unpkg.com/leaflet@1.6.0/dist/leaflet.js';
				$urlLeafletDrawCSS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css';
				$urlLeafletDrawJS = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js';
				// $urlJqueryJS = 'https://code.jquery.com/jquery-3.2.1.js';
				// jquery no need to load, already loaded here: ojs/lib/pkp/classes/template/PKPTemplateManager.inc.php 
				$urlMomentJS = 'https://cdn.jsdelivr.net/momentjs/latest/moment.min.js';
				$urlDaterangepickerJS = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js';
				$urlDaterangepickerCSS = 'https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css';
				$urlLeafletControlGeocodeJS = 'https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js';
				$urlLeafletControlGeocodeCSS = 'https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css';
			} else {
				$urlLeafletCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/leaflet/leaflet.css';
				$urlLeafletJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/leaflet/leaflet.js';
				$urlLeafletDrawCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/leaflet-draw/leaflet.draw.css';
				$urlLeafletDrawJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/leaflet-draw/leaflet.draw.js';
				// $urlJqueryJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/daterangepicker/jquery.min.js';
				// jquery - no need to load, already loaded here: ojs/lib/pkp/classes/template/PKPTemplateManager.inc.php 
				$urlMomentJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/daterangepicker/moment.min.js';
				$urlDaterangepickerJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/daterangepicker/daterangepicker.min.js';
				$urlDaterangepickerCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/daterangepicker/daterangepicker.css';
				$urlLeafletControlGeocodeJS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/leaflet-control-geocoder/dist/Control.Geocoder.js';
				$urlLeafletControlGeocodeCSS = $request->getBaseUrl() . '/' . $this->getPluginPath() . '/enable_cdn_Off/leaflet-control-geocoder/dist/Control.Geocoder.css';
			}

			/*
			Here further scripts like JS and CSS are included, 
			these are included by the following lines and need not be referenced (e.g. in .tbl files).
			Further information can be found here: https://docs.pkp.sfu.ca/dev/plugin-guide/en/examples-styles-scripts
			*/

			// loading the leaflet scripts, source: https://leafletjs.com/examples/quick-start/
			$templateMgr->addStyleSheet('leafletCSS', $urlLeafletCSS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addJavaScript('leafletJS', $urlLeafletJS, array('contexts' => array('frontend', 'backend')));

			// loading the leaflet draw scripts, source: https://www.jsdelivr.com/package/npm/leaflet-draw?path=dist
			$templateMgr->addStyleSheet("leafletDrawCSS", $urlLeafletDrawCSS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addJavaScript("leafletDrawJS", $urlLeafletDrawJS, array('contexts' => array('frontend', 'backend')));

			// loading the daterangepicker scripts, source: https://www.daterangepicker.com/#example2 
			//$templateMgr->addJavaScript("jqueryJS", $urlJqueryJS, array('contexts' => array('frontend', 'backend')));
			// jquery no need to load, already loaded here: ojs/lib/pkp/classes/template/PKPTemplateManager.inc.php 
			$templateMgr->addJavaScript("momentJS", $urlMomentJS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addJavaScript("daterangepickerJS", $urlDaterangepickerJS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addStyleSheet("daterangepickerCSS", $urlDaterangepickerCSS, array('contexts' => array('frontend', 'backend')));

			// loading leaflet control geocoder (search), source: https://github.com/perliedman/leaflet-control-geocoder 
			$templateMgr->addJavaScript("leafletControlGeocodeJS", $urlLeafletControlGeocodeJS, array('contexts' => array('frontend', 'backend')));
			$templateMgr->addStyleSheet("leafletControlGeocodeCSS", $urlLeafletControlGeocodeCSS, array('contexts' => array('frontend', 'backend')));

			// main js scripts
			$templateMgr->assign('submissionMetadataFormFieldsJS', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/submissionMetadataFormFields.js');
			$templateMgr->assign('article_detailsJS', $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/article_details.js');
		}
		return $success;
	}

	/**
	 * Function which extends the submissionMetadataFormFields template and adds template variables concerning temporal- and spatial properties 
	 * and the administrative unit if there is already a storage in the database. 
	 * @param hook Templates::Submission::SubmissionMetadataForm::AdditionalMetadata
	 */
	public function extendSubmissionMetadataFormTemplate($hookName, $params)
	{
		/*
		This way templates are loaded. 
		Its important that the corresponding hook is activated. 
		If you want to override a template you need to create a .tpl-file which is in the plug-ins template path which the same 
		path it got in the regular ojs structure. E.g. if you want to override/ add something to this template 
		'/ojs/lib/pkp/templates/submission/submissionMetadataFormTitleFields.tpl'
		you have to store in in the plug-ins template path under this path 'submission/form/submissionMetadataFormFields.tpl'. 
		Further details can be found here: https://docs.pkp.sfu.ca/dev/plugin-guide/en/templates
		Where are templates located: https://docs.pkp.sfu.ca/pkp-theming-guide/en/html-smarty
		*/

		$templateMgr = &$params[1];
		$output = &$params[2];

		// example: the arrow is used to access the attribute smarty of the variable smarty 
		// $templateMgr = $smarty->smarty; 


		$request = Application::get()->getRequest();
		$context = $request->getContext();

		/*
		Check if the user has entered an username in the plugin settings for the geonames API (https://www.geonames.org/login). 
		The result is passed on accordingly to submissionMetadataFormFields.js as template variable. 
		*/
		$usernameGeonames = $this->getSetting($context->getId(), 'usernameGeonames');
		$templateMgr->assign('usernameGeonames', $usernameGeonames);

		/*
		In case the user repeats the step "3. Enter Metadata" in the process 'Submit an Article' and comes back to this step to make changes again, 
		the already entered data is read from the database, added to the template and displayed for the user.
		Data is loaded from the database, passed as template variable to the 'submissionMetadataFormFiels.tpl' 
	 	and requested from there in the 'submissionMetadataFormFields.js' to display coordinates in a map, the date and coverage information if available.
		*/
		$publicationDao = DAORegistry::getDAO('PublicationDAO');
		$submissionId = $request->getUserVar('submissionId');
		$publication = $publicationDao->getById($submissionId);

		$temporalProperties = $publication->getData('geoOJS::temporalProperties');
		$spatialProperties = $publication->getData('geoOJS::spatialProperties');
		$administrativeUnit = $publication->getData('coverage');

		// for the case that no data is available 
		if ($temporalProperties === null) {
			$temporalProperties = 'no data';
		}

		if ($spatialProperties === null || $spatialProperties === '{"type":"FeatureCollection","features":[],"administrativeUnits":{},"temporalProperties":{"unixDateRange":"not available","provenance":{"description":"not available","id":"not available"}}}') {
			$spatialProperties = 'no data';
		}

		if (current($administrativeUnit) === '' || $administrativeUnit === '' || $administrativeUnit === null) {
			$administrativeUnit = 'no data';
		}

		//assign data as variables to the template 
		$templateMgr->assign('temporalPropertiesFromDb', $temporalProperties);
		$templateMgr->assign('spatialPropertiesFromDb', $spatialProperties);
		$templateMgr->assign('administrativeUnitFromDb', $administrativeUnit);

		// echo "TestTesTest"; // by echo a direct output is created on the page

		// here the original template is extended by the additional template modified by geoOJS  
		$output .= $templateMgr->fetch($this->getTemplateResource('submission/form/submissionMetadataFormFields.tpl'));

		return false;
	}

	/**
	 * Function which extends ArticleMain Template by geospatial properties if available. 
	 * Data is loaded from the database, passed as template variable to the 'article_details.tpl' 
	 * and requested from there in the 'article_details.js' to display coordinates in a map, the date and coverage information if available.
	 * @param hook Templates::Article::Main
	 */
	public function extendArticleMainTemplate($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		$publication = $templateMgr->getTemplateVars('publication');
		$submission = $templateMgr->getTemplateVars('article');
		$submissionId = $submission->getId();

		// get data from database 
		$temporalProperties = $publication->getData('geoOJS::temporalProperties');
		$spatialProperties = $publication->getData('geoOJS::spatialProperties');
		$administrativeUnit = $publication->getData('coverage');

		// for the case that no data is available 
		if ($temporalProperties === null || $temporalProperties === '') {
			$temporalProperties = 'no data';
		}

		if (($spatialProperties === null || $spatialProperties === '{"type":"FeatureCollection","features":[],"administrativeUnits":{},"temporalProperties":{"unixDateRange":"not available","provenance":"not available"}}')) {
			$spatialProperties = 'no data';
		}

		if (current($administrativeUnit) === '' || $administrativeUnit === '') {
			$administrativeUnit = 'no data';
		}

		//assign data as variables to the template 
		$templateMgr->assign('temporalProperties', $temporalProperties);
		$templateMgr->assign('spatialProperties', $spatialProperties);
		$templateMgr->assign('administrativeUnit', $administrativeUnit);

		$output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/article_details.tpl'));

		return false;
	}

	/**
	 * Function which extends the ArticleMain Template by a download button for the geospatial Metadata as geoJSON. 
	 * @param hook Templates::Article::Details
	 */
	public function extendArticleDetailsTemplate($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		$output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/article_details_download.tpl'));

		return false;
	}

	/**
	 * Function which extends the schema of the publication_settings table in the database. 
	 * There are two further rows in the table one for the spatial properties, and one for the timestamp. 
	 * @param hook Schema::get::publication
	 */
	public function addToSchema($hookName, $params)
	{
		// possible types: integer, string, text 
		$schema = $params[0];

		$timestamp = '{
			"type": "string",
			"multilingual": false,
			"apiSummary": true,
			"validation": [
				"nullable"
			]
		}';
		$timestampDecoded = json_decode($timestamp);
		$schema->properties->{'geoOJS::temporalProperties'} = $timestampDecoded;

		$spatialProperties = '{
			"type": "string",
			"multilingual": false,
			"apiSummary": true,
			"validation": [
				"nullable"
			]
		}';
		$spatialPropertiesDecoded = json_decode($spatialProperties);
		$schema->properties->{'geoOJS::spatialProperties'} = $spatialPropertiesDecoded;
	}

	/**
	 * Function which fills the new fields (created by the function addToSchema) in the schema. 
	 * The data is collected using the 'submissionMetadataFormFields.js', then passed as input to the 'submissionMetadataFormFields.tpl'
	 * and requested from it in this php script by a POST-method. 
	 * @param hook Publication::edit
	 */
	function editPublication(string $hookname, array $params)
	{
		$newPublication = $params[0];
		$params = $params[2];

		$temporalProperties = $_POST['temporalProperties'];
		$spatialProperties = $_POST['spatialProperties'];
		$administrativeUnit = $_POST['administrativeUnit'];

		$exampleTimestamp = '2020-08-12 11:00 AM - 2020-08-13 07:00 PM';
		$exampleSpatialProperties = '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[7.516193389892579,51.94553466305084],[7.516193389892579,51.96447134091556],[7.56511688232422,51.96447134091556],[7.56511688232422,51.94553466305084],[7.516193389892579,51.94553466305084]]]},"properties":{"name":"TODO Administrative Unit"}}]}';
		$exampleCoverageElement = 'TODO';

		/*
		If the element to store in the database is an element which is different in different languages 
		the property "multilingual" in the function addToSchema has to be true, and you have to use a loop like this 

		$localePare = $params['title'];

		foreach ($localePare as $localeKey => $fileId) {
			$newPublication->setData('jatsParser::fullText', $htmlDocument->saveAsHTML(), $localeKey);
		}

		further information: https://github.com/Vitaliy-1/JATSParserPlugin/blob/21425c486f0f157cd8dc6b829322cd32159dd408/JatsParserPlugin.inc.php#L619 

		For elements which are not multilangual you can skip the parameter $localeKey and just do it like this: 
			$newPublication->setData('geoOJS::spatialProperties', $spatialProperties);

		Take care, function is called twice, first during Submission Workflow and also before Schedule for Publication in the Review Workflow!!!
		*/

		// null if there is no possibility to input data (metadata input before Schedule for Publication)
		if ($spatialProperties !== null) {
			$newPublication->setData('geoOJS::spatialProperties', $spatialProperties);
		}

		if ($temporalProperties !== null && $temporalProperties !== "") {
			$newPublication->setData('geoOJS::temporalProperties', $temporalProperties);
		}

		if ($administrativeUnit !== null) {
			$newPublication->setData('coverage', $administrativeUnit);
		}

		/*
		The following lines are probably needed if you want to store text in a certain language to set the local key,
		further information can be found here:
		https://github.com/Vitaliy-1/JATSParserPlugin/blob/21425c486f0f157cd8dc6b829322cd32159dd408/JatsParserPlugin.inc.php#L619 
		
		$yourdata = 100;
		$yourdata2 = '00:00:00';
		$yourdata3 = 'TesTestTest';
		$localePare = $params['title'];
		foreach ($localePare as $localeKey => $fileId) {
			continue;
		}
		$newPublication->setData('Textfeld', $yourdata3, $localeKey);
		*/
	}

	/**
	 * Not working function to edit a form before Schedule for Publication. 
	 * Possible solution can be found here: https://forum.pkp.sfu.ca/t/insert-in-submission-settings-table/61291/19?u=tnier01, 
	 * which is already implemented partly here, but commented out! 
	 */
	public function extendScheduleForPublication(string $hookName, FormComponent $form): void
	{

		// Import the FORM_METADATA constant
		import('lib.pkp.classes.components.forms.publication.PKPMetadataForm');

		if ($form->id !== 'metadata' || !empty($form->errors)) return;

		if ($form->id === 'metadata') {

			/*
			$publication = Services::get('publication');
			$temporalProperties = $publication->getData('geoOJS::temporalProperties');
			$spatialProperties = $publication->getData('geoOJS::spatialProperties');
			*/

			/*$form->addField(new \PKP\components\forms\FieldOptions('jatsParser::references', [
				'label' => 'Hello',
				'description' => 'Hello',
				'type' => 'radio',
				'options' => null,
				'value' => null
			]));*/

			// Add a plain HTML field to the form
			/*$form->addField(new FieldHTML('myFieldName', [
				'label' => 'My Field Name',
				'description' => '<p>Add any HTML code that you want.</p>
				<div id="mapdiv" style="width: 1116px; height: 400px; float: left;  z-index: 0;"></div>
				<script src="{$submissionMetadataFormFieldsJS}" type="text/javascript" defer></script>',
			]));*/
		}
	}

	/**
	 * Not working function to edit a form before Schedule for Publication. 
	 */
	public function extendScheduleForPublication2($hookName, $params)
	{
		$templateMgr = &$params[1];
		$output = &$params[2];

		// echo "<p> Hello </p>";

		// $output .= $templateMgr->fetch($this->getTemplateResource('frontend/objects/article_details.tpl'));

		return false;
	}

	/**
	 * @copydoc Plugin::getActions() - https://docs.pkp.sfu.ca/dev/plugin-guide/en/settings
	 * Function needed for Plugin Settings.
	 */
	public function getActions($request, $actionArgs)
	{

		// Get the existing actions
		$actions = parent::getActions($request, $actionArgs);
		if (!$this->getEnabled()) {
			return $actions;
		}

		// Create a LinkAction that will call the plugin's
		// `manage` method with the `settings` verb.
		$router = $request->getRouter();
		import('lib.pkp.classes.linkAction.request.AjaxModal');
		$linkAction = new LinkAction(
			'settings',
			new AjaxModal(
				$router->url(
					$request,
					null,
					null,
					'manage',
					null,
					array(
						'verb' => 'settings',
						'plugin' => $this->getName(),
						'category' => 'generic'
					)
				),
				$this->getDisplayName()
			),
			__('manager.plugins.settings'),
			null
		);

		// Add the LinkAction to the existing actions.
		// Make it the first action to be consistent with
		// other plugins.
		array_unshift($actions, $linkAction);

		return $actions;
	}

	/**
	 * @copydoc Plugin::manage() - https://docs.pkp.sfu.ca/dev/plugin-guide/en/settings#the-form-class 
	 * Function needed for Plugin Settings. 
	 */
	public function manage($args, $request)
	{
		switch ($request->getUserVar('verb')) {
			case 'settings':

				// Load the custom form
				$this->import('geoOJSPluginSettingsForm');
				$form = new geoOJSPluginSettingsForm($this);

				// Fetch the form the first time it loads, before
				// the user has tried to save it
				if (!$request->getUserVar('save')) {
					$form->initData();
					return new JSONMessage(true, $form->fetch($request));
				}

				// Validate and execute the form
				$form->readInputData();
				if ($form->validate()) {
					$form->execute();
					return new JSONMessage(true);
				}
		}
		return parent::manage($args, $request);
	}

	/**
	 * Provide a name for this plugin (plugin gallery)
	 *
	 * The name will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 */
	public function getDisplayName()
	{
		return __('plugins.generic.geoOJS.name');
	}

	/**
	 * Provide a description for this plugin (plugin gallery) 
	 *
	 * The description will appear in the Plugin Gallery where editors can
	 * install, enable and disable plugins.
	 */
	public function getDescription()
	{
		return __('plugins.generic.geoOJS.description');
	}
}
