<?php
class GridGallery_Optimization_Module extends GridGallery_Core_Module
{
  public function onInit()
  {
    parent::onInit();

    $config = $this->getEnvironment()->getConfig();
    $prefix = $config->get('hooks_prefix');
    add_action($prefix . 'after_ui_loaded', [$this, 'registerAssets']);

    // Auto-CDN: keep the CDN copy of a gallery in step with its images.
    add_action('sgg_add_new_image_to_gallery', [$this, 'onGalleryImageAdded']);
    add_action('sgg_auto_cdn_transfer', [$this, 'runAutoCdnTransfer']);
  }

  /**
   * Queues a CDN upload for a gallery that just gained an image, when that
   * gallery has auto-transfer switched on.
   *
   * The upload is deferred to a one-off cron event rather than run inline: an
   * FTP round trip inside the media-upload request would stall the uploader UI
   * for the user, and a failing CDN would make adding images look broken.
   *
   * @param array $imageParams
   * @return void
   */
  public function onGalleryImageAdded($imageParams)
  {
    if (!is_array($imageParams) || empty($imageParams['gallery_id'])) {
      return;
    }

    $galleryId = (int) $imageParams['gallery_id'];
    if (!$galleryId || !$this->galleryWantsAutoCdn($galleryId)) {
      return;
    }

    if (!wp_next_scheduled('sgg_auto_cdn_transfer', [$galleryId])) {
      // Small delay so a multi-file upload results in one transfer rather
      // than one per file.
      wp_schedule_single_event(time() + 60, 'sgg_auto_cdn_transfer', [$galleryId]);
    }
  }

  /**
   * Cron handler: pushes the whole gallery to the CDN using the credentials
   * stored in Advanced Settings.
   *
   * @param int $galleryId
   * @return void
   */
  public function runAutoCdnTransfer($galleryId)
  {
    $galleryId = (int) $galleryId;
    if (!$galleryId) {
      return;
    }

    $cdnModel = new GridGallery_Optimization_Model_Cdn();
    if ($cdnModel->checkRequirements()) {
      return;
    }

    $settings = $cdnModel->getServiceSettings();
    $current = isset($settings['current']) ? $settings['current'] : 'keycdn';
    $params = isset($settings['setting'][$current]) ? $settings['setting'][$current] : [];

    if (empty($params['u_name']) || empty($params['u_pass']) || empty($params['zone_name'])) {
      return;
    }

    $encryptModel = new GridGallery_Optimization_Model_Encrypt();
    $password = $encryptModel->decrypt($params['u_pass']);
    if (false === $password) {
      return;
    }

    $resourcesModel = new GridGallery_Galleries_Model_Resources();
    $photosModel = new GridGallery_Photos_Model_Photos();
    $attachmentModel = new GridGallery_Galleries_Attachment();

    $resources = $resourcesModel->getByGalleryId($galleryId);
    if (empty($resources)) {
      return;
    }

    $photos = $photosModel->getPhotos($resources);
    if (empty($photos)) {
      return;
    }

    $ftpModel = new GridGallery_Optimization_Model_Ftp([
      'host' => 'ftp.keycdn.com',
      'port' => null,
      'ftpUsername' => $params['u_name'],
      'ftpPassword' => $password,
      'folderName' => isset($params['base_ftp_path']) ? $params['base_ftp_path'] : null,
    ]);

    if (isset($_SERVER['HTTP_X_REQUEST_SCHEME'])) {
      $serverName = sanitize_text_field($_SERVER['HTTP_X_REQUEST_SCHEME']) . '://' . sanitize_text_field($_SERVER['HTTP_HOST']);
    } elseif (isset($_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_HOST'])) {
      $serverName = sanitize_text_field($_SERVER['REQUEST_SCHEME']) . '://' . sanitize_text_field($_SERVER['HTTP_HOST']);
    } else {
      $serverName = site_url();
    }

    $total = 0;

    foreach ($photos as $photo) {
      $attachment = isset($photo->attachment) ? (array) $photo->attachment : [];
      if (empty($attachment['url'])) {
        continue;
      }

      $path = $attachmentModel->replaceUrlToFilePath($attachment['url']);
      if (!$path || !is_file($path)) {
        continue;
      }

      try {
        $remote = str_replace($serverName, '', $attachment['url']);
        $ftpModel->uploadFileOnServer($remote, realpath($path));
        $total += (int) filesize($path);
      } catch (Exception $e) {
        // A cron run has nobody to report to - stop quietly and let the next
        // image change (or a manual "Transfer now") retry.
        return;
      }
    }

    if ($total > 0) {
      $cdnModel->save([
        'gallery_id' => $galleryId,
        'last_transfer_date' => date('Y-m-d'),
        'service_code' => $current,
        'size' => $total,
      ]);
    }
  }

  /**
   * @param int $galleryId
   * @return bool
   */
  private function galleryWantsAutoCdn($galleryId)
  {
    $settingsModel = new GridGallery_Galleries_Model_Settings();
    $stored = $settingsModel->get($galleryId);
    $data = $stored && isset($stored->data) && is_array($stored->data) ? $stored->data : [];
    $cdn = isset($data['cdn']) && is_array($data['cdn']) ? $data['cdn'] : [];

    return isset($cdn['enabled'], $cdn['auto']) && $cdn['enabled'] === 'true' && $cdn['auto'] === '1';
  }

  public function registerAssets(GridGallery_Ui_Module $ui)
  {
    if ($this->getEnvironment()->isModule('optimization')) {
      $ui->asset->enqueue('styles', $this->getBackendCSS());
      $ui->asset->enqueue('scripts', $this->getBackendJS());
    }
  }

  public function getBackendCSS()
  {
    return [$this->getLocationUrl() . '/assets/css/backend.index.css'];
  }

  public function getBackendJS()
  {
    return [$this->getLocationUrl() . '/assets/js/backend.index.js'];
  }

}
