<?php

/**
 * Plugin Name: Photo Gallery by Supsystic
 * Plugin URI: https://supsystic.com/plugins/photo-gallery/
 * Description: Responsive photo and image galleries with masonry, albums and a lightbox. Bulk upload, drag-and-drop ordering, Gutenberg block and Elementor widget.
 * Version: 1.21.4
 * Author: supsystic.com
 * Author URI: https://supsystic.com
 * Text Domain: grid-gallery
 **/

require_once dirname(__FILE__) . '/app/SupsysticGallery.php';

if (!defined('SGG_PLUGIN_URL')) {
  define('SGG_PLUGIN_URL', plugin_dir_url(__FILE__));
}

$supsysticGallery = new SupsysticGallery('1.21.4');
$supsysticGallery->run();
