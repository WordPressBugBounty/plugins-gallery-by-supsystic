<?php
class GridGallery_Optimization_Controller extends GridGallery_Core_BaseController
{
  public function requireNonces()
  {
    return ['saveSettingsAction', 'getPhotoListAction', 'optimizeOneImageAction', 'saveOptimizeInfoToDbAction', 'rollbackRestoredImgAction', 'saveCdnSettingsAction', 'transferOneImageAction', 'saveCdnInfoToDbAction', 'runGalleryOptimizeAction', 'runGalleryCdnAction'];
  }

  protected function getModelAliases()
  {
    return [
      'galleries' => 'GridGallery_Galleries_Model_Galleries',
      'resources' => 'GridGallery_Galleries_Model_Resources',
      'settings' => 'GridGallery_Galleries_Model_Settings',
      'photos' => 'GridGallery_Photos_Model_Photos',
      'optimization' => 'GridGallery_Optimization_Model_Optimization',
      'imageOptimize' => 'GridGallery_Optimization_Model_ImageOptimize',
      'cdn' => 'GridGallery_Optimization_Model_Cdn',
      'encrypt' => 'GridGallery_Optimization_Model_Encrypt',
      'serverOptimize' => 'GridGallery_Optimization_Model_ServerOptimize',
    ];
  }

  /**
   * The standalone Optimization page is retired - optimization and CDN are
   * per-gallery options now. Existing bookmarks and stale links land on the
   * gallery list instead of a dead screen; the module itself stays registered
   * because its models, tables and transfer code still back those options.
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function indexAction(RscSgg_Http_Request $request)
  {
    return $this->redirect($this->generateUrl('galleries'));
  }

  public function legacyIndexAction(RscSgg_Http_Request $request)
  {
    $imageOptimizeModel = $this->getModel('imageOptimize');
    $requirements = $imageOptimizeModel->checkRequirements();

    if (!$requirements) {
      $imgOptimizationSett = $this->getModel('optimization')->getServiceSettings();
      $galleryList = $this->getModel('galleries')->getList();
      $this->getModel('imageOptimize')->extendGalleryImageOptimizeInfo($galleryList);
      $this->getModel('resources')->extendGalleryPhotoCount($galleryList);
      $statistic = $imageOptimizeModel->getStatistic();

      $cdnModel = $this->getModel('cdn');
      // check cdn table exists
      $cdnRequirements = $cdnModel->checkRequirements();
      if (!$cdnRequirements) {
        $cdnSett = $cdnModel->getServiceSettings();
        $cdnModel->extendGalleryList($galleryList);
      } else {
        $cdnSett = null;
      }

      $tabName = null;
      if (isset($request->query['sggtab'])) {
        $tabAllowList = ['img', 'cdn'];
        $tabName = strtolower($request->query['sggtab']);
        if (!in_array($tabName, $tabAllowList)) {
          $tabName = null;
        }
      }

      return $this->response('@optimization/index.twig', [
        'imgOptimizationSett' => $imgOptimizationSett,
        'galleryList' => $galleryList,
        'statistic' => $statistic,
        'tabName' => $tabName,
        'cdnSett' => $cdnSett,
        'cdnRequirements' => $cdnRequirements,
      ]);
    } else {
      return $this->response('@optimization/error.twig', [
        'info' => $requirements,
      ]);
    }
  }

  public function saveSettingsAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;

    $data = isset($request->post['route']['data']) ? $request->post['route']['data'] : null;
    if ($data) {
      if (isset($data['setting_type'])) {
        if ($data['setting_type'] == 'tinypng' && !empty($data['params']['auth_key'])) {
          $settings = $this->getModel('optimization')->getServiceSettings();
          $settings['setting']['tinypng']['auth_key'] = $data['params']['auth_key'];
          $this->getModel('optimization')->saveServiceSettings($settings);

          $message = $this->translate('Auth key saved!');
          $isSuccess = true;
        }
      }
    }
    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
    ]);
  }

  public function saveCdnSettingsAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;

    $data = isset($request->post['route']['data']) ? $request->post['route']['data'] : null;
    if ($data) {
      if (isset($data['setting_type'])) {
        $cdnModel = $this->getModel('cdn');
        $encryptModel = $this->getModel('encrypt');
        if ($data['setting_type'] == 'keycdn' && !empty($data['params']['zone_name'])) {
          $settings = $cdnModel->getServiceSettings();
          $settings['setting']['keycdn']['zone_name'] = $data['params']['zone_name'];
          $settings['setting']['keycdn']['u_name'] = !empty($data['params']['u_name']) ? $data['params']['u_name'] : null;
          $settings['setting']['keycdn']['base_ftp_path'] = !empty($data['params']['base_ftp_path']) ? $data['params']['base_ftp_path'] : null;

          $password = !empty($data['params']['u_pass']) ? $data['params']['u_pass'] : '';
          $encryptedPassword = $encryptModel->encrypt($password);

          if (false === $encryptedPassword) {
            $message = $this->translate('Encryption is unavailable. Please check OpenSSL and AUTH_KEY settings.');
          } else {
            $settings['setting']['keycdn']['u_pass'] = $encryptedPassword;

            $cdnModel->saveServiceSettings($settings);
            $message = $this->translate('Service data was saved!');
            $isSuccess = true;
          }
        }
      }
    }
    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
    ]);
  }

  public function getPhotoListAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;

    $photos = [];
    $route = $request->post->get('route');

    if (isset($route['data']) && isset($route['data']['galleries'])) {
      $isSuccess = true;
      $message = 'Galleries info loaded';
      $somePhoto = null;
      $galleryInfo = [];

      $galleryArr = $route['data']['galleries'];
      if (count($galleryArr) > 0) {
        $optimizePreview = false;
        $attachmentSimpleModel = new GridGallery_Galleries_Attachment();
        $resources = $this->getModel('resources');
        $photoModel = $this->getModel('photos');

        if (isset($route['data']['optimize-preview']) && $route['data']['optimize-preview'] == 1) {
          $optimizePreview = true;
        }

        $gallKeyList = array_keys($galleryArr);
        foreach ($gallKeyList as $galleryId) {
          $galleryId = intval($galleryId);
          if ($galleryId) {
            if (isset($galleryArr[$galleryId]['imglist']) && is_array($galleryArr[$galleryId]['imglist'])) {
              $somePhoto = $galleryArr[$galleryId]['imglist'];
            }
            $galleryInfo[$galleryId]['size'] = 0;
            $currGallerySettings = $this->getModel('settings')->get($galleryId);
            $currGalleryResourcesData = $resources->getByGalleryId($galleryId);
            $currGalleryPhotoInfo = $photoModel->getPhotos($currGalleryResourcesData);

            if (count($currGalleryPhotoInfo) > 0) {
              if ($optimizePreview && isset($currGallerySettings->data)) {
                $settingsForWmPreview = GridGallery_Galleries_Attachment::prepareWmImgParamsFromGallerySett($currGallerySettings);
              }

              foreach ($currGalleryPhotoInfo as $onePhoto) {
                if (isset($onePhoto->attachment['url'])) {
                  $currFilePath = $attachmentSimpleModel->replaceUrlToFilePath($onePhoto->attachment['url']);

                  if ($somePhoto == 0) {
                    $photos[$galleryId][] = $onePhoto->attachment['url'];
                  }
                  $galleryInfo[$galleryId]['size'] += filesize($currFilePath);

                  if ($optimizePreview) {
                    // get Thumbnail Image Url
                    $calcAttachUrl = $attachmentSimpleModel->getAttachment(
                      $onePhoto->attachment_id,
                      $settingsForWmPreview['photo_width'],
                      $settingsForWmPreview['photo_height'],
                      isset($onePhoto->attachment['cropPosition']) ? $onePhoto->attachment['cropPosition'] : null,
                      $settingsForWmPreview['crop_quality'],
                    );
                    if ($calcAttachUrl && $onePhoto->attachment['url'] != $calcAttachUrl) {
                      $currFilePath = $attachmentSimpleModel->replaceUrlToFilePath($calcAttachUrl);
                      if ($somePhoto == 0) {
                        $photos[$galleryId][] = $calcAttachUrl;
                      }
                      $galleryInfo[$galleryId]['size'] += filesize($currFilePath);
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
      'photos' => $photos,
      'galleryInfo' => $galleryInfo,
    ]);
  }

  public function getCdnPhotoListAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;

    $photos = [];
    $route = $request->post->get('route');

    if (isset($route['data']) && isset($route['data']['galleries'])) {
      $isSuccess = true;
      $message = 'Galleries info loaded';
      $galleryInfo = [];

      $galleryArr = $route['data']['galleries'];
      if (count($galleryArr) > 0) {
        $this->prepareCdnPhotoList($route, $galleryArr, $photos, $galleryInfo);
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
      'photos' => $photos,
      'galleryInfo' => $galleryInfo,
    ]);
  }

  public function prepareCdnPhotoList($route, $galleryArr, &$photos, &$galleryInfo)
  {
    $optimizePreview = false;
    $attachmentSimpleModel = new GridGallery_Galleries_Attachment();
    $resources = $this->getModel('resources');
    $photoModel = $this->getModel('photos');

    if (isset($route['data']['optimize-preview']) && $route['data']['optimize-preview'] == 1) {
      $optimizePreview = true;
    }

    $gallKeyList = array_keys($galleryArr);
    foreach ($gallKeyList as $galleryId) {
      $galleryId = intval($galleryId);
      if ($galleryId) {
        $galleryInfo[$galleryId]['size'] = 0;
        $currGallerySettings = $this->getModel('settings')->get($galleryId);
        $currGalleryResourcesData = $resources->getByGalleryId($galleryId);
        $currGalleryPhotoInfo = $photoModel->getPhotos($currGalleryResourcesData);

        if (count($currGalleryPhotoInfo) > 0) {
          if ($optimizePreview && isset($currGallerySettings->data)) {
            $settingsForWmPreview = GridGallery_Galleries_Attachment::prepareWmImgParamsFromGallerySett($currGallerySettings);
          }

          foreach ($currGalleryPhotoInfo as $onePhoto) {
            if (isset($onePhoto->attachment['url'])) {
              $photoToAdd = [];
              $photoToAdd['img_url'] = $onePhoto->attachment['url'];
              $photoToAdd['attachment_id'] = $onePhoto->attachment_id;
              $galleryInfo[$galleryId]['size'] += filesize($attachmentSimpleModel->replaceUrlToFilePath($photoToAdd['img_url']));

              if ($optimizePreview) {
                // get Thumbnail Image Url
                $calcAttachUrl = $attachmentSimpleModel->getAttachment(
                  $onePhoto->attachment_id,
                  $settingsForWmPreview['photo_width'],
                  $settingsForWmPreview['photo_height'],
                  isset($onePhoto->attachment['cropPosition']) ? $onePhoto->attachment['cropPosition'] : null,
                  $settingsForWmPreview['crop_quality'],
                );
                if ($calcAttachUrl && $onePhoto->attachment['url'] != $calcAttachUrl) {
                  $photoToAdd['preview_url'] = $calcAttachUrl;
                  $galleryInfo[$galleryId]['size'] += filesize($attachmentSimpleModel->replaceUrlToFilePath($photoToAdd['preview_url']));
                }
              }
              $photos[$galleryId][] = $photoToAdd;
            }
          }
        }
      }
    }
    return true;
  }

  public function optimizeOneImageAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;
    $serviceError = null;

    $route = $request->post->get('route');
    if (isset($route['data'])) {
      $data = $route['data'];
      if (isset($route['data']['currentServiceCode']) && isset($route['data']['auth_data']) && isset($route['data']['url'])) {
        if ($route['data']['currentServiceCode'] == 'tinypng') {
          require_once 'lib/ImageOptimizeInterface.php';
          require_once 'lib/Tinify/Exception.php';
          require_once 'lib/Tinify/ResultMeta.php';
          require_once 'lib/Tinify/Result.php';
          require_once 'lib/Tinify/Source.php';
          require_once 'lib/Tinify/Client.php';
          require_once 'lib/Tinify/Tinify.php';
          $service = new Tinify_Tinify();
        }

        if (isset($service)) {
          $answer = [];
          $attachmentModel = new GridGallery_Galleries_Attachment();
          $currFilePath = $attachmentModel->replaceUrlToFilePath($route['data']['url']);
          $isFileRestored = false;

          // only when we restore the copy of file
          if ($data['restoreSrc'] == 1) {
            $restoreUrl = GridGallery_Optimization_Model_Optimization::addSubFolderToUrl($route['data']['url'], GridGallery_Optimization_Model_Optimization::$restoreSubFolder);
            $restoreFilePath = $attachmentModel->replaceUrlToFilePath($restoreUrl);
            $restoreDirectory = dirname($restoreFilePath);

            if (!file_exists($restoreDirectory)) {
              if (!mkdir($restoreDirectory, 0777, true)) {
                $message = $this->translate("Can't create restore directory!");
              }
            }
            // restore file once
            if (!file_exists($restoreFilePath)) {
              if (!copy($currFilePath, $restoreFilePath)) {
                $message = $this->translate("Can't create restore file!");
                $isFileRestored = null;
              } else {
                $answer['restoreSize'] = GridGallery_Optimization_Model_Optimization::getSizeInMb(filesize($restoreFilePath));
                $answer['restoreUrl'] = $restoreUrl;
                $isFileRestored = true;
              }
            }
          }

          if ($isFileRestored === false) {
            $answer['restoreUrl'] = $route['data']['url'];
            $answer['restoreSize'] = GridGallery_Optimization_Model_Optimization::getSizeInMb(filesize($currFilePath));
          }

          if (isset($answer['restoreSize'])) {
            try {
              if (!$service->setConfiguration($route['data']['auth_data'])) {
                $message = $this->translate('Error! Incorrect auth params!');
              } else {
                $service->optimizeImage([
                  'fileSrc' => realpath($currFilePath),
                  'fileDest' => realpath($currFilePath),
                ]);
                $answer['optSize'] = GridGallery_Optimization_Model_Optimization::getSizeInMb(filesize($currFilePath));
                $isSuccess = true;
              }
            } catch (Exception $e1) {
              $message = $e1->getMessage();
              $serviceError = true;
            }
          }
        }
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
      'serviceError' => $serviceError,
      'imgInfo' => isset($answer) ? $answer : null,
    ]);
  }

  public function transferOneImageAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;
    $serviceError = false;
    $route = $request->post->get('route');

    if (isset($route['data'])) {
      $data = $route['data'];
      if (isset($data['auth_data']) && isset($data['auth_data']['setting']) && isset($data['auth_data']['current']) && isset($data['auth_data']['setting'][$data['auth_data']['current']]) && $data['photoObj']) {
        $settings = $data['auth_data']['setting'][$data['auth_data']['current']];
        if ($data['auth_data']['current'] == 'keycdn') {
          if (isset($settings['base_ftp_path']) && isset($settings['u_name']) && isset($settings['u_pass']) && isset($settings['zone_name'])) {
            $encryptModel = $this->getModel('encrypt');
            $decryptedPassword = $encryptModel->decrypt($settings['u_pass']);

            if (false === $decryptedPassword) {
              $serviceError = true;
              $message = $this->translate('Error! Incorrect service params!');
            } else {
              $ftpModel = new GridGallery_Optimization_Model_Ftp([
                'host' => 'ftp.keycdn.com',
                'port' => null,
                'ftpUsername' => $settings['u_name'],
                'ftpPassword' => $decryptedPassword,
                'folderName' => $settings['base_ftp_path'],
              ]);

              $attachmentSimpleModel = new GridGallery_Galleries_Attachment();
              try {
                // upload image and preview
                $this->transferToCdnOnePhotoObj($ftpModel, $attachmentSimpleModel, $data['photoObj'], $data['isDelete']);
                $isSuccess = true;
              } catch (Exception $e1) {
                $message = $e1->getMessage();
                if ($ftpModel->authError) {
                  $serviceError = true;
                }
              }
            }
          } else {
            $serviceError = true;
            $message = $this->translate('Error! Incorrect service params!');
          }
        } else {
          $message = $this->translate('Error! Incorrect selected service!');
        }
      }
    } else {
      $message = $this->translate('Error! Incorrect params!');
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
      'serviceError' => $serviceError,
    ]);
  }

  public function saveOptimizeInfoToDbAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;
    $route = $request->post->get('route');

    if (isset($route['data']['serviceCode']) && count($route['data']) > 1) {
      $serviceCode = $route['data']['serviceCode'];
      unset($route['data']['serviceCode']);
      $isRestore = $route['data']['isRestore'];
      unset($route['data']['isRestore']);
      $isOptimizePreview = $route['data']['optimizePreview'];
      unset($route['data']['optimizePreview']);

      $attachmentSimpleModel = new GridGallery_Galleries_Attachment();
      $resourcesModel = $this->getModel('resources');
      $photoModel = $this->getModel('photos');
      $imageOptimizeModel = $this->getModel('imageOptimize');
      $optimizationModel = $this->getModel('optimization');
      $gallerySettings = $this->getModel('settings');

      foreach ($route['data'] as $galleryId => $gallerInfo) {
        $galleryId = (int) $galleryId;
        $photoOptCount = 0;
        $newModelSize = $optimizationModel->calcGalleryCurrentSize($galleryId, $attachmentSimpleModel, $resourcesModel, $photoModel, $gallerySettings, $isOptimizePreview, $photoOptCount);

        $res = $imageOptimizeModel->insertUpdate([
          'gallery_id' => $galleryId,
          'can_restore' => (int) $isRestore,
          'last_optimize_date' => date('Y.m.d'),
          'service_code' => $serviceCode,
          'size' => (int) $gallerInfo['size'],
          'optimized_size' => $newModelSize,
          'photo_count' => $photoOptCount,
        ]);
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
    ]);
  }

  public function saveCdnInfoToDbAction(RscSgg_Http_Request $request)
  {
    $route = $request->post->get('route');
    $addedGall = [];

    if (isset($route['data']) && isset($route['data']['gallery-obj']) && count($route['data']['gallery-obj'])) {
      $serviceCode = isset($route['data']['serviceCode']) ? $route['data']['serviceCode'] : null;

      foreach ($route['data']['gallery-obj'] as $galleryId => $gallInfo) {
        if (isset($gallInfo['size'])) {
          $cdnModel = $this->getModel('cdn');
          $oneRecord = [
            'gallery_id' => $galleryId,
            'last_transfer_date' => date('Y.m.d'),
            'service_code' => $serviceCode,
            'size' => $gallInfo['size'],
          ];
          if ($cdnModel->save($oneRecord)) {
            $oneRecord['last_transfer_date'] = date('d.m.Y');
            $addedGall[] = $oneRecord;
          }
        }
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'galleries' => $addedGall,
    ]);
  }

  public function rollbackRestoredImgAction(RscSgg_Http_Request $request)
  {
    $message = $this->translate('Error occurred');
    $isSuccess = false;
    $data = isset($request->post['route']['data']) ? $request->post['route']['data'] : null;
    $photos = [];

    if ($data && $data['gallery_id']) {
      $galleryId = intval($data['gallery_id']);
      $optimizeModel = $this->getModel('optimization');
      $resources = $this->getModel('resources');
      $photoModel = $this->getModel('photos');
      $imgOptimize = $this->getModel('imageOptimize');
      $currGallerySettings = $this->getModel('settings')->get($galleryId);
      $optimizeModel->restorePreviousFiles($galleryId, $resources, $photoModel, $currGallerySettings, $imgOptimize);
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => $isSuccess,
      'message' => $message,
    ]);
  }

  /**
   * How many images one "Optimize now" / "Transfer now" request handles before
   * handing control back to the browser. Keeps every request well inside
   * max_execution_time on shared hosting, and gives the progress bar something
   * to move on.
   */
  const GALLERY_BATCH_SIZE = 5;

  private function galleryHasActiveEcommerceRestrictions($galleryId)
  {
    $ecommerceModule = $this->getEnvironment()->getModule('ecommerce');

    return $ecommerceModule
      && method_exists($ecommerceModule, 'galleryHasActiveRestriction')
      && $ecommerceModule->galleryHasActiveRestriction((int) $galleryId);
  }

  /**
   * Runs image optimization for ONE gallery, a batch at a time.
   *
   * Unlike the old whole-site page, credentials are never sent to the browser:
   * the TinyPNG key and the chosen engine are read here from the global
   * settings and the gallery's own settings blob. The client only says which
   * gallery and how far along it is.
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function runGalleryOptimizeAction(RscSgg_Http_Request $request)
  {
    if (!$this->isPro()) {
      return $this->galleryRunResponse(false, $this->translate('Image optimization is a PRO feature.'));
    }

    $galleryId = (int) $request->post->get('gallery_id');
    $offset = max(0, (int) $request->post->get('offset', 0));

    if (!$galleryId) {
      return $this->galleryRunResponse(false, $this->translate('Unknown gallery.'));
    }

    if ($this->galleryHasActiveEcommerceRestrictions($galleryId)) {
      return $this->galleryRunResponse(false, $this->translate('Image optimization is disabled while this gallery uses E-commerce restrictions.'));
    }

    $options = $this->getGalleryOptimizationOptions($galleryId);
    if (empty($options['enabled'])) {
      return $this->galleryRunResponse(false, $this->translate('Image optimization is disabled for this gallery.'));
    }

    $photos = $this->collectGalleryPhotos($galleryId);
    $total = count($photos);

    if ($total === 0) {
      return $this->galleryRunResponse(false, $this->translate('This gallery has no images yet.'));
    }

    $engine = $options['engine'] === GridGallery_Optimization_Model_Optimization::ENGINE_SERVER
      ? GridGallery_Optimization_Model_Optimization::ENGINE_SERVER
      : GridGallery_Optimization_Model_Optimization::ENGINE_TINYPNG;

    $tinify = null;
    $serverEngine = null;

    if ($engine === GridGallery_Optimization_Model_Optimization::ENGINE_TINYPNG) {
      $serviceSettings = $this->getModel('optimization')->getServiceSettings();
      $authKey = isset($serviceSettings['setting']['tinypng']['auth_key']) ? $serviceSettings['setting']['tinypng']['auth_key'] : '';

      if (empty($authKey)) {
        return $this->galleryRunResponse(false, $this->translate('Add a TinyPNG API key in Advanced Settings first.'));
      }

      $tinify = $this->makeTinifyService();
      if (!$tinify->setConfiguration(['auth_key' => $authKey])) {
        return $this->galleryRunResponse(false, $this->translate('Error! Incorrect auth params!'));
      }
    } else {
      $serverEngine = new GridGallery_Optimization_Model_ServerOptimize($this->getPreferredImageEditor());
      if (!$serverEngine->isAvailable()) {
        return $this->galleryRunResponse(false, $this->translate('This server has neither GD nor Imagick available.'));
      }
    }

    $state = $this->readRunState($galleryId, 'optimize', $offset);
    $attachmentModel = new GridGallery_Galleries_Attachment();
    $batch = array_slice($photos, $offset, self::GALLERY_BATCH_SIZE);
    $lastError = null;

    foreach ($batch as $photo) {
      $path = $attachmentModel->replaceUrlToFilePath($photo['img_url']);
      if (!$path || !is_file($path)) {
        continue;
      }

      clearstatcache(true, $path);
      $before = (int) filesize($path);

      if ($engine === GridGallery_Optimization_Model_Optimization::ENGINE_SERVER) {
        $result = $serverEngine->optimizeFile($path, $options);
        if (empty($result['ok'])) {
          $lastError = $result['error'];
          continue;
        }
        $after = $result['after'];
      } else {
        if (!$this->backupBeforeOptimize($path, $options)) {
          $lastError = $this->translate('Could not create a backup of the original file.');
          continue;
        }

        try {
          $real = realpath($path);
          $tinify->optimizeImage(['fileSrc' => $real, 'fileDest' => $real]);
        } catch (Exception $e) {
          // A service failure is fatal for the whole run (quota, bad key,
          // no network) - stop rather than hammering the API image by image.
          $this->clearRunState($galleryId, 'optimize');
          return $this->galleryRunResponse(false, $e->getMessage());
        }

        clearstatcache(true, $path);
        $after = (int) filesize($path);
      }

      $state['size'] += $before;
      $state['optimized_size'] += $after;
      $state['photo_count']++;
    }

    $processed = min($total, $offset + count($batch));
    $finished = $processed >= $total;

    if ($finished) {
      $this->getModel('imageOptimize')->insertUpdate([
        'gallery_id' => $galleryId,
        'photo_count' => $state['photo_count'],
        'can_restore' => empty($options['replace_original']) ? 1 : 0,
        'last_optimize_date' => date('Y-m-d'),
        'service_code' => $engine,
        'size' => $state['size'],
        'optimized_size' => $state['optimized_size'],
      ]);
      $this->clearRunState($galleryId, 'optimize');
      $this->getModule('galleries')->cleanCache($galleryId, false);
    } else {
      $this->writeRunState($galleryId, 'optimize', $state);
    }

    return $this->galleryRunResponse(true, $lastError ? $lastError : $this->translate('Optimizing...'), [
      'processed' => $processed,
      'total' => $total,
      'finished' => $finished,
      'saved' => GridGallery_Optimization_Model_Optimization::getSizeInMb(max(0, $state['size'] - $state['optimized_size'])),
      'percent' => $state['size'] > 0 ? GridGallery_Optimization_Model_Optimization::calcOptimizePercent($state['size'], $state['optimized_size']) : 0,
    ]);
  }

  /**
   * Pushes ONE gallery to the CDN, a batch at a time. Credentials are read and
   * decrypted server-side.
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function runGalleryCdnAction(RscSgg_Http_Request $request)
  {
    if (!$this->isPro()) {
      return $this->galleryRunResponse(false, $this->translate('CDN transfer is a PRO feature.'));
    }

    $galleryId = (int) $request->post->get('gallery_id');
    $offset = max(0, (int) $request->post->get('offset', 0));

    if (!$galleryId) {
      return $this->galleryRunResponse(false, $this->translate('Unknown gallery.'));
    }

    if ($this->galleryHasActiveEcommerceRestrictions($galleryId)) {
      return $this->galleryRunResponse(false, $this->translate('CDN transfer is disabled while this gallery uses E-commerce restrictions.'));
    }

    $cdnModel = $this->getModel('cdn');
    if ($cdnModel->checkRequirements()) {
      return $this->galleryRunResponse(false, $this->translate('CDN storage is not available on this install.'));
    }

    $settings = $cdnModel->getServiceSettings();
    $current = isset($settings['current']) ? $settings['current'] : 'keycdn';
    $params = isset($settings['setting'][$current]) ? $settings['setting'][$current] : [];

    if (empty($params['u_name']) || empty($params['u_pass']) || empty($params['zone_name'])) {
      return $this->galleryRunResponse(false, $this->translate('Add your CDN credentials in Advanced Settings first.'));
    }

    $password = $this->getModel('encrypt')->decrypt($params['u_pass']);
    if (false === $password) {
      return $this->galleryRunResponse(false, $this->translate('Error! Incorrect service params!'));
    }

    $photos = $this->collectGalleryPhotos($galleryId);
    $total = count($photos);

    if ($total === 0) {
      return $this->galleryRunResponse(false, $this->translate('This gallery has no images yet.'));
    }

    $ftpModel = new GridGallery_Optimization_Model_Ftp([
      'host' => 'ftp.keycdn.com',
      'port' => null,
      'ftpUsername' => $params['u_name'],
      'ftpPassword' => $password,
      'folderName' => isset($params['base_ftp_path']) ? $params['base_ftp_path'] : null,
    ]);

    $state = $this->readRunState($galleryId, 'cdn', $offset);
    $attachmentModel = new GridGallery_Galleries_Attachment();
    $batch = array_slice($photos, $offset, self::GALLERY_BATCH_SIZE);

    foreach ($batch as $photo) {
      $path = $attachmentModel->replaceUrlToFilePath($photo['img_url']);
      if ($path && is_file($path)) {
        $state['size'] += (int) filesize($path);
      }

      try {
        $this->transferToCdnOnePhotoObj($ftpModel, $attachmentModel, $photo, false);
      } catch (Exception $e) {
        $this->clearRunState($galleryId, 'cdn');
        return $this->galleryRunResponse(false, $e->getMessage());
      }
    }

    $processed = min($total, $offset + count($batch));
    $finished = $processed >= $total;

    if ($finished) {
      $cdnModel->save([
        'gallery_id' => $galleryId,
        'last_transfer_date' => date('Y-m-d'),
        'service_code' => $current,
        'size' => $state['size'],
      ]);
      $this->clearRunState($galleryId, 'cdn');
    } else {
      $this->writeRunState($galleryId, 'cdn', $state);
    }

    return $this->galleryRunResponse(true, $this->translate('Transferring...'), [
      'processed' => $processed,
      'total' => $total,
      'finished' => $finished,
      'saved' => GridGallery_Optimization_Model_Optimization::getSizeInMb($state['size']),
      'percent' => 0,
    ]);
  }

  /**
   * Every image URL in a gallery, in a shape transferToCdnOnePhotoObj() also
   * accepts.
   *
   * @param int $galleryId
   * @return array
   */
  private function collectGalleryPhotos($galleryId)
  {
    $out = [];

    $resources = $this->getModel('resources')->getByGalleryId($galleryId);
    if (empty($resources)) {
      return $out;
    }

    $photos = $this->getModel('photos')->getPhotos($resources);
    if (empty($photos)) {
      return $out;
    }

    // The frontend does not render the original upload - it renders a cropped
    // (and possibly watermarked) derivative built by Attachment::getAttachment()
    // for this gallery's configured photo size. Optimizing only the original
    // would shrink a file no visitor ever downloads, and would put the WebP /
    // AVIF siblings next to the wrong file, so both are collected here.
    $gallerySettings = $this->getModel('settings')->get($galleryId);
    $sizeParams = null;

    if ($gallerySettings && isset($gallerySettings->data)) {
      $sizeParams = GridGallery_Galleries_Attachment::prepareWmImgParamsFromGallerySett($gallerySettings);
    }

    $attachmentModel = new GridGallery_Galleries_Attachment();
    $seen = [];

    foreach ($photos as $photo) {
      $attachment = is_object($photo) && isset($photo->attachment) ? (array) $photo->attachment : [];
      if (empty($attachment['url'])) {
        continue;
      }

      if (!isset($seen[$attachment['url']])) {
        $seen[$attachment['url']] = true;
        $out[] = ['img_url' => $attachment['url']];
      }

      if (!$sizeParams || empty($photo->attachment_id)) {
        continue;
      }

      $derivative = $attachmentModel->getAttachment(
        $photo->attachment_id,
        isset($sizeParams['photo_width']) ? $sizeParams['photo_width'] : null,
        isset($sizeParams['photo_height']) ? $sizeParams['photo_height'] : null,
        isset($attachment['cropPosition']) ? $attachment['cropPosition'] : null,
        isset($sizeParams['crop_quality']) ? $sizeParams['crop_quality'] : null
      );

      if (!empty($derivative) && !isset($seen[$derivative])) {
        $seen[$derivative] = true;
        $out[] = ['img_url' => $derivative];
      }
    }

    return $out;
  }

  /**
   * The gallery's own optimization block, with the same defaults the settings
   * form renders, so a gallery saved before these fields existed still runs
   * with sane values instead of zeroes.
   *
   * @param int $galleryId
   * @return array
   */
  private function getGalleryOptimizationOptions($galleryId)
  {
    $stored = $this->getModel('settings')->get($galleryId);
    $data = $stored && isset($stored->data) && is_array($stored->data) ? $stored->data : [];
    $opt = isset($data['optimization']) && is_array($data['optimization']) ? $data['optimization'] : [];
    $frontendFormat = isset($opt['frontend_format']) ? (string) $opt['frontend_format'] : '';
    if (!in_array($frontendFormat, ['original', 'webp', 'avif'], true)) {
      if (isset($opt['serve_avif']) && $opt['serve_avif'] === '1') {
        $frontendFormat = 'avif';
      } elseif (isset($opt['serve_webp']) && $opt['serve_webp'] === '1') {
        $frontendFormat = 'webp';
      } else {
        $frontendFormat = 'original';
      }
    }

    return [
      'enabled' => isset($opt['enabled']) && $opt['enabled'] === 'true',
      'engine' => isset($opt['engine']) ? $opt['engine'] : GridGallery_Optimization_Model_Optimization::ENGINE_TINYPNG,
      'max_width' => isset($opt['max_width']) ? (int) $opt['max_width'] : 0,
      'max_height' => isset($opt['max_height']) ? (int) $opt['max_height'] : 0,
      'quality' => isset($opt['quality']) ? (int) $opt['quality'] : 100,
      'replace_original' => isset($opt['replace_original']) && $opt['replace_original'] === '1',
      'convert_webp' => $frontendFormat === 'webp' || (isset($opt['convert_webp']) && $opt['convert_webp'] === '1'),
      'convert_avif' => $frontendFormat === 'avif' || (isset($opt['convert_avif']) && $opt['convert_avif'] === '1'),
      'frontend_format' => $frontendFormat,
    ];
  }

  /**
   * @return string|null 'auto' | 'gd' | 'imagic'
   */
  private function getPreferredImageEditor()
  {
    $settings = get_option($this->getConfig()->get('db_prefix') . 'settings');

    return is_array($settings) && isset($settings['image_editor']) ? $settings['image_editor'] : null;
  }

  /**
   * @return Tinify_Tinify
   */
  private function makeTinifyService()
  {
    require_once 'lib/ImageOptimizeInterface.php';
    require_once 'lib/Tinify/Exception.php';
    require_once 'lib/Tinify/ResultMeta.php';
    require_once 'lib/Tinify/Result.php';
    require_once 'lib/Tinify/Source.php';
    require_once 'lib/Tinify/Client.php';
    require_once 'lib/Tinify/Tinify.php';

    return new Tinify_Tinify();
  }

  /**
   * TinyPNG rewrites the file in place, so take the same io-backup copy the
   * server engine takes - one Restore path covers both engines.
   *
   * @param string $path
   * @param array $options
   * @return bool
   */
  private function backupBeforeOptimize($path, array $options)
  {
    if (!empty($options['replace_original'])) {
      return true;
    }

    $dir = dirname($path) . DIRECTORY_SEPARATOR . GridGallery_Optimization_Model_Optimization::$restoreSubFolder;

    if (!file_exists($dir) && !wp_mkdir_p($dir)) {
      return false;
    }

    $target = $dir . DIRECTORY_SEPARATOR . basename($path);

    if (file_exists($target)) {
      return true;
    }

    return (bool) @copy($path, $target);
  }

  /**
   * Running totals for a multi-request run. Kept server-side in a transient so
   * a browser that closes mid-run cannot leave half-counted stats in the
   * gallery row - the row is only written once the last batch lands.
   *
   * @param int $galleryId
   * @param string $kind
   * @param int $offset
   * @return array
   */
  private function readRunState($galleryId, $kind, $offset)
  {
    $empty = ['size' => 0, 'optimized_size' => 0, 'photo_count' => 0];

    if ($offset === 0) {
      return $empty;
    }

    $state = get_transient($this->runStateKey($galleryId, $kind));

    return is_array($state) ? array_merge($empty, $state) : $empty;
  }

  private function writeRunState($galleryId, $kind, array $state)
  {
    set_transient($this->runStateKey($galleryId, $kind), $state, HOUR_IN_SECONDS);
  }

  private function clearRunState($galleryId, $kind)
  {
    delete_transient($this->runStateKey($galleryId, $kind));
  }

  private function runStateKey($galleryId, $kind)
  {
    return 'sgg_run_' . $kind . '_' . (int) $galleryId;
  }

  /**
   * @param bool $success
   * @param string $message
   * @param array $extra
   * @return RscSgg_Http_Response
   */
  private function galleryRunResponse($success, $message, array $extra = [])
  {
    return $this->response(
      RscSgg_Http_Response::AJAX,
      array_merge(
        [
          'success' => $success,
          'message' => $message,
        ],
        $extra
      )
    );
  }

  private function transferToCdnOnePhotoObj($ftpModel, $attachModel, $onePhoto, $needToDelete)
  {
    if (isset($_SERVER['HTTP_X_REQUEST_SCHEME'])) {
      $currServerName = sanitize_text_field($_SERVER['HTTP_X_REQUEST_SCHEME']) . '://' . sanitize_text_field($_SERVER['HTTP_HOST']);
    } else {
      $currServerName = sanitize_text_field($_SERVER['REQUEST_SCHEME']) . '://' . sanitize_text_field($_SERVER['HTTP_HOST']);
    }
    $ftpMainImgUrl = preg_replace('`' . $currServerName . '`', '', $onePhoto['img_url']);
    $mainPath = realpath($attachModel->replaceUrlToFilePath($onePhoto['img_url']));
    $ftpModel->uploadFileOnServer($ftpMainImgUrl, $mainPath);

    if (isset($onePhoto['preview_url'])) {
      $ftpPreviewImgUrl = preg_replace('`' . $currServerName . '`', '', $onePhoto['preview_url']);
      $previewPath = realpath($attachModel->replaceUrlToFilePath($onePhoto['preview_url']));
      $ftpModel->uploadFileOnServer($ftpPreviewImgUrl, $previewPath);
    }
  }
}
