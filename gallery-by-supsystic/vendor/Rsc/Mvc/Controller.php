<?php

#[\AllowDynamicProperties]
class RscSgg_Mvc_Controller
{
  /**
   * @var RscSgg_Environment
   */
  private $environment;

  /**
   * @var RscSgg_Http_Request
   */
  private $request;

  /**
   * Constructor
   * @param RscSgg_Environment $environment
   * @param RscSgg_Http_Request $request
   */
  public function __construct(RscSgg_Environment $environment, RscSgg_Http_Request $request)
  {
    $this->environment = $environment;
    $this->request = $request;
    $this->models = [];
  }

  /**
   * @param string $method The name of the method
   * @param array $arguments An array of arguments
   * @return mixed
   * @throws BadMethodCallException If specified method does not exists
   */
  public function __call($method, $arguments)
  {
    if (!method_exists($this->environment, $method)) {
      throw new BadMethodCallException(sprintf('Unexpected method: %s', $method));
    }

    return call_user_func_array([$this->environment, $method], $arguments);
  }

  /**
   * Creates new response
   * @param string $template The name of the template
   * @param array $data An associative array of the data
   * @return RscSgg_Http_Response
   */
  public function response($template, array $data = [])
  {
    if ($template != RscSgg_Http_Response::AJAX) {
      try {
        $twig = $this->environment->getTwig();
        $content = $twig->render($template, $data);
      } catch (Exception $e) {
        wp_die($e->getMessage());
      }
    } else {
      // The plugin's scripts ask through admin-ajax.php. A plugin page has printed its HTML already: JSON there
      // would be a part of the page.
      if (!wp_doing_ajax()) {
        wp_die(esc_html__('This address answers only the requests of the plugin.', 'sgg'), '', ['response' => 400, 'back_link' => true]);
      }
      if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
      }
      wp_send_json($data, null, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS);
    }

    return RscSgg_Http_Response::create()->setContent($content);
  }

  /**
   * Generates the URL the to specified path
   * @param string $module The name of the module
   * @param string $action The name of the action
   * @param array $parameters An assoc array of parameters
   * @return string|void
   */
  public function generateUrl($module, $action = 'index', array $parameters = [])
  {
    $parameters = !empty($parameters) ? '&' . http_build_query($parameters) : null;
    $slug = $this->getEnvironment()->getMenu()->getMenuSlug();

    return admin_url('admin.php?page=' . $slug . '&module=' . $module . '&action=' . $action . $parameters);
  }

  /**
   * Makes redirects to the specified URL
   * @param string $url
   * @return \RscSgg_Http_Response
   */
  public function redirect($url)
  {
    if (!headers_sent()) {
      header(sprintf('Location: %s', $url));
      exit();
    }

    $content = '<script type="text/javascript">document.location.href = ' . wp_json_encode((string) $url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS) . '</script>';

    return RscSgg_Http_Response::create()->setContent($content);
  }

  /**
   * Returns an instance of the environment
   * @return \RscSgg_Environment
   */
  public function getEnvironment()
  {
    return $this->environment;
  }

  /**
   * Returns an instance of the current request
   * @return \RscSgg_Http_Request
   */
  public function getRequest()
  {
    return $this->request;
  }

  public function requireNonces()
  {
    return [];
  }
}
