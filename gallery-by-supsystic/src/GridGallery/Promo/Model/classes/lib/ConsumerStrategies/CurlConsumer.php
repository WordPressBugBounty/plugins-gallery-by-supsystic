<?php
require_once dirname(__FILE__) . '/AbstractConsumer.php';

/**
 * Consumes messages and sends them to a host/endpoint using the WordPress HTTP API or PHP cURL
 */
class ConsumerStrategies_CurlConsumer extends ConsumerStrategies_AbstractConsumer
{
  /**
   * @var string the host to connect to (e.g. api.mixpanel.com)
   */
  protected $_host;

  /**
   * @var string the host-relative endpoint to write to (e.g. /engage)
   */
  protected $_endpoint;

  /**
   * @var int connect_timeout The number of seconds to wait while trying to connect. Default is 5 seconds.
   */
  protected $_connect_timeout;

  /**
   * @var int timeout The maximum number of seconds to allow cURL call to execute. Default is 30 seconds.
   */
  protected $_timeout;

  /**
   * @var string the protocol to use for the cURL connection
   */
  protected $_protocol;

  /**
   * @var bool|null Legacy option retained for compatibility. Forked shell requests are disabled.
   */
  protected $_fork = null;

  /**
   * Creates a new CurlConsumer and assigns properties from the $options array
   * @param array $options
   * @throws Exception
   */
  function __construct($options)
  {
    parent::__construct($options);

    $this->_host = $options['host'];
    $this->_endpoint = $options['endpoint'];
    $this->_connect_timeout = array_key_exists('connect_timeout', $options) ? $options['connect_timeout'] : 5;
    $this->_timeout = array_key_exists('timeout', $options) ? $options['timeout'] : 30;
    $this->_protocol = array_key_exists('use_ssl', $options) && $options['use_ssl'] == true ? 'https' : 'http';
    $this->_fork = false;

    // ensure the environment is workable for the given settings
    if (!function_exists('wp_remote_post') && !function_exists('curl_init')) {
      throw new Exception('The WordPress HTTP API or cURL PHP extension is required to use the cURL consumer.');
    }
  }

  /**
   * Write to the given host/endpoint using the WordPress HTTP API or PHP's cURL extension
   * @param array $batch
   * @return bool
   */
  public function persist($batch)
  {
    if (count($batch) > 0) {
      $data = 'data=' . $this->_encode($batch);
      $url = $this->_protocol . '://' . $this->_host . $this->_endpoint;
      return $this->_execute($url, $data);
    } else {
      return true;
    }
  }

  /**
   * Write using WordPress HTTP API when available, with PHP cURL as fallback.
   * @param $url
   * @param $data
   * @return bool
   */
  protected function _execute($url, $data)
  {
    if ($this->_debug()) {
      $this->_log("Making blocking HTTP call to $url");
    }

    if (function_exists('wp_remote_post') && function_exists('is_wp_error') && function_exists('wp_remote_retrieve_body')) {
      $response = wp_remote_post($url, [
        'timeout' => $this->_timeout,
        'blocking' => true,
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => $data,
      ]);

      if (is_wp_error($response)) {
        $this->_handleError($response->get_error_code(), $response->get_error_message());
        return false;
      }

      $responseCode = function_exists('wp_remote_retrieve_response_code') ? wp_remote_retrieve_response_code($response) : 0;
      $responseBody = wp_remote_retrieve_body($response);
      if ($responseCode >= 400) {
        $this->_handleError($responseCode, $responseBody);
        return false;
      }

      if (trim($responseBody) == '1') {
        return true;
      } else {
        $this->_handleError($responseCode, $responseBody);
        return false;
      }
    }

    return $this->_execute_curl($url, $data);
  }

  /**
   * Write using the cURL php extension
   * @param $url
   * @param $data
   * @return bool
   */
  protected function _execute_curl($url, $data)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_connect_timeout);
    curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    $response = curl_exec($ch);
    if (false === $response) {
      $curl_error = curl_error($ch);
      $curl_errno = curl_errno($ch);
      curl_close($ch);
      $this->_handleError($curl_errno, $curl_error);
      return false;
    } else {
      curl_close($ch);
      if (trim($response) == '1') {
        return true;
      } else {
        $this->_handleError(0, $response);
        return false;
      }
    }
  }

  /**
   * @return int
   */
  public function getConnectTimeout()
  {
    return $this->_connect_timeout;
  }

  /**
   * @return string
   */
  public function getEndpoint()
  {
    return $this->_endpoint;
  }

  /**
   * @return bool|null
   */
  public function getFork()
  {
    return $this->_fork;
  }

  /**
   * @return string
   */
  public function getHost()
  {
    return $this->_host;
  }

  /**
   * @return array
   */
  public function getOptions()
  {
    return $this->_options;
  }

  /**
   * @return string
   */
  public function getProtocol()
  {
    return $this->_protocol;
  }

  /**
   * @return int
   */
  public function getTimeout()
  {
    return $this->_timeout;
  }
}
