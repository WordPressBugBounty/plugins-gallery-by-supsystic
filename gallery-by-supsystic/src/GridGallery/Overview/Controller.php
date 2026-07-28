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
    return ['sendMailAction', 'sendSubscribeMail', 'sendSubscribeRemind', 'sendSubscribeDisable'];
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
      'news' => $this->loadNews($config['post_url']),
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
   * @param RscSgg_Http_Request $request
   */
  public function sendSubscribeMailAction(RscSgg_Http_Request $request)
  {
    $apiUrl = 'https://supsystic.com/wp-admin/admin-ajax.php';
    $reqUrl = $apiUrl . '?action=ac_get_plugin_installed';
    $config = $this->getEnvironment()->getConfig();
    $mail = $request->post['route']['data'];
    $isPro = !empty($config->get('is_pro')) ? true : false;
    $data = [
      'body' => [
        'key' => 'kJ#f3(FjkF9fasd124t5t589u9d4389r3r3R#2asdas3(#R03r#(r#t-4t5t589u9d4389r3r3R#$%lfdj',
        'user_name' => $mail['username'],
        'user_email' => $mail['email'],
        'customertype' => $mail['expertise'],
        'site_url' => get_bloginfo('wpurl'),
        'site_name' => get_bloginfo('name'),
        'plugin_code' => $config->get('plugin_name'),
        'is_pro' => $isPro,
      ],
    ];
    if (!empty($this->getModule('license'))) {
      if ($isPro && $this->getModule('license')->getHelper()->_getPluginCode() == 'gallery_by_supsystic_pro') {
        $data['body']['is_pro'] = true;
      } else {
        $data['body']['is_pro'] = false;
      }
    }
    $response = wp_remote_post($reqUrl, $data);
    if (is_wp_error($response)) {
      $response = [
        'success' => false,
        'message' => $this->translate('Some errors.'),
      ];
    } else {
      $response = [
        'success' => true,
        'message' => $this->translate('Thank you for subscribtions.'),
      ];
      update_option('sgg_ac_subscribe', true);
    }
    return $this->response(RscSgg_Http_Response::AJAX, $response);
  }

  /**
   * @param RscSgg_Http_Request $request
   */
  public function sendSubscribeRemindAction(RscSgg_Http_Request $request)
  {
    update_option('sgg_ac_remind', date('Y-m-d h:i:s', time() + 86400));
    $response = ['success' => true];
    return $this->response(RscSgg_Http_Response::AJAX, $response);
  }

  /**
   * @param RscSgg_Http_Request $request
   */
  public function sendSubscribeDisableAction(RscSgg_Http_Request $request)
  {
    update_option('sgg_ac_disabled', true);
    $response = ['success' => true];
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

  public function getApiUrl()
  {
    $apiUrl = 'aHR0cDovLzU0LjY4LjE5MS4yMTcvP21vZD1vcHRpb25zJmFjdGlvbj1zYXZlVXNhZ2VTdGF0JnBsPXJjcw==';
    return base64_decode($apiUrl);
  }

  /**
   * @param string $url url with news
   * @return news body only
   */
  protected function loadNews($url)
  {
    $news = wp_remote_retrieve_body(wp_remote_get($url));

    return $news;
  }
}
