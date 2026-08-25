<?php

class GridGallery_Ecommerce_Module extends RscSgg_Mvc_Module
{
  public function onInit()
  {
    parent::onInit();

    add_action($this->getConfig()->get('hooks_prefix') . 'after_ui_loaded', [$this, 'loadAssets']);
  }

  public function loadAssets(GridGallery_Ui_Module $ui)
  {
    if ($this->getEnvironment()->isModule('ecommerce')) {
      $ui->asset->enqueue('styles', [
        $this->getLocationUrl() . '/assets/css/ecommerce-promo.css',
      ]);
    }
  }
}
