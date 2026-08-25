<?php

class GridGallery_Ecommerce_Controller extends GridGallery_Core_BaseController
{
  public function indexAction(RscSgg_Http_Request $request)
  {
    return $this->response('@ecommerce/index.twig', [
      'upgradeUrl' => 'https://supsystic.com/plugins/photo-gallery/',
    ]);
  }
}
