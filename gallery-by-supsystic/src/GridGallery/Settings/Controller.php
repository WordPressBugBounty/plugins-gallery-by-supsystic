<?php

/**
 * Class GridGallery_Settings_Controller
 * Settings Controller
 *
 * @package GridGallery\Settings
 * @author Artur Kovalevsky
 */
class GridGallery_Settings_Controller extends GridGallery_Core_BaseController
{
  /**
   * {@inheritdoc}
   */
  protected function getModelAliases()
  {
    return [
      'settings' => 'GridGallery_Settings_Model_Settings',
    ];
  }

  /**
   * Index Action
   * Shows the settings page
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function indexAction(RscSgg_Http_Request $request)
  {
    $module = $this->getModule('settings');
    $module->loadAssets();
    $templates = $module->getTemplatesAliases();
    $settings = get_option($this->getConfig()->get('db_prefix') . 'settings');
    try {
      return $this->response($templates['settings.index'], [
        'settings' => $settings,
        'services' => $this->readServiceCredentials(),
      ]);
    } catch (Exception $e) {
      return $this->response('error.twig', ['exception' => $e]);
    }
  }

  /**
   * TinyPNG / KeyCDN credentials for the "Image Optimization & CDN" block.
   *
   * These used to be edited on the standalone Optimization page. They are read
   * and written through the exact same wp_options rows and array shape as
   * before (sgg_img_optimize_service_settings / sgg_cdn_service_settings), so
   * installs configured before the move keep working untouched and the
   * transfer/optimize code that still reads them needs no changes at all.
   *
   * The CDN password is never sent back to the browser - only whether one is
   * stored - so viewing this page can't leak it.
   *
   * @return array
   */
  private function readServiceCredentials()
  {
    $out = [
      'tinypng' => ['auth_key' => ''],
      'keycdn' => ['zone_name' => '', 'u_name' => '', 'base_ftp_path' => '', 'has_password' => false],
      'available' => false,
    ];

    if (!class_exists('GridGallery_Optimization_Model_Optimization') || !class_exists('GridGallery_Optimization_Model_Cdn')) {
      return $out;
    }

    $out['available'] = true;

    $optimizeModel = new GridGallery_Optimization_Model_Optimization();
    $optimizeSett = $optimizeModel->getServiceSettings();
    if (isset($optimizeSett['setting']['tinypng']['auth_key'])) {
      $out['tinypng']['auth_key'] = (string) $optimizeSett['setting']['tinypng']['auth_key'];
    }

    $cdnModel = new GridGallery_Optimization_Model_Cdn();
    $cdnSett = $cdnModel->getServiceSettings();
    $keycdn = isset($cdnSett['setting']['keycdn']) ? $cdnSett['setting']['keycdn'] : [];

    foreach (['zone_name', 'u_name', 'base_ftp_path'] as $field) {
      if (isset($keycdn[$field])) {
        $out['keycdn'][$field] = (string) $keycdn[$field];
      }
    }
    $out['keycdn']['has_password'] = !empty($keycdn['u_pass']);

    return $out;
  }

  /**
   * Writes the credentials back into the original option rows.
   *
   * Blank fields are treated as "leave as-is" rather than "clear", because the
   * password input is deliberately rendered empty on every page load - saving
   * the form without retyping it must not wipe a working CDN configuration.
   *
   * @param array $posted
   * @return void
   */
  private function writeServiceCredentials($posted)
  {
    if (!is_array($posted) || !class_exists('GridGallery_Optimization_Model_Optimization') || !class_exists('GridGallery_Optimization_Model_Cdn')) {
      return;
    }

    if (isset($posted['tinypng']['auth_key'])) {
      $optimizeModel = new GridGallery_Optimization_Model_Optimization();
      $settings = $optimizeModel->getServiceSettings();
      $settings['setting']['tinypng']['auth_key'] = sanitize_text_field($posted['tinypng']['auth_key']);
      GridGallery_Optimization_Model_Optimization::saveServiceSettings($settings);
    }

    if (!isset($posted['keycdn']) || !is_array($posted['keycdn'])) {
      return;
    }

    $cdnModel = new GridGallery_Optimization_Model_Cdn();
    $settings = $cdnModel->getServiceSettings();

    foreach (['zone_name', 'u_name', 'base_ftp_path'] as $field) {
      if (isset($posted['keycdn'][$field])) {
        $settings['setting']['keycdn'][$field] = sanitize_text_field($posted['keycdn'][$field]);
      }
    }

    if (!empty($posted['keycdn']['u_pass']) && class_exists('GridGallery_Optimization_Model_Encrypt')) {
      $encryptModel = new GridGallery_Optimization_Model_Encrypt();
      $encrypted = $encryptModel->encrypt($posted['keycdn']['u_pass']);
      if (false !== $encrypted) {
        $settings['setting']['keycdn']['u_pass'] = $encrypted;
      }
    }

    GridGallery_Optimization_Model_Cdn::saveServiceSettings($settings);
  }

  public function requireNonces()
  {
    return ['saveSettingsAction'];
  }

  public function saveSettingsAction(RscSgg_Http_Request $request)
  {
    $optionsName = $this->getConfig()->get('db_prefix') . 'settings';
    $currentSettings = get_option($optionsName);
    $settings = $request->post->get('settings', []);

    // Service credentials live in their own option rows with their own nested
    // shape - keep them out of the flat array_diff/intersect merge below, which
    // only handles scalar settings.
    if ($this->isPro()) {
      $this->writeServiceCredentials($request->post->get('services', []));
    }

    if (!$currentSettings) {
      $currentSettings = [];
    }

    if (!current_user_can('manage_options')) {
      if (isset($currentSettings['access_roles'])) {
        $settings['access_roles'] = $currentSettings['access_roles'];
      }
    }

    if (!$this->isPro()) {
      $settings['access_roles'] = ['administrator'];
    }

    $diff = @array_diff($settings, $currentSettings);
    $intersect = @array_intersect($settings, $currentSettings);
    $merge = array_merge($intersect, $diff);

    update_option($optionsName, $merge);
    return $this->redirect($this->generateUrl('settings'));
  }
}
