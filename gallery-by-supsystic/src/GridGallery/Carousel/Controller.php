<?php

/**
 * Class GridGallery_Carousel_Controller
 * Save and live preview of a Carousel gallery; the Slider controller extends it. The AJAX handler checks the nonce
 * and the access rights before an action runs.
 */
class GridGallery_Carousel_Controller extends GridGallery_Core_BaseController
{
  /**
   * The name of the module this controller serves.
   * @return string
   */
  protected function moduleName()
  {
    return 'carousel';
  }

  /**
   * @return GridGallery_Carousel_Module
   */
  protected function typeModule()
  {
    return $this->getEnvironment()->getModule($this->moduleName());
  }

  /**
   * Saves the settings from the editor.
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function saveAction(RscSgg_Http_Request $request)
  {
    $settings = $this->typeModule()->saveSettings((int) $request->post->get('gallery_id'), $this->input($request));
    if ($settings === null) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($this->translate('This gallery is of another type.')));
    }

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData($this->translate('Saved')));
  }

  /**
   * The gallery drawn with the unsaved settings of the editor. Nothing is stored.
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function previewAction(RscSgg_Http_Request $request)
  {
    $html = $this->typeModule()->renderShortcode(['id' => (int) $request->post->get('gallery_id')], $this->input($request));
    if ($html === null) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($this->translate('This gallery is of another type.')));
    }

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData('', ['html' => $html]));
  }

  /**
   * @param RscSgg_Http_Request $request
   * @return array
   */
  protected function input(RscSgg_Http_Request $request)
  {
    $input = $request->post->get('slider');

    return is_array($input) ? wp_unslash($input) : [];
  }
}
