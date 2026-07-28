<?php

/**
 * Class GridGallery_Ui_Module
 * User Interface Module
 *
 * @package GridGallery\Ui
 * @author Artur Kovalevsky
 */
#[\AllowDynamicProperties]
class GridGallery_Ui_Module extends RscSgg_Mvc_Module
{
  /**
   * @var array
   */
  protected $javascripts;

  /**
   * @var array
   */
  protected $stylesheets;

  /**
   * @var GridGallery_Ui_AssetsCollection
   */
  protected $assets;

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();
    $this->asset = new GridGallery_Ui_Assets($this);
    add_action('init', [$this, 'registerJSData']);
    $this->preload();
  }

  /**
   * Preloads the assets
   */
  public function preload()
  {
    $this->asset->enqueue('styles', $this->getBackendCSS());
    // Global
    $this->asset->enqueue('styles', [$this->getConfig()->get('plugin_url') . '/app/assets/css/supsystic-for-all-admin.css'], 'backend', true);
    $this->asset->enqueue('scripts', $this->getBackendJS());
  }

  public function getBackendCSS()
  {
    $url = $this->getEnvironment()->getConfig()->get('plugin_url');
    return [
      [
        'source' => $url . '/app/assets/css/supsystic-ui.css',
        'dependencies' => ['wp-color-picker'],
      ],
      $url . '/app/assets/css/supsystic-jgrowl.css',
      $url . '/app/assets/css/animate.css',
      $url . '/app/assets/css/minimal/minimal.css',
      $url . '/app/assets/css/libraries/fontawesome/font-awesome.min.css',
      $this->getLocationUrl() . '/css/tooltipster.css',
      SGG_PLUGIN_URL . '/app/assets/css/jquery-ui.css',
    ];
  }

  public function registerJSData()
  {
    wp_register_script('sg-ajax.js', $this->getLocationUrl() . '/js/ajax.js', ['jquery']);
    wp_localize_script('sg-ajax.js', 'SupsysticGallery', ['nonce' => wp_create_nonce('supsystic-gallery')]);
    // really assigned to /app/assets/js/grid-gallery.js Handle
    wp_localize_script('sg-ajax.js', 'sggParams', ['isRtl' => (int) is_rtl()]);
    wp_localize_script('sg-ajax.js', 'sggStandartFontsList', $this->getStandardFontsList());
    if ($this->getEnvironment()->isPro()) {
      if (version_compare($this->getEnvironment()->getConfig()->get('pro_plugin_version'), '2.7.6', '<')) {
        wp_enqueue_script('webfont-js', SGG_PLUGIN_URL . '/app/assets/js/webfont.js');
      }
    }
  }

  public function getBackendJS()
  {
    $url = $this->getEnvironment()->getConfig()->get('plugin_url');

    return [
      [
        'source' => $this->getLocationUrl() . '/js/ajax.js',
        'handle' => 'sg-ajax.js',
      ],
      [
        'source' => $url . '/app/assets/js/grid-gallery.js',
        'dependencies' => ['jquery', 'jquery-ui-dialog'],
      ],
      $url . '/app/assets/js/icheck.min.js',
      $url . '/app/assets/js/jquery.lazyload.min.js',
      $url . '/app/assets/js/jquery.jgrowl.min.js',
      //$url . '/app/assets/js/webfont.js',
      [
        'source' => $this->getLocationUrl() . '/js/colorpicker.js',
        'dependencies' => ['grid-gallery.js', 'wp-color-picker'],
      ],
      $this->getLocationUrl() . '/js/common.js',
      $this->getLocationUrl() . '/js/types.js',
      $this->getLocationUrl() . '/plugins/grid-gallery.ui.formSerialize.js',
      $this->getLocationUrl() . '/js/jquery.tooltipster.min.js',
      $this->getLocationUrl() . '/js/slimscroll.min.js',
      $this->getLocationUrl() . '/plugins/grid-gallery.ui.toolbar.js',
      $this->getLocationUrl() . '/js/checkbox-observer.js',
      $this->getLocationUrl() . '/js/toolbar.js',
      $this->getLocationUrl() . '/js/ajaxQueue.js',
    ];
  }
  public function getStandardFontsList()
  {
    return ['Georgia', 'Palatino Linotype', 'Times New Roman', 'Arial', 'Helvetica', 'Arial Black', 'Gadget', 'Comic Sans MS', 'Impact', 'Charcoal', 'Lucida Sans Unicode', 'Lucida Grande', 'Tahoma', 'Geneva', 'Trebuchet MS', 'Verdana', 'Geneva', 'Courier New', 'Courier', 'Lucida Console', 'Monaco'];
  }
}
