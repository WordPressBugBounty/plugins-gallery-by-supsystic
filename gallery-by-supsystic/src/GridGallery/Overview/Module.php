<?php

class GridGallery_Overview_Module extends RscSgg_Mvc_Module
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    $environment = $this->getEnvironment();
    $config = $environment->getConfig();

    // Client ID
    $config->add('post_id', 637);
    $config->add('post_url', 'https://supsystic.com/news/main.html');
    $config->add('mail', 'support@supsystic.zendesk.com');

    $prefix = $config->get('hooks_prefix');

    add_action($prefix . 'after_ui_loaded', [$this, 'loadAssets']);
  }

  /**
   * Loads the assets required by the module
   */
  public function loadAssets(GridGallery_Ui_Module $ui)
  {
    if ($this->getEnvironment()->isModule('overview')) {
      $ui->asset->enqueue('styles', [$this->getLocationUrl() . '/assets/css/overview-styles.css']);
      $ui->asset->enqueue('scripts', [$this->getLocationUrl() . '/assets/js/overview-settings.js']);
    }
  }
}
