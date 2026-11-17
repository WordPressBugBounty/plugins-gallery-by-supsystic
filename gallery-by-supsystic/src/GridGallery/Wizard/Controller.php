<?php

/**
 * Class GridGallery_Wizard_Controller
 * Creates a gallery from the answers of the creation wizard. The AJAX handler checks the nonce and the access
 * rights before an action runs.
 */
class GridGallery_Wizard_Controller extends GridGallery_Core_BaseController
{
  /**
   * @param RscSgg_Http_Request $request title, goal, style, images[]
   * @return RscSgg_Http_Response
   */
  public function createAction(RscSgg_Http_Request $request)
  {
    $stats = $this->getEnvironment()->getModule('stats');
    if ($stats) {
      $stats->save('galleries.create');
    }
    try {
      $result = $this->getEnvironment()->getModule('wizard')->create($request);
    } catch (Exception $e) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($e->getMessage()));
    }

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData($this->translate('New gallery successfully created'), $result));
  }
}
