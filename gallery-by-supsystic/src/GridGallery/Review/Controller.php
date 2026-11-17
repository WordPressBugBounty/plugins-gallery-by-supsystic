<?php

/**
 * Class GridGallery_Review_Controller
 * The AJAX handler checks the nonce and the access rights before it calls an action.
 */
class GridGallery_Review_Controller extends GridGallery_Core_BaseController
{
  /**
   * Saves the answer to the review card: rate, done, later, support or close.
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function respondAction(RscSgg_Http_Request $request)
  {
    $answer = $request->post->get('answer');

    /** @var GridGallery_Review_Module $module */
    $module = $this->getEnvironment()->getModule('review');
    if (!$module->saveAnswer(get_current_user_id(), is_string($answer) ? $answer : '')) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($this->translate('Unknown answer.')));
    }

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData());
  }
}
