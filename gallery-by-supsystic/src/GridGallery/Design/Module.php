<?php

/**
 * One look for every admin page of the plugin: buttons, fields, selects, toolbars and tabs. The old plugin-wide
 * stylesheet (app/assets/css/supsystic-ui.css) draws square black buttons and grey fields without side borders, often
 * with !important; this stylesheet is printed after it and brings all pages to the design of the new editors.
 */
class GridGallery_Design_Module extends RscSgg_Mvc_Module
{
  public function onInit()
  {
    parent::onInit();

    if (is_admin() && $this->getEnvironment()->isPluginPage()) {
      // Late, so it is printed after the plugin's own stylesheets
      add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 100);
    }
  }

  public function enqueueAssets()
  {
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-ds-12';
    wp_enqueue_style('sgg-admin-design', plugin_dir_url(__FILE__) . 'assets/css/admin.css', [], $version);
    wp_enqueue_script('sgg-admin-design', plugin_dir_url(__FILE__) . 'assets/js/admin.js', ['jquery'], $version, true);
  }
}
