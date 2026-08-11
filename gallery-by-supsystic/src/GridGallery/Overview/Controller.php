<?php

/**
 * Class GridGallery_Overview_Controller
 * Overview page controller
 *
 * @package GridGallery\Overview
 */
class GridGallery_Overview_Controller extends GridGallery_Core_BaseController
{
  public function requireNonces()
  {
    return ['sendMailAction'];
  }
  /**
   * @param RscSgg_Http_Request $request
   */
  public function indexAction(RscSgg_Http_Request $request)
  {
    $serverSettings = $this->getServerSettings();
    $config = $this->getEnvironment()->getConfig();
    global $current_user;

    return $this->response('@overview/index.twig', [
      'serverSettings' => $serverSettings,
      'contactForm' => [
        'name' => $current_user->user_firstname,
        'email' => $current_user->user_email,
        'website' => get_bloginfo('url'),
      ],
    ]);
  }

  /**
   * @param RscSgg_Http_Request $request
   */
  public function sendMailAction(RscSgg_Http_Request $request)
  {
    $mail = $request->post['route']['data'];

    $headers = ['Content-Type: text/html; charset=UTF-8', 'From: ' . $mail['name'] . ' <' . $mail['email'] . '>'];

    $message = ['Name: ' . $mail['name'], 'E-mail: ' . $mail['email'], 'Website: ' . $mail['website'], 'Subject: ' . $mail['subject'], 'Topic: ' . str_replace('_', ' ', ucfirst($mail['question'])), 'Мessage: ' . $mail['message']];
    $message = implode('<br>', $message);

    $config = $this->getEnvironment()->getConfig();

    wp_mail($config['mail'], $mail['subject'], $message, $headers);

    $response = [
      'success' => true,
      'message' => $this->translate('Your message successfully send. We contact you soon.'),
    ];

    $errors = $this->getMailErrors();
    if (!empty($errors)) {
      $response = [
        'success' => false,
        'message' => $errors[0],
      ];
    }

    return $this->response(RscSgg_Http_Response::AJAX, $response);
  }

  /**
   * @return base server settings
   */
  protected function getServerSettings()
  {
    global $wpdb;

    return [
      'Operating System' => ['value' => PHP_OS],
      'PHP Version' => ['value' => PHP_VERSION],
      'Server Software' => ['value' => sanitize_text_field($_SERVER['SERVER_SOFTWARE'])],
      'MySQL version' => ['value' => $wpdb->db_version()],
      'MySQLi driver' => ['value' => $wpdb->use_mysqli ? 'Yes' : 'No'],
      'PHP Allow URL Fopen' => ['value' => ini_get('allow_url_fopen') ? 'Yes' : 'No'],
      'PHP Memory Limit' => ['value' => ini_get('memory_limit')],
      'PHP Max Post Size' => ['value' => ini_get('post_max_size')],
      'PHP Max Upload Filesize' => ['value' => ini_get('upload_max_filesize')],
      'PHP Max Script Execute Time' => ['value' => ini_get('max_execution_time')],
      'PHP EXIF Support' => ['value' => extension_loaded('exif') ? 'Yes' : 'No'],
      'PHP EXIF Version' => ['value' => phpversion('exif')],
      'PHP XML Support' => ['value' => extension_loaded('libxml') ? 'Yes' : 'No', 'error' => !extension_loaded('libxml')],
      'PHP CURL Support' => ['value' => extension_loaded('curl') ? 'Yes' : 'No', 'error' => !extension_loaded('curl')],
    ];
  }

  /**
   * @return mail send error
   */
  protected function getMailErrors()
  {
    global $ts_mail_errors;

    if (!isset($ts_mail_errors)) {
      $ts_mail_errors = [];
    }

    return $ts_mail_errors;
  }
}
