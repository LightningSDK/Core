<?php

namespace lightningsdk\core\API;

use lightningsdk\core\Tools\ClientUser;
use lightningsdk\core\Tools\Configuration;
use lightningsdk\core\Tools\Output;
use lightningsdk\core\View\API;

class ElFinder extends API {
	public function execute() {
		include_once HOME_PATH . '/js/elfinder/php/elFinderConnector.class.php';
		include_once HOME_PATH . '/js/elfinder/php/elFinder.class.php';
		include_once HOME_PATH . '/js/elfinder/php/elFinderVolumeDriver.class.php';
		include_once HOME_PATH . '/js/elfinder/php/elFinderVolumeLocalFileSystem.class.php';
		// Required for MySQL storage connector
		// include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeMySQL.class.php';
		// Required for FTP connector support
		// include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeFTP.class.php';

		/**
		 * # Dropbox volume driver need "dropbox-php's Dropbox" and "PHP OAuth extension" or "PEAR's HTTP_OAUTH package"
		 * * dropbox-php: http://www.dropbox-php.com/
		 * * PHP OAuth extension: http://pecl.php.net/package/oauth
		 * * PEAR's HTTP_OAUTH package: http://pear.php.net/package/http_oauth
		 *  * HTTP_OAUTH package require HTTP_Request2 and Net_URL2
		 */
		// Required for Dropbox.com connector support
		// include_once dirname(__FILE__).DIRECTORY_SEPARATOR.'elFinderVolumeDropbox.class.php';

		// Dropbox driver need next two settings. You can get at https://www.dropbox.com/developers
		// define('ELFINDER_DROPBOX_CONSUMERKEY',    '');
		// define('ELFINDER_DROPBOX_CONSUMERSECRET', '');
		// define('ELFINDER_DROPBOX_META_CACHE_PATH',''); // optional for `options['metaCachePath']`

		/**
		 * Simple function to demonstrate how to control file access using "accessControl" callback.
		 * This method will disable accessing files/folders starting from '.' (dot)
		 *
		 * @param  string  $attr  attribute name (read|write|locked|hidden)
		 * @param  string  $path  file path relative to volume root directory started with directory separator
		 * @return bool|null
		 **/
		function access($attr, $path, $data, $volume) {
			return strpos(basename($path), '.') === 0       // if file/folder begins with '.' (dot)
				? !($attr == 'read' || $attr == 'write')    // set read+write to false, other (locked+hidden) set to true
				:  null;                                    // else elFinder decide it itself
		}


		// Documentation for connector options:
		// https://github.com/Studio-42/elFinder/wiki/Connector-configuration-options
		if (!ClientUser::getInstance()->isAdmin()) {
			Output::http(401);
		}
		$path = HOME_PATH . '/' . Configuration::get('imageBrowser.containers.images.storage') . '/';
		$url = Configuration::get('imageBrowser.containers.images.url');
		if ($subdirectory = Configuration::get('imageBrowser.containers.images.subdirectory')) {
			$path .= $subdirectory . '/';
			$url .= $subdirectory . '/';
		}
		$opts = [
			'roots' => [
				[
					'driver'        => 'LocalFileSystem',           // driver for accessing file system (REQUIRED)
					'path'          => $path,                       // path to files (REQUIRED)
					'URL'           => $url,                        // URL to files (REQUIRED)
					'uploadDeny'    => ['all'],                     // All Mimetypes not allowed to upload
					'uploadAllow'   => ['image', 'text/plain'],     // Mimetype `image` and `text/plain` allowed to upload
					'uploadOrder'   => ['deny', 'allow'],           // allowed Mimetype `image` and `text/plain` only
					'accessControl' => 'access'                     // disable and hide dot starting files (OPTIONAL)
				]
			]
		];

		$connector = new \elFinderConnector(new \elFinder($opts));
		$connector->run();
	}
}
