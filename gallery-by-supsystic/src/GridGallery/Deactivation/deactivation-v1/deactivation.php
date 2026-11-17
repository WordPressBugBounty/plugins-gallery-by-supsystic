<?php

/**
 * Deactivation dialog of the Supsystic plugins, version 1 (the same file ships in every free plugin; the first copy
 * loaded serves all of them, so the Plugins page gets one script and one dialog style).
 *
 * A click on "Deactivate" in the row of a registered plugin (or of its PRO, which needs the free plugin and so always
 * comes with it) opens a short dialog. The answer goes to the Supsystic update server only when the user sends it:
 * "Skip & deactivate" and "Cancel" send nothing.
 *
 * Sent: the reason, the user's comment, the versions of the plugin, WordPress and PHP, the admin language, whether PRO
 * is active, the days the plugin was used and a random id of the install. The email goes only when the user types it
 * (free) or, for a PRO plugin, the email of its license. No site address.
 *
 * Supsystic_Deactivation_V1::register([
 *   'slug' => 'google-maps-easy',            folder of the free plugin = its code on the server
 *   'name' => 'Easy Google Maps',
 *   'file' => __FILE__,                      main file of the free plugin
 *   'tables' => ['gmp_modules'],             optional: its tables without the WordPress prefix (their creation = install)
 *   'since' => callable,                     optional: when the plugin started to be used (timestamp)
 *   'pro' => [                               optional
 *     'slug' => 'google-maps-easy-pro',      folder of the PRO plugin
 *     'name' => 'Easy Google Maps PRO',
 *     'email' => callable,                   the license email ('' when there is none)
 *   ],
 * ]);
 *
 * Filters: "supsystic_deactivation_dialog" (false turns it off, gets the slug), "supsystic_deactivation_endpoint".
 */

if (!defined('ABSPATH')) {
  exit;
}

if (!class_exists('Supsystic_Deactivation_V1', false)) {
  class Supsystic_Deactivation_V1
  {
    const ENDPOINT = 'https://updates.supsystic.com/?pl=com&mod=feedback&action=deactivate';
    const SUPPORT_URL = 'https://supsystic.com/contact-us/';
    const AJAX_ACTION = 'supsystic_deactivation_v1';
    const NONCE = 'supsystic_deactivation_v1';
    const HANDLE = 'supsystic-deactivation-v1';
    // One random id per site, shared by the Supsystic plugins
    const INSTALL_ID_OPTION = 'supsystic_install_id';
    // slug => first time a plugin was seen with this code (when nothing better tells the install time)
    const SEEN_OPTION = 'supsystic_feedback_seen';
    const MAX_DETAILS = 2000;

    /** @var array slug => config */
    private static $plugins = [];
    /** @var string Folder of this copy (its assets) */
    private static $dir = '';

    /**
     * @param array $config See the top of the file
     */
    public static function register(array $config)
    {
      if (empty($config['slug']) || empty($config['name']) || empty($config['file']) || !is_admin()) {
        return;
      }
      if (!self::$plugins) {
        self::$dir = __DIR__;
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_action('wp_ajax_' . self::AJAX_ACTION, [__CLASS__, 'ajax']);
        add_action('admin_init', [__CLASS__, 'rememberSeen']);
      }
      self::$plugins[(string) $config['slug']] = $config;
    }

    /**
     * Notes the first time each plugin was seen (once; used only when its tables tell nothing).
     */
    public static function rememberSeen()
    {
      $seen = get_option(self::SEEN_OPTION);
      $seen = is_array($seen) ? $seen : [];
      $changed = false;
      foreach (array_keys(self::$plugins) as $slug) {
        if (empty($seen[$slug])) {
          $seen[$slug] = time();
          $changed = true;
        }
      }
      if ($changed) {
        update_option(self::SEEN_OPTION, $seen, false);
      }
    }

    /**
     * @param string $hook
     */
    public static function enqueue($hook)
    {
      if ($hook !== 'plugins.php' || !current_user_can('activate_plugins')) {
        return;
      }
      $targets = [];
      foreach (self::$plugins as $slug => $config) {
        if (!apply_filters('supsystic_deactivation_dialog', true, $slug)) {
          continue;
        }
        foreach (self::targets($config) as $key => $target) {
          $reasons = [];
          foreach (self::reasons($target['pro']) as $code => $reason) {
            $reasons[] = ['code' => $code] + $reason;
          }
          $targets[] = [
            'plugin' => $slug,
            'key' => $key,
            'basename' => $target['basename'],
            'intro' => sprintf('Why are you deactivating %s? One click is enough, and it helps us make the plugin better.', $target['name']),
            'privacy' => $target['pro']
              ? 'Sent with the email of your license, so we can get back to you: the reason, your comment, the versions of the plugin, WordPress and PHP and the admin language. No site address.'
              : 'Sent: the reason, your comment and your email if you leave them, the versions of the plugin, WordPress and PHP and the admin language. No site address.',
            'email' => $target['pro'] ? ['mode' => 'license', 'value' => self::licenseEmail($config)] : ['mode' => 'input', 'value' => ''],
            'reasons' => $reasons,
          ];
        }
      }
      if (!$targets) {
        return;
      }
      $url = plugins_url('', self::$dir . '/deactivation.php');
      $version = '1.0.' . (int) @filemtime(self::$dir . '/deactivation.js');
      wp_enqueue_style(self::HANDLE, $url . '/deactivation.css', [], $version);
      wp_enqueue_script(self::HANDLE, $url . '/deactivation.js', [], $version, true);
      wp_add_inline_script(self::HANDLE, 'window.supsysticDeactivationV1 = ' . wp_json_encode([
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => self::AJAX_ACTION,
        'nonce' => wp_create_nonce(self::NONCE),
        'supportUrl' => self::SUPPORT_URL,
        'maxDetails' => self::MAX_DETAILS,
        'targets' => $targets,
        'texts' => apply_filters('supsystic_deactivation_texts', [
          'title' => 'Quick question before you go',
          'quick' => 'Just for a moment:',
          'main' => 'Or the reason is:',
          'help' => 'Our support team may be able to sort this out.',
          'helpLink' => 'Contact support',
          'emailLabel' => 'Your email for a reply (optional)',
          'emailHint' => 'Leave it if you want us to get back to you about this.',
          'emailError' => 'Please check the email address, or leave the field empty.',
          'licenseEmail' => 'We will reply to the email of your license:',
          'submit' => 'Submit & deactivate',
          'skip' => 'Skip & deactivate',
          'cancel' => 'Cancel',
          'sending' => 'Deactivating…',
          'close' => 'Close',
        ]),
      ]) . ';', 'before');
    }

    /**
     * AJAX: sends the answer to the update server. The dialog deactivates the plugin whatever the result.
     */
    public static function ajax()
    {
      if (!check_ajax_referer(self::NONCE, 'nonce', false) || !current_user_can('activate_plugins')) {
        wp_send_json_error(['message' => 'forbidden'], 403);
      }
      $slug = isset($_POST['plugin']) && is_string($_POST['plugin']) ? sanitize_key(wp_unslash($_POST['plugin'])) : '';
      $key = isset($_POST['target']) && is_string($_POST['target']) ? sanitize_key(wp_unslash($_POST['target'])) : '';
      $config = isset(self::$plugins[$slug]) ? self::$plugins[$slug] : null;
      $targets = $config ? self::targets($config) : [];
      if (!isset($targets[$key])) {
        wp_send_json_error(['message' => 'target'], 400);
      }
      $target = $targets[$key];
      $reason = isset($_POST['reason']) && is_string($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
      if (!isset(self::reasons($target['pro'])[$reason])) {
        wp_send_json_error(['message' => 'reason'], 400);
      }
      $details = isset($_POST['details']) && is_string($_POST['details']) ? sanitize_textarea_field(wp_unslash($_POST['details'])) : '';
      $details = function_exists('mb_substr') ? mb_substr($details, 0, self::MAX_DETAILS) : substr($details, 0, self::MAX_DETAILS);

      if ($target['pro']) {
        $email = self::licenseEmail($config);
        $emailSource = $email !== '' ? 'license' : '';
      } else {
        $typed = isset($_POST['email']) && is_string($_POST['email']) ? trim(wp_unslash($_POST['email'])) : '';
        $email = $typed !== '' ? sanitize_email($typed) : '';
        if ($typed !== '' && ($email === '' || !is_email($email))) {
          wp_send_json_error(['message' => 'email'], 400);
        }
        $emailSource = $email !== '' ? 'form' : '';
      }

      global $wp_version;
      $response = wp_remote_post(apply_filters('supsystic_deactivation_endpoint', self::ENDPOINT, $slug), [
        'timeout' => 5,
        'redirection' => 0,
        // The default user agent of WordPress carries the site address: this one does not
        'user-agent' => 'WordPress/' . $wp_version . '; ' . $target['slug'] . '/' . $target['version'],
        'body' => [
          'plugin' => $target['slug'],
          'plugin_name' => $target['name'],
          'plugin_version' => $target['version'],
          'reason' => $reason,
          'details' => $details,
          'email' => $email,
          'email_source' => $emailSource,
          'used_days' => self::usedDays($config),
          'is_pro' => $target['pro'] || isset($targets['pro']) ? 1 : 0,
          'wp_version' => (string) $wp_version,
          // Without the build suffix of the distribution (8.1.2-1ubuntu2.14 -> 8.1.2)
          'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
          'locale' => get_user_locale(),
          'is_multisite' => is_multisite() ? 1 : 0,
          'install_id' => self::installId(),
        ],
      ]);

      wp_send_json_success(['sent' => !is_wp_error($response) && (int) wp_remote_retrieve_response_code($response) === 200]);
    }

    /**
     * The rows the dialog is for: the free plugin and, when it is active, its PRO.
     * @param array $config
     * @return array key => ['slug', 'name', 'basename', 'version', 'pro' => bool]
     */
    private static function targets(array $config)
    {
      $basename = plugin_basename($config['file']);
      $targets = [
        'free' => [
          'slug' => (string) $config['slug'],
          'name' => (string) $config['name'],
          'basename' => $basename,
          'version' => self::version(WP_PLUGIN_DIR . '/' . $basename),
          'pro' => false,
        ],
      ];
      if (!empty($config['pro']['slug'])) {
        $proBasename = self::findActive((string) $config['pro']['slug']);
        if ($proBasename !== '') {
          $targets['pro'] = [
            'slug' => (string) $config['pro']['slug'],
            'name' => !empty($config['pro']['name']) ? (string) $config['pro']['name'] : $config['name'] . ' PRO',
            'basename' => $proBasename,
            'version' => self::version(WP_PLUGIN_DIR . '/' . $proBasename),
            'pro' => true,
          ];
        }
      }

      return $targets;
    }

    /**
     * Basename of the active plugin in this folder, or ''.
     * @param string $folder
     * @return string
     */
    private static function findActive($folder)
    {
      if (!function_exists('get_plugins') || !function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
      }
      foreach (array_keys(get_plugins('/' . $folder)) as $file) {
        $basename = $folder . '/' . $file;
        if (is_plugin_active($basename) || is_plugin_active_for_network($basename)) {
          return $basename;
        }
      }

      return '';
    }

    /**
     * @param string $file
     * @return string
     */
    private static function version($file)
    {
      $data = is_file($file) ? get_file_data($file, ['Version' => 'Version']) : [];

      return isset($data['Version']) ? (string) $data['Version'] : '';
    }

    /**
     * Reasons of the dialog. The codes are the ones the update server knows (src/Feedback/Reasons.php there).
     * "quick" reasons are a moment off (buttons in one row), "main" ones are the real reasons.
     * @param bool $pro
     * @return array code => ['label', 'placeholder', 'help' => bool, 'group' => 'quick'|'main']
     */
    private static function reasons($pro)
    {
      $r = function ($label, $placeholder = '', $help = false, $group = 'main') {
        return ['label' => $label, 'placeholder' => $placeholder, 'help' => $help, 'group' => $group];
      };
      $reasons = [
        'temporary' => $r('Debugging a problem', '', false, 'quick'),
        'maintenance' => $r('Site maintenance', '', false, 'quick'),
        'reinstall' => $r('Updating or reinstalling', '', false, 'quick'),
        'broke_site' => $r('It broke my site or showed errors', 'What happened? An error message helps us find the cause.', true),
        'not_working' => $r('It did not work as I expected', 'What did you expect it to do?', true),
      ];
      if ($pro) {
        $reasons['license_issue'] = $r('A license or activation problem', 'What went wrong with the license?', true);
      }
      $reasons['missing_feature'] = $r('A feature I need is missing', 'Which feature do you need?');
      $reasons['hard_to_use'] = $r('It is too hard to set up or use', 'Where did you get stuck?', true);
      if ($pro) {
        $reasons['free_enough'] = $r('The free version is enough for me', 'Which PRO features did you expect to use?');
        $reasons['too_expensive'] = $r('It is too expensive, I am not renewing', 'What would make it worth it for you?');
      } else {
        $reasons['slow'] = $r('It slowed down my site', 'Which pages got slower?');
      }
      $reasons['better_plugin'] = $r('I found a better plugin', 'Which plugin, and what does it do better?');
      $reasons['no_longer_needed'] = $r('I no longer need it');
      $reasons['other'] = $r('Other', 'Please tell us the reason.');

      return $reasons;
    }

    /**
     * @param array $config
     * @return string The email of the PRO license, or ''
     */
    private static function licenseEmail(array $config)
    {
      if (empty($config['pro']['email']) || !is_callable($config['pro']['email'])) {
        return '';
      }
      try {
        $email = call_user_func($config['pro']['email']);
      } catch (\Throwable $e) {
        return '';
      }
      $email = is_string($email) ? sanitize_email(trim($email)) : '';

      return $email !== '' && is_email($email) ? $email : '';
    }

    /**
     * Days since the plugin started to be used: its own answer, else the creation of its tables, else the first time
     * this library saw it. '' when unknown.
     * @param array $config
     * @return string
     */
    private static function usedDays(array $config)
    {
      global $wpdb;

      $since = 0;
      if (!empty($config['since']) && is_callable($config['since'])) {
        try {
          $since = (int) call_user_func($config['since']);
        } catch (\Throwable $e) {
          $since = 0;
        }
      }
      if ($since <= 0 && !empty($config['tables']) && is_array($config['tables'])) {
        $names = [];
        foreach ($config['tables'] as $table) {
          $names[] = $wpdb->prefix . preg_replace('/[^A-Za-z0-9_]/', '', (string) $table);
        }
        $suppress = $wpdb->suppress_errors(true);
        $created = $wpdb->get_var($wpdb->prepare(
          'SELECT UNIX_TIMESTAMP(MIN(CREATE_TIME)) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . implode(',', array_fill(0, count($names), '%s')) . ')',
          $names
        ));
        $wpdb->suppress_errors($suppress);
        $since = (int) $created;
      }
      $seen = get_option(self::SEEN_OPTION);
      if (is_array($seen) && !empty($seen[$config['slug']])) {
        $since = $since > 0 ? min($since, (int) $seen[$config['slug']]) : (int) $seen[$config['slug']];
      }
      if ($since <= 0 || $since > time()) {
        return '';
      }

      return (string) (int) floor((time() - $since) / DAY_IN_SECONDS);
    }

    /**
     * A random id of the site (32 hex characters), made once. It only tells that two answers came from the same site.
     * @return string
     */
    private static function installId()
    {
      $id = get_option(self::INSTALL_ID_OPTION);
      if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/', $id)) {
        $id = md5(wp_generate_password(64, true, true) . microtime());
        update_option(self::INSTALL_ID_OPTION, $id, false);
      }

      return $id;
    }
  }
}
