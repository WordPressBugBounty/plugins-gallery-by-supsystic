<?php
class GridGallery_Optimization_Module extends GridGallery_Core_Module
{
  public function onInit()
  {
    parent::onInit();

    $config = $this->getEnvironment()->getConfig();
    $prefix = $config->get('hooks_prefix');
    add_action($prefix . 'after_ui_loaded', [$this, 'registerAssets']);
  }

  public function registerAssets(GridGallery_Ui_Module $ui)
  {
    if ($this->getEnvironment()->isModule('optimization')) {
      $ui->asset->enqueue('styles', $this->getBackendCSS());
      $ui->asset->enqueue('scripts', $this->getBackendJS());
    }
  }

  public function getBackendCSS()
  {
    return [$this->getLocationUrl() . '/assets/css/backend.index.css'];
  }

  public function getBackendJS()
  {
    return [$this->getLocationUrl() . '/assets/js/backend.index.js'];
  }

}
