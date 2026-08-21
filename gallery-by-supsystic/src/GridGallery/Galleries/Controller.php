<?php

/**
 * Class GridGallery_Galleries_Controller
 * The controller of the Galleries module.
 *
 * @package GridGallery\Galleries
 * @author  Artur Kovalevsky
 */
class GridGallery_Galleries_Controller extends GridGallery_Core_BaseController
{
  const DEFAULT_PHOTOS_PER_PAGE = 100;

  public function requireNonces()
  {
    return [
      'createAction',
      'sideloadSaveAction',
      'attachAction',
      'chooseAction',
      'renameAction',
      'deleteAction',
      'saveSettingsAction',
      'deleteGroupAction',
      'deleteResourceAction',
      'addImagesAction',
      'saveCatsPresetAction',
      'savePagesPresetAction',
      'savePresetAction',
      'removePresetAction',
      'applyPresetAction',
      'sendUsageStat',
      'ajaxResizeImageAction',
      'saveSortByAction',
      'saveCategoryOrderAction',
      'saveUiStateAction',
      'importSettingsAction',
      'cloneAction',
      'createDefaultGallerySettingsAction',
      'removeDefaultGallerySettingsAction',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getModelAliases()
  {
    return [
      'socialSharing' => 'GridGallery_Galleries_Model_SocialSharing',
      'galleries' => 'GridGallery_Galleries_Model_Galleries',
      'resources' => 'GridGallery_Galleries_Model_Resources',
      'settings' => 'GridGallery_Galleries_Model_Settings',
      'preset' => 'GridGallery_Galleries_Model_Preset',
      'photos' => 'GridGallery_Photos_Model_Photos',
      'folders' => 'GridGallery_Photos_Model_Folders',
      'position' => 'GridGallery_Photos_Model_Position',
      'cdn' => 'GridGallery_Optimization_Model_Cdn',
      'imageOptimize' => 'GridGallery_Optimization_Model_ImageOptimize',
      'optimization' => 'GridGallery_Optimization_Model_Optimization',
      'pagination' => 'GridGallery_Galleries_Model_Pagination',
    ];
  }

  /**
   * Index Action
   * Shows the list of the galleries
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function indexAction(RscSgg_Http_Request $request)
  {
    $stats = $this->getEnvironment()->getModule('stats');
    $stats->save('Galleries.tab');

    /*if ('grid-gallery-new' === $request->query->get('page')) {
            $redirectUrl = $this->generateUrl('galleries') . '#add';

            return $this->redirect($redirectUrl);
        }*/

    $page = 1;
    $perPage = 20;
    $sort = 'id';
    $dir = 'desc';

    $result = $this->getModel('galleries')->getPaginatedList($page, $perPage, $sort, $dir);
    $galleries = $this->enrichGalleryRows($result['rows']);

    $twig = $this->getEnvironment()->getTwig();
    $twig->addFunction(new Twig_SupTwgSgg_SimpleFunction('get_image_src', 'wp_get_attachment_image_src'));

    return $this->response('@galleries/index.twig', [
      'galleries' => $galleries,
      'recordsTotal' => $result['total'],
      'page' => $page,
      'perPage' => $perPage,
      'sort' => $sort,
      'dir' => $dir,
    ]);
  }

  /**
   * Server-side-processing data endpoint for the galleries list table:
   * returns a rendered rows partial + pagination metadata as JSON.
   */
  public function galleriesDataAction(RscSgg_Http_Request $request)
  {
    $page = (int) $request->post->get('page', 1);
    $perPage = (int) $request->post->get('perPage', 20);
    $sort = (string) $request->post->get('sort', 'id');
    $dir = (string) $request->post->get('dir', 'desc');
    $search = (string) $request->post->get('search', '');

    $result = $this->getModel('galleries')->getPaginatedList($page, $perPage, $sort, $dir, $search);
    $galleries = $this->enrichGalleryRows($result['rows']);

    $twig = $this->getEnvironment()->getTwig();
    $twig->addFunction(new Twig_SupTwgSgg_SimpleFunction('get_image_src', 'wp_get_attachment_image_src'));
    $html = $twig->render('@galleries/includes/list_rows.twig', ['galleries' => $galleries]);

    return $this->response(RscSgg_Http_Response::AJAX, [
      'html' => $html,
      'recordsTotal' => $result['total'],
      'page' => $page,
      'perPage' => $perPage,
      'sort' => $sort,
      'dir' => $dir,
      'search' => $search,
    ]);
  }

  /**
   * Unserializes each gallery row's settings blob and attaches display-ready
   * fields (gallery type label, formatted created/modified dates) used by
   * both the first-paint index view and the AJAX SSP endpoint.
   */
  private function enrichGalleryRows($galleries)
  {
    $typeLabels = [
      '0' => 'Fixed',
      '1' => 'Vertical',
      '2' => 'Horizontal',
      '3' => 'Fixed Columns',
      '4' => 'Mosaic',
    ];
    $dateFormat = get_option('date_format') . ' ' . get_option('time_format');

    foreach ($galleries as $gallery) {
      $gallery->settings = unserialize($gallery->settings, ['allowed_classes' => false]);

      $grid = isset($gallery->settings['area']['grid']) ? (string) $gallery->settings['area']['grid'] : '0';
      $gallery->typeLabel = isset($typeLabels[$grid]) ? $typeLabels[$grid] : $typeLabels['0'];

      $gallery->createdFormatted = $gallery->created ? mysql2date($dateFormat, $gallery->created) : '—';
      $gallery->modifiedFormatted = $gallery->modified ? mysql2date($dateFormat, $gallery->modified) : '—';
    }

    $this->getModel('settings')->PostThumb($galleries);

    return $galleries;
  }

  /**
   * Preview Action
   */
  public function previewAction(RscSgg_Http_Request $request)
  {
    $this->saveEvent('galleries.preview');
    $galleryId = $request->query->get('gallery_id');
    $shortcode = $this->getEnvironment()->getConfig()->get('shortcode_name', 'grid-gallery');

    try {
      $preview = new GridGallery_Galleries_Model_Preview();
      $postId = $preview->setPostContent(sprintf('[%s id="%s"]', $shortcode, $galleryId));
    } catch (Exception $e) {
      return $this->response('error.twig', [
        'message' => $e->getMessage(),
      ]);
    }

    return $this->response('@galleries/preview.twig', [
      'base_url' => get_bloginfo('wpurl'),
      'post_id' => $postId,
      'gallery_id' => $galleryId,
    ]);
  }

  /**
   * Renders a single photo card (caption/icons/hover effect only) for the
   * small live-updating thumbnail at the top of the Settings page
   * (.gg-detailed-info-preview), reflecting the settings form's CURRENT
   * (possibly unsaved) field values rather than what's stored in the DB -
   * settings.js posts the whole #form-settings serialization here on every
   * relevant field change, the same way saveSettingsAction reads it, just
   * without ever calling settings->save().
   */
  public function previewCaptionAction(RscSgg_Http_Request $request)
  {
    // Reached through SupsysticGallery.Ajax.Post (admin-ajax.php), unlike
    // previewAction above (a direct admin page link) - everything including
    // gallery_id arrives in the POST body here, not the query string.
    $galleryId = $request->post->get('gallery_id');
    $gallery = $this->getModel('galleries')->getById((int) $galleryId);

    if (!$gallery || empty($gallery->photos)) {
      return $this->response(RscSgg_Http_Response::AJAX, [
        'html' => $this->getEnvironment()->getTwig()->render('@galleries/shortcode/preview_caption.twig', [
          'gallery' => $gallery,
          'settings' => [],
        ]),
      ]);
    }

    $data = $request->post->all();

    // Same decode saveSettingsAction does for these two fields - the form
    // posts them JSON-encoded either way, this just never reaches ->save().
    if (isset($data['attributes']['order'])) {
      $data['attributes']['order'] = json_decode($data['attributes']['order']);
      $data['attributes']['enable'] = json_decode($data['attributes']['enable']);
      unset($data['attributes']['rename']);
    }
    if (isset($data['ui']['collapsedSections'])) {
      $decoded = json_decode($data['ui']['collapsedSections'], true);
      $data['ui']['collapsedSections'] = is_array($decoded) ? $decoded : [];
    }

    // The real gallery's own configured photo size would make this preview
    // as big as the actual thumbnails - .gg-detailed-info-preview is a
    // fixed 165x165 box, so force a size that fits it regardless of what's
    // actually configured/being edited.
    // This standalone snippet never runs the real gallery's Wookmark/lazy-load
    // JS (nothing else on the page reveals a lazy placeholder or swaps it for
    // the real image), so force lazy load off here regardless of the actual
    // gallery setting - otherwise the preview would be stuck showing loading.gif.
    $data['lazyload']['enabled'] = '0';

    $data['area']['photo_width'] = 155;
    $data['area']['photo_width_unit'] = 0;
    $data['area']['photo_height'] = 155;
    $data['area']['photo_height_unit'] = 0;

    // Use a copy of the first photo so a blank Title/Description (the common
    // case for a freshly-uploaded photo) still demonstrates the configured
    // caption styling instead of rendering empty - never touches the DB, and
    // any real caption text the photo already has takes priority as-is.
    // Note: helpers.twig's legacy (non-Caption-Builder) icons-mode caption
    // panel is gated on "caption is not empty" (not "title"), and photo.title
    // is only ever used as ITS fallback for when caption is non-empty but
    // came from EXIF - so caption, not title, is the field that actually
    // needs a placeholder for that panel to render at all.
    $previewPhoto = clone $gallery->photos[0];
    $previewPhoto->attachment = (array) $previewPhoto->attachment;
    if (empty($previewPhoto->attachment['caption'])) {
      $previewPhoto->attachment['caption'] = $this->translate('Sample Photo Title');
    }
    if (empty($previewPhoto->attachment['captionDescription'])) {
      $previewPhoto->attachment['captionDescription'] = $this->translate('Sample photo description text goes here.');
    }
    $previewGallery = clone $gallery;
    $previewGallery->photos = [$previewPhoto];

    // Per-image Social Sharing icons are DOM-injected by frontend.js on the
    // real gallery (initImageSocialSharing clones a hidden per-icon template
    // into every figure) rather than rendered per-photo by Twig - there's no
    // live Gallery JS instance driving this static preview box, so build the
    // same icon list server-side instead and let preview_caption.twig render
    // it directly (see gallery.twig's own use of getSocialShareList/getSocialIcons).
    $socialIcons = false;
    if (
      method_exists($this->getModel('galleries'), 'getSocialShareList') &&
      !empty($data['socialSharing']['enabled']) &&
      !empty($data['socialSharing']['imageSharing']['enabled']) &&
      !empty($data['socialSharing']['gallerySharing']['socialIcons'])
    ) {
      $socialIcons = $this->getModel('galleries')->getSocialShareList($data['socialSharing']['gallerySharing']['socialIcons']);
      // The real per-image icon strip is sized for a full-width gallery thumbnail;
      // this preview box is a fixed 155x155, so however many platforms are
      // actually configured, only show the first 3 here or they overlap/overflow.
      $socialIcons = array_slice($socialIcons, 0, 3);
    }

    // Watermarking is real GD image processing keyed by a hash of the source
    // file + every watermark setting (GridGalleryPro_Galleries_Attachment) -
    // there's no CSS-only stand-in for it anywhere in this codebase. Force
    // toCreateWatermark so a not-yet-saved combination of settings actually
    // gets generated instead of silently falling back to the plain image
    // (that flag is normally only set by the explicit "Update watermark"
    // button). preview_caption.twig calls set_attachment_settings() with
    // this, mirroring settings.twig's own {% block preview %}.
    if (isset($data['watermark'])) {
      $data['watermark']['galleryId'] = $gallery->id;
      $data['watermark']['toCreateWatermark'] = true;
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'html' => $this->getEnvironment()->getTwig()->render('@galleries/shortcode/preview_caption.twig', [
        'gallery' => $previewGallery,
        'settings' => $data,
        'socialIcons' => $socialIcons,
      ]),
    ]);
  }

  protected function getViewActionParams($request)
  {
    if (!($galleryId = $request->query->get('gallery_id'))) {
      $this->redirect($this->generateUrl('galleries', 'index'));
    }

    if (!($gallery = $this->getModel('galleries')->getById((int) $galleryId))) {
      $this->redirect($this->generateUrl('galleries', 'index'));
    }

    $settings = $this->getModel('settings')->get($galleryId);
    if (!is_object($settings) || null === $settings->data) {
      $config = $this->getEnvironment()->getConfig();
      $config->load('@galleries/settings.php');

      $settings = new stdClass();

      $settings->id = null;
      $settings->data = unserialize($config->get('gallery_settings'), ['allowed_classes' => false]);

      $environment = $this->getPluginEnvironment();
      if ($environment->isPro() && $environment->isModule('license') && $environment->getModule('license')->isActive()) {
      } else {
        $settings->data['icons']['enabled'] = false;
        $settings->data['icons']['effect'] = 'none';
      }
    }

    $perPage = self::DEFAULT_PHOTOS_PER_PAGE;
    $result = $this->getGalleryPhotosPage($gallery, $settings->data, 1, $perPage);
    $gallery->photos = $result['photos'];

    $galleries = $this->getModel('galleries')->getList();
    return [
      'gallery' => $gallery,
      'recordsTotal' => $result['total'],
      'page' => 1,
      'perPage' => $perPage,
      'sort' => isset($settings->data['sort']['sortby']) ? $settings->data['sort']['sortby'] : 'position',
      'dir' => isset($settings->data['sort']['sortto']) ? $settings->data['sort']['sortto'] : 'asc',
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'settings' => $settings->data,
      'galleries' => $galleries,
    ];
  }

  /**
   * Fetches a gallery's photos with position + sort applied, optionally
   * sliced to one page ($page is 1-based). Shared by getViewActionParams
   * (first paint) and photosDataAction (AJAX re-fetch) so both stay
   * consistent with a single implementation of the existing sort/paginate
   * logic that used to live only inline in getViewActionParams.
   *
   * @return array{photos: array, total: int}
   */
  private function getGalleryPhotosPage($gallery, $settingsData, $page = null, $perPage = null)
  {
    if (!is_object($gallery) || !property_exists($gallery, 'photos') || !is_array($gallery->photos)) {
      return ['photos' => [], 'total' => 0];
    }

    $position = $this->getModel('position');
    $photos = $gallery->photos;

    foreach ($photos as $index => $row) {
      $photos[$index] = $position->setPosition($row, 'gallery', $gallery->id);
    }

    if (isset($settingsData['sort'])) {
      $photos = $position->sort($photos, $settingsData['sort']);
    } else {
      $photos = $position->sort($photos);
    }

    $total = count($photos);

    if ($total && $page !== null && $perPage !== null) {
      $currentPage = max(0, (int) $page - 1);
      $imgPerPage = $perPage === 'all' ? null : (int) $perPage;
      $fromImg = $currentPage * $imgPerPage;
      $photos = array_slice($photos, $fromImg, $imgPerPage, true);
      $this->getEnvironment()
        ->getDispatcher()
        ->dispatch('before_gallery_photos_edit', [$photos]);
    }

    return ['photos' => $photos, 'total' => $total];
  }

  /**
   * View Action
   * Renders single gallery page
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function viewAction(RscSgg_Http_Request $request)
  {
    $params = $this->getViewActionParams($request);

    return $this->response('@galleries/view.twig', $params);
  }

  /**
   * Server-side-processing data endpoint for the Images List tile grid:
   * returns a rendered tiles partial + pagination metadata as JSON. Mirrors
   * galleriesDataAction's shape.
   */
  public function photosDataAction(RscSgg_Http_Request $request)
  {
    $galleryId = (int) $request->post->get('gallery_id');
    $page = (int) $request->post->get('page', 1);
    $perPage = (int) $request->post->get('perPage', self::DEFAULT_PHOTOS_PER_PAGE);
    $sort = $request->post->get('sort');
    $dir = $request->post->get('dir');

    if (!($gallery = $this->getModel('galleries')->getById($galleryId))) {
      return $this->response(RscSgg_Http_Response::AJAX, ['html' => '', 'recordsTotal' => 0]);
    }

    $settings = $this->getModel('settings')->get($galleryId);
    if (!is_object($settings) || null === $settings->data) {
      $config = $this->getEnvironment()->getConfig();
      $config->load('@galleries/settings.php');

      $settings = new stdClass();
      $settings->id = null;
      $settings->data = unserialize($config->get('gallery_settings'), ['allowed_classes' => false]);
    }

    if ($sort) {
      $settings->data = $this->saveSortChoice($galleryId, $sort, $dir === 'desc' ? 'desc' : 'asc');
    }

    $result = $this->getGalleryPhotosPage($gallery, $settings->data, $page, $perPage);

    $twig = $this->getEnvironment()->getTwig();
    $html = $twig->render('@ui/includes/tile_grid.twig', [
      'photos' => $result['photos'],
      'gallery' => $gallery,
      'settings' => $settings->data,
    ]);

    return $this->response(RscSgg_Http_Response::AJAX, [
      'html' => $html,
      'recordsTotal' => $result['total'],
      'page' => $page,
      'perPage' => $perPage,
      'sort' => isset($settings->data['sort']['sortby']) ? $settings->data['sort']['sortby'] : 'position',
      'dir' => isset($settings->data['sort']['sortto']) ? $settings->data['sort']['sortto'] : 'asc',
    ]);
  }

  /**
   * The dedicated Sort grid page has been folded into the Images List tile
   * grid (drag-and-drop reorder works there directly) - redirect old links.
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function sortAction(RscSgg_Http_Request $request)
  {
    return $this->redirect($this->generateUrl('galleries', 'view', ['gallery_id' => $request->query->get('gallery_id')]));
  }

  /**
   * List Action
   * Returns the AJAX response with galleries list
   *
   * @return RscSgg_Http_Response
   */
  public function listAction()
  {
    return $this->response('ajax', [
      'galleries' => $this->getModel('galleries')->getList(),
    ]);
  }

  /**
   * Create Action
   * Creates the new gallery from the POST request
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function createAction(RscSgg_Http_Request $request)
  {
    $galleries = $this->getModel('galleries');
    $language = $this->getEnvironment()->getLang();
    $logger = $this->getEnvironment()->getLogger();
    $config = $this->getEnvironment()->getConfig();

    $stats = $this->getEnvironment()->getModule('stats');
    $stats->save('galleries.create');

    // if(get_option('defaultgallerysettings')) {
    //   $defaultSettingGalleryId = get_option('defaultgallerysettings');
    // } else {
    $defaultSettingGalleryId = null;
    // }

    try {
      $galleries->createFromRequest($request, $language, $config);
      if ($defaultSettingGalleryId) {
        $createdGalleryId = $galleries->getInsertId();
        $settingsModel = $this->getModel('settings');
        $settings = $settingsModel->get($defaultSettingGalleryId);
        $defaultSettings = $settings->data;
        $defaultSettings['defaultsettings'] = 0;
        $settingsModel->save($createdGalleryId, $defaultSettings);
        $this->getModule('galleries')->cleanCache($createdGalleryId, true);
      }
    } catch (Exception $e) {
      if ($logger) {
        $logger->error('Create a new gallery failed: {exception}', ['exception' => $e]);
      }

      return $this->response('ajax', $this->getErrorResponseData($e->getMessage()));
    }

    return $this->response(
      'ajax',
      $this->getSuccessResponseData($this->translate('New gallery successfully created'), [
        'id' => $galleries->getInsertId(),
        'url' => $this->generateUrl('galleries', 'settings', [
          'gallery_id' => $galleries->getInsertId(),
        ]),
      ]),
    );
  }

  public function sideloadSaveAction(RscSgg_Http_Request $request)
  {
    $selectedImages = $request->post->get('urls');
    $photos = $this->getModel('photos');
    $attachID = [];

    foreach ($selectedImages as $image) {
      $id = $this->getModule('galleries')->media_sideload_image($image, 0);
      if ($photos->add($id)) {
        $attachID[] = $photos->getInsertId();
      }
    }
    return $this->response(RscSgg_Http_Response::AJAX, ['msh' => 'Loaded', 'ids' => $attachID]);
  }

  /**
   * Attach Action
   * Attaches resources to the specified gallery
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function attachAction(RscSgg_Http_Request $request)
  {
    $logger = $this->getEnvironment()->getLogger();
    $lang = $this->getEnvironment()->getLang();

    try {
      $gid = $this->getModel('resources')->attachFromRequest($request, $lang);
    } catch (GridGallery_Galleries_Exception_AttachException $e) {
      if ($logger) {
        $logger->error('Failed to attach resources to gallery: {exception}', [
          'exception' => $e,
        ]);
      }

      return $this->response('ajax', $this->getErrorResponseData($e->getMessage()));
    }

    $galleries = $this->getModel('galleries');
    $gallery = $galleries->getById($gid);

    //cleaning cache after attaching
    $this->getModule('galleries')->cleanCache($gid);

    /*return $this->response(
            'ajax',
            $this->getSuccessResponseData(
                sprintf(
                    $lang->translate(
                        'The resources are successfully attached to the <a href="%s">%s</a>'
                    ),
                    $this->generateUrl(
                        'galleries',
                        'view',
                        array('gallery_id' => $gid)
                    ),
                    $gallery->title
                )
            )
        );*/

    return $this->response('ajax', [
      'message' => $this->translate('The resources are successfully attached to the ' . $gallery->title),
      'galleryId' => (int) $gallery->id,
      'redirectUrl' => $this->getEnvironment()->generateUrl('galleries', 'view', ['gallery_id' => $gallery->id]),
    ]);
  }

  public function chooseAction(RscSgg_Http_Request $request)
  {
    $resourceId = $request->post->get('resources');
    $galleryId = $request->post->get('gallery_id');

    $settings = $this->getModel('settings')->get($galleryId);
    if ($resourceId[0]['id']) {
      $photo = $this->getModel('photos')->getById($resourceId[0]['id']);
    }

    $settings->data['previewImage'] = $photo->attachment_id;

    $this->getModel('settings')->save($galleryId, $settings->data);

    update_option('previewImageId', $photo->attachment_id);

    return $this->response(RscSgg_Http_Response::AJAX, ['url' => $this->generateUrl('galleries', 'settings', ['gallery_id' => $galleryId]), 'message' => 'Preview image successfully changed']);
  }

  //Uncomment to allow getting tooltips url
  /*public function getTooltipsUrlAction(RscSgg_Http_Request $request) {
        $url = $this->getEnvironment()->getConfig()->get('plugin_url');

        return $this->response(RscSgg_Http_Response::AJAX,
            array('url' => $url,
                'message' => 'Preview image successfully changed'
            )
        );
    }*/

  public function showPresetsAction(RscSgg_Http_Request $request)
  {
    return $this->response('@galleries/gallery_preset.twig', ['url' => $this->generateUrl('galleries')]);
  }

  /**
   * Rename Action
   * Renames the specified gallery
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function renameAction(RscSgg_Http_Request $request)
  {
    $lang = $this->getEnvironment()->getLang();
    $logger = $this->getEnvironment()->getLogger();

    $stats = $this->getEnvironment()->getModule('stats');
    $stats->save('galleries.rename');

    try {
      $this->getModel('galleries')->renameFromRequest($request, $lang);
    } catch (Exception $e) {
      if ($logger) {
        $logger->error('Invalid argument specified: {exception}', [
          'exception' => $e,
        ]);
      }

      return $this->response('ajax', $this->getErrorResponseData($e->getMessage()));
    }

    return $this->response('ajax', $this->getSuccessResponseData($lang->translate('Title successfully updated')));
  }

  /**
   * Delete Action
   * Deletes the gallery
   *
   * @param RscSgg_Http_Request $request An instance of the HTTP request
   * @return RscSgg_Http_Response
   */
  public function deleteAction(RscSgg_Http_Request $request)
  {
    $env = $this->getEnvironment();
    $lang = $env->getLang();
    $logger = $env->getLogger();
    $prefix = $env->getConfig()->get('hooks_prefix');

    $stats = $this->getEnvironment()->getModule('stats');
    $stats->save('galleries.delete');

    try {
      $this->getModel('galleries')->deleteFromRequest($request, $lang, $prefix);
    } catch (Exception $e) {
      if ($logger) {
        $logger->error('Failed to delete the gallery: {exception}', [
          'exception' => $e,
        ]);
      }

      return $this->response('ajax', $this->getErrorResponseData($e->getMessage()));
    }
    $membershipModel = false;
    // $membershipModel = $this->getModel('membership');
    // $membershipModel->removeRowByGalleryId((int)$request->query->get('gallery_id'));
    $cleanAllCache = false;
    $settings = $this->getModel('settings')->get($request->query->get('gallery_id'));
    if ($settings && property_exists($settings, 'data')) {
      $data = $settings->data;
      if (isset($data['plugins']['membership']['enable']) && $data['plugins']['membership']['enable'] == 1) {
        $cleanAllCache = true;
      }
    }

    $this->getModule('galleries')->cleanCache($request->query->get('gallery_id'), $cleanAllCache);
    return $this->redirect($this->generateUrl('galleries'));
  }

  /**
   * Delete Group Action
   * Deletes the gallery list
   *
   * @param RscSgg_Http_Request $request An instance of the HTTP request
   * @return RscSgg_Http_Response
   */
  public function deleteGroupAction(RscSgg_Http_Request $request)
  {
    $env = $this->getEnvironment();
    $logger = $env->getLogger();

    $ids = $request->post->get('gallery_ids');

    foreach ($ids as $id) {
      try {
        $this->getModel('galleries')->delete($id);
      } catch (Exception $e) {
        if ($logger) {
          $logger->error('Failed to delete the gallery: {exception}', [
            'exception' => $e,
          ]);
        }

        return $this->response('ajax', $this->getErrorResponseData($e->getMessage()));
      }
    }
  }

  /**
   * Deletes the resources from the specified gallery.
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function deleteResourceAction(RscSgg_Http_Request $request)
  {
    $resources = $this->getModel('resources');

    try {
      $resources->deleteFromRequest($request);
    } catch (Exception $e) {
      return $this->response('ajax', $this->getErrorResponseData($e->getMessage()));
    }

    $this->getModule('galleries')->cleanCache($request->post->get('gallery_id'));

    return $this->response('ajax', $this->getSuccessResponseData('Deleted successfully.'));
  }

  /**
   * Shows the page with photos to attach them to the gallery.
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function addImagesAction(RscSgg_Http_Request $request)
  {
    if (null === ($galleryId = $request->query->get('gallery_id'))) {
      // 404 - gallery not found
    }

    /** @var GridGallery_Galleries_Model_Galleries $galleries */
    $galleries = $this->getModel('galleries');
    $gallery = $galleries->getById($galleryId);

    return $this->response('@galleries/add_images.twig', [
      'gallery' => $gallery,
      'photos' => $this->getModel('photos')->getAllWithoutFolders(),
      'folders' => $this->getModel('folders')->getAll(),
      'viewType' => $request->query->get('view', 'block'),
    ]);
  }

  /**
   * Settings Action.
   * Manage gallery settings
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function settingsAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->query->get('gallery_id');

    $this->getEnvironment()->getConfig()->load('@galleries/tooltips.php');

    $tooltips = $this->getEnvironment()->getConfig()->get('tooltips');
    $icon = $this->getEnvironment()->getConfig()->get('tooltips_icon');

    $tooltips = array_map([$this, 'rewrite'], $tooltips);

    $this->getEnvironment()->getTwig()->addGlobal('tooltips', $tooltips);
    $this->getEnvironment()->getTwig()->addGlobal('tooltips_icon', $icon);

    $twig = $this->getEnvironment()->getTwig();
    $twig->addFunction(new Twig_SupTwgSgg_SimpleFunction('get_image_src', 'wp_get_attachment_image_src'));

    $settings = $this->getModel('settings')->get($galleryId);
    //        $preset   = $this->getModel('preset')->getBySettingsId($settings->id);
    if (!is_object($settings) || null === $settings->data) {
      $config = $this->getEnvironment()->getConfig();
      $config->load('@galleries/settings.php');

      $settings = new stdClass();

      $settings->id = null;
      $settings->data = unserialize($config->get('gallery_settings'), ['allowed_classes' => false]);
    }

    $galleryModule = $this->getModule('galleries');
    // $settings->data['socialSharing'] = $galleryModule->initGallerySocialShare($settings->data);
    $settings->data['rtl'] = is_rtl();

    $uiModule = $this->getModule('ui');
    $fontList = array_merge($this->getModel('settings')->getFontsList(), $uiModule->getStandardFontsList());
    sort($fontList);

    // $membershipModel = $this->getModel('membership');
    //     $pageOptions = array(
    //     	'isSettingPage' => 1,
    // 	'isMembershipPluginActive' => $membershipModel->isPluginActive(),
    // 	'membershipInstallWpUrl' => $membershipModel->getPluginInstallWpUrl(),
    // 	'membershipInstallUrl' => $membershipModel->getPluginInstallUrl(),
    // );

    $pageOptions = [];

    $membershipModel = false;

    if ($request->query['clone_type'] != null && $request->query['oldGalleryId'] != null) {
      $cloneType = $request->query['clone_type'];
      $oldGalleryId = $request->query['oldGalleryId'];
      if ($cloneType != 2) {
        // Image optimize
        $imageOptimizeModel = $this->getModel('imageOptimize');
        $optimizeInfo = $imageOptimizeModel->getInfoByGalleryId($oldGalleryId);
        if (!empty($optimizeInfo[0]['service_code'])) {
          $ioServiceCode = $optimizeInfo[0]['service_code'];
          $requirements = $imageOptimizeModel->checkRequirements();
          $imgOptimizationSett = $this->getModel('optimization')->getServiceSettings();

          if (!$requirements && isset($imgOptimizationSett['setting'][$ioServiceCode]['auth_key'])) {
            $ioParams = $imgOptimizationSett;
            $ioParams['current'] = $ioServiceCode;
          }
        }
        // CDN
        $cdnModel = $this->getModel('cdn');
        $cdnServiceCode = $cdnModel->getServiceCodeByGalleryId($oldGalleryId);
        $cdnRequirements = $cdnModel->checkRequirements();
        if (!$cdnRequirements && !empty($cdnServiceCode[0]['service_code'])) {
          $cdnServiceCode = $cdnServiceCode[0]['service_code'];
          $cdnSett = $cdnModel->getServiceSettings();
          if (isset($cdnSett['setting'][$cdnServiceCode])) {
            $cdnParams = $cdnSett;
            $cdnParams['current'] = $cdnServiceCode;
          }
        }
      }
    }

    return $this->response('@galleries/settings.twig', [
      'gallery' => $this->getModel('galleries')->getById($galleryId),
      'settings' => $settings->data,
      'id' => $settings->id,
      // deprecated
      'preset' => null,
      'fontList' => $fontList,
      'pageOptions' => $pageOptions,
      'ioServiceParams' => isset($ioParams) ? json_encode($ioParams) : null,
      'cdnServiceParams' => isset($cdnParams) ? json_encode($cdnParams) : null,
      'pluginUrl' => $this->getModuleUrl(),
    ]);
  }

  public function getModuleUrl()
  {
    return plugins_url('', __FILE__);
  }

  /**
   * Save Settings Action
   * Saves the specified gallery's settings
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function saveSettingsAction(RscSgg_Http_Request $request)
  {
    /** @var GridGallery_Galleries_Model_Settings $settings */
    $galleryId = $request->query->get('gallery_id');
    $settings = $this->getModel('settings');

    // Insurance against a direct POST bypassing the settings form's own
    // confirm dialog (settings.js): saveSettingsAction fully replaces the
    // stored settings blob with just what's submitted, and Pro fields
    // render `disabled` while unlicensed so the browser never sends them -
    // saving here would silently erase any Pro option this gallery already
    // has configured. Checked against the settings as they are RIGHT NOW,
    // before this request touches them.
    if ($this->isLicenseInactive()) {
      $existing = $settings->get($galleryId);
      $existingData = $existing && isset($existing->data) ? $existing->data : null;

      if ($this->gallerySettingsHaveProConfigured($existingData) && (string) $request->post->get('gg_confirm_pro_wipe') !== '1') {
        return $this->redirect(
          $this->generateUrl('galleries', 'settings', [
            'gallery_id' => $galleryId,
            'gg_pro_wipe_blocked' => 1,
          ]),
        );
      }
    }

    $stats = $this->getEnvironment()->getModule('stats');
    $config = $this->getEnvironment()->getConfig();

    $postData = $request->post->all();

    if (isset($postData['socialSharing']['enabled']) && $postData['socialSharing']['enabled'] && isset($postData['socialSharing']['projectId'])) {
      do_action('sss_show_at_grid_gallery', $postData['socialSharing']['projectId']);
    }

    $allSettings = $request->post->all();
    if (isset($allSettings['attributes']) && isset($allSettings['attributes']['order'])) {
      $allSettings['attributes']['order'] = json_decode($allSettings['attributes']['order']);
      $allSettings['attributes']['enable'] = json_decode($allSettings['attributes']['enable']);
      if (isset($allSettings['attributes']['rename'])) {
        $rename = json_decode($allSettings['attributes']['rename']);
        if (class_exists('GridGalleryPro_Galleries_Model_Attributes')) {
          $attributesModel = new GridGalleryPro_Galleries_Model_Attributes();
          $attributesModel->renameAttributes($galleryId, $rename);
        }
        unset($allSettings['attributes']['rename']);
      }
    }

    if (isset($allSettings['ui']['collapsedSections'])) {
      $decodedCollapsedSections = json_decode($allSettings['ui']['collapsedSections'], true);
      $allSettings['ui']['collapsedSections'] = is_array($decodedCollapsedSections) ? $decodedCollapsedSections : [];
    }

    $settings->settingsDiff($stats, $galleryId, $allSettings);
    $data = $settings->getCatsFromPreset($allSettings, $config);
    $data = $settings->getPagesFromPreset($data, $config);

    if (!empty($data)) {
      $settings->save($galleryId, $data);
      $galleriesModel = $this->getModel('galleries');
      $galleriesModel->rename($galleryId, $data['title']);
      $this->getModule('galleries')->cleanCache($galleryId, false);
    }

    return $this->redirect(
      $this->generateUrl('galleries', 'settings', [
        'gallery_id' => $request->query->get('gallery_id'),
      ]),
    );
  }

  /**
   * Mirrors the license.isActive() check settings.twig uses for the same
   * purpose (galleryFeatureStatuses / the pro-wipe warning) - deliberately
   * NOT environment->isModule('license'), which actually means "is the
   * license admin page the current request" rather than "is the license
   * module registered", and getModule() returns null (not a fatal) when
   * the module isn't registered at all in a Free-only install.
   *
   * @return bool
   */
  private function isLicenseInactive()
  {
    $environment = $this->getEnvironment();

    if (!$environment->isPro()) {
      return true;
    }

    $licenseModule = $environment->getModule('license');

    return !$licenseModule || !$licenseModule->isActive();
  }

  /**
   * Mirrors settings.twig's hasProConfiguredSettings: true if any option
   * that is CURRENTLY Pro-only, and was NEVER seeded by one of the built-in
   * gallery-creation templates (configs/presets.php - applied to every
   * gallery on creation, see Galleries::add()), has a value already saved
   * for this gallery - a sign it was configured during an earlier active-
   * license session and would be silently erased by a plain settings save
   * while unlicensed. Checks presence/value on the raw stored data, not the
   * currently-rendered form, so it still reflects reality even before this
   * request's own save happens.
   *
   * Captions (thumbnail.overlay.enabled), Categories, Icons, Pagination and
   * Posts are deliberately NOT checked here even though their fields are
   * Pro-gated in the CURRENT settings.twig: every one of the 9 built-in
   * presets bakes in a fully-populated sub-array for these (colors,
   * borders, positions, and for Captions/Categories/Icons/Pagination/Posts
   * specifically an "enabled"-ish value that is 'true'/1 in at least one
   * shipped preset) - they were free-available back when those presets were
   * authored. A brand new, never-licensed gallery can inherit any of these
   * as "enabled" purely from its creation preset, so their stored value
   * can't be trusted to mean "a Pro session configured this."
   *
   * @param mixed $data
   * @return bool
   */
  private function gallerySettingsHaveProConfigured($data)
  {
    if (!is_array($data)) {
      return false;
    }

    $checks = [
      isset($data['showMore']['enabled']) && $data['showMore']['enabled'] === 'true',
      !empty($data['watermark']['enabled']),
      !empty($data['socialSharing']['enabled']),
      isset($data['lazyload']['enabled']) && $data['lazyload']['enabled'] === '1',
      isset($data['exif']['enabled']) && (int) $data['exif']['enabled'] === 1,
      isset($data['attributes']['enabled']) && $data['attributes']['enabled'] === 'true',
    ];

    foreach ($checks as $check) {
      if ($check) {
        return true;
      }
    }

    return false;
  }

  /**
   * Save custom categories preset
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function saveCatsPresetAction(RscSgg_Http_Request $request)
  {
    if (isset($request->post['route']['options'])) {
      $data = $request->post['route']['options'];

      if (get_option('customCatsPresets')) {
        $presets = get_option('customCatsPresets');
      } else {
        $presets = [];
      }

      $presetsNames = $this->getModel('preset')->getCatsPresetsNames();
      $indx = array_search($data['preset']['name'], $presetsNames);

      if ($indx) {
        $presets[$indx - 1]['categories'] = $data['categories'];
      } else {
        array_push($presets, $data);
      }

      update_option('customCatsPresets', $presets);

      return $this->response(RscSgg_Http_Response::AJAX, [
        'success' => 'ok',
      ]);
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => 'error',
    ]);
  }

  /**
   *
   * Get categories custom presets
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function getCustomCatsPresetsAction(RscSgg_Http_Request $request)
  {
    $presets = get_option('customCatsPresets');
    $names = [];

    if ($presets) {
      foreach ($presets as $preset) {
        array_push($names, $preset['preset']['name']);
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'names' => $names,
    ]);
  }

  /**
   * Save pages custom preset
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function savePagesPresetAction(RscSgg_Http_Request $request)
  {
    if (isset($request->post['route']['options'])) {
      $data = $request->post['route']['options'];
      if (get_option('customPagesPresets')) {
        $presets = get_option('customPagesPresets');
      } else {
        $presets = [];
      }

      $presetsNames = $this->getModel('preset')->getPagesPresetsNames();
      $indx = array_search($data['preset']['name'], $presetsNames);

      if ($indx) {
        $presets[$indx - 1]['pagination'] = $data['pagination'];
      } else {
        array_push($presets, $data);
      }

      update_option('customPagesPresets', $presets);

      return $this->response(RscSgg_Http_Response::AJAX, [
        'success' => 'ok',
      ]);
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => 'error',
    ]);
  }

  /**
   *
   * Get pages custom presets
   *
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function getCustomPagesPresetsAction(RscSgg_Http_Request $request)
  {
    $presets = get_option('customPagesPresets');
    $names = [];

    if ($presets) {
      foreach ($presets as $preset) {
        array_push($names, $preset['preset']['name']);
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'names' => $names,
    ]);
  }

  /**
   * Save Preset Action
   * Saves the settings preset to the database.
   * @param RscSgg_Http_Request $request HTTP request.
   * @return RscSgg_Http_Response
   */
  public function savePresetAction(RscSgg_Http_Request $request)
  {
    $preset = $this->getModel('preset');
    $settingsId = $request->post->get('settings_id');
    $presetTitle = $request->post->get('title');

    $lang = $this->getEnvironment()->getLang();

    if (empty($settingsId) || empty($presetTitle)) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($lang->translate('Not enough data.')));
    }

    if ($preset->set($settingsId, $presetTitle)) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData($lang->translate('Preset successfully saved.')));
    }

    return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($lang->translate(sprintf('Failed to save the preset: %s', $preset->getLastError()))));
  }

  /**
   * Remove Preset Action
   * Removes the settings preset by the preset id.
   * @param RscSgg_Http_Request $request
   * @return RscSgg_Http_Response
   */
  public function removePresetAction(RscSgg_Http_Request $request)
  {
    $preset = $this->getModel('preset');
    $presetId = $request->post->get('preset_id');

    $lang = $this->getEnvironment()->getLang();

    if (null === $presetId || empty($presetId)) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($lang->translate('The preset ID is not specified.')));
    }

    if ($preset->remove($presetId)) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData($lang->translate('Preset successfully removed.')));
    }

    return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($lang->translate(sprintf('Failed to remove the preset: %s.', $preset->getLastError()))));
  }

  public function getPresetListAction()
  {
    return $this->response(RscSgg_Http_Response::AJAX, [
      'error' => false,
      'presets' => $this->getModel('preset')->getAll(),
    ]);
  }

  public function applyPresetAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->post->get('gallery_id');
    $presetId = $request->post->get('preset_id');

    $presets = $this->getModel('preset');
    $settings = $this->getModel('settings');

    $lang = $this->getEnvironment()->getLang();

    $preset = $presets->getById($presetId);

    if (!$preset) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData($lang->translate('Failed to find the preset.')));
    }

    $settings->save($galleryId, $preset->settings->data);

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData($lang->translate('Preset successfully applied to the gallery.')));
  }

  public function getCatsPresetOptionsAction(RscSgg_Http_Request $request)
  {
    if (isset($request->post['route']['selectedPreset'])) {
      $selectedPreset = $request->post['route']['selectedPreset'];
      $presetsNames = $this->getModel('preset')->getCatsPresetsNames();
      $dbPresetOpt = get_option('customCatsPresets');
      $indx = array_search($selectedPreset, $presetsNames);
      if ($indx) {
        $presetOptions = $dbPresetOpt[$indx - 1]['categories'];
        return $this->response(RscSgg_Http_Response::AJAX, [
          'dialogType' => 'customized',
          'presetName' => $selectedPreset,
          'presetOptions' => $presetOptions,
        ]);
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'dialogType' => 'standart',
    ]);
  }

  public function getPagesPresetOptionsAction(RscSgg_Http_Request $request)
  {
    if (isset($request->post['route']['selectedPreset'])) {
      $selectedPreset = $request->post['route']['selectedPreset'];
      $presetsNames = $this->getModel('preset')->getPagesPresetsNames();
      $dbPresetOpt = get_option('customPagesPresets');
      $indx = array_search($selectedPreset, $presetsNames);
      if ($indx) {
        $presetOptions = $dbPresetOpt[$indx - 1]['pagination'];
        return $this->response(RscSgg_Http_Response::AJAX, [
          'dialogType' => 'customized',
          'presetName' => $selectedPreset,
          'presetOptions' => $presetOptions,
        ]);
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, [
      'dialogType' => 'standart',
    ]);
  }

  public function checkReviewNoticeAction(RscSgg_Http_Request $request)
  {
    $showNotice = get_option('showGalleryRevNotice');
    $show = false;

    if (!$showNotice) {
      update_option('showGalleryRevNotice', [
        'date' => time(),
        'is_shown' => false,
      ]);
    } else {
      if ($showNotice['date'] instanceof DateTime) {
        $showNotice['date'] = $showNotice['date']->getTimestamp();
      }
      $days = floor((time() - $showNotice['date']) / (60 * 60 * 24));
      if ($days > 7 && $showNotice['is_shown'] != true) {
        $show = true;
      }
    }

    return $this->response(RscSgg_Http_Response::AJAX, ['show' => $show]);
  }

  public function checkNoticeButtonAction(RscSgg_Http_Request $request)
  {
    $code = 'is_shown';
    $showNotice = get_option('showGalleryRevNotice');

    if ($code == 'is_shown') {
      $showNotice['is_shown'] = true;
    } else {
      $showNotice['date'] = time();
    }

    $this->sendUsageStat($code);
    update_option('showGalleryRevNotice', $showNotice);

    return $this->response(RscSgg_Http_Response::AJAX);
  }

  public function sendUsageStat($state)
  {
    $apiUrl = 'http://updates.supsystic.com';

    $reqUrl = $apiUrl . '?mod=options&action=saveUsageStat&pl=rcs';
    $res = wp_remote_post($reqUrl, [
      'body' => [
        'site_url' => get_bloginfo('wpurl'),
        'site_name' => get_bloginfo('name'),
        'plugin_code' => 'sgg',
        'all_stat' => ['views' => 'review', 'code' => $state],
      ],
    ]);

    return true;
  }

  /**
   * Rewrites @url annotation to the full url.
   *
   * @param string $element
   * @return string
   */
  public function rewrite($element)
  {
    $cdnUrl = $this->getEnvironment()->getModule('core')->getCdnUrl();
    $element = str_replace('@cdn_url', $cdnUrl, $element);

    $url = $this->getEnvironment()->getConfig()->get('plugin_url');

    return str_replace('@url', $url . '/app/assets/img', $element);
  }

  public function ajaxGetImagesAction(RscSgg_Http_Request $request)
  {
    if (null === ($galleryId = $request->post->get('gallery_id'))) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData('Gallery identifier is not specified.'));
    }

    /** @var GridGallery_Galleries_Model_Galleries $galleries */
    $galleries = $this->getModel('galleries');

    if (null === ($gallery = $galleries->getById((int) $galleryId))) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData('The gallery does not exists.'));
    }

    $gallery->settings = $this->getModel('settings')->get($galleryId)->data;

    return $this->response(
      RscSgg_Http_Response::AJAX,
      $this->getSuccessResponseData(null, [
        'photos' => $gallery->photos,
        'area' => $gallery->settings['area'],
      ]),
    );
  }

  public function ajaxResizeImageAction(RscSgg_Http_Request $request)
  {
    if (!function_exists('wp_get_image_editor')) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData('Current WordPress revision has not Image Editor.'));
    }

    $attachmentId = $request->post->get('attachment_id');
    $width = $request->post->get('width');
    $height = $request->post->get('height');

    if (!$attachmentId) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData('The attachment id is not specified.'));
    }

    $meta = wp_get_attachment_metadata($attachmentId);
    $upload = wp_upload_dir();

    if (!is_file($file = $upload['basedir'] . '/' . $meta['file'])) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData(sprintf('File not found: %s', $file)));
    }

    if (!$width || !$height) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData('Width or Height is not specified.'));
    }

    $editor = wp_get_image_editor($file);

    if (is_wp_error($editor)) {
      return $this->response(RscSgg_Http_Response::AJAX, $this->getErrorResponseData(sprintf('Unable to load the image: %s', $file)));
    }

    if (is_wp_error($error = $editor->resize((int) $width, (int) $height, true))) {
      return $this->response(
        RscSgg_Http_Response::AJAX,
        $this->getErrorResponseData(sprintf('Unable to resize the image: %s.', $file), [
          'reason' => $error->get_error_message(),
        ]),
      );
    }

    $image = $editor->save();

    return $this->response(
      RscSgg_Http_Response::AJAX,
      $this->getSuccessResponseData(sprintf('Attachment %s resized successfully.', $attachmentId), [
        'image' => $image,
      ]),
    );
  }

  /**
   * Save sort images by (Size, Name, Add date, Create Date)
   *
   * @param RscSgg_Http_Request $request query post param
   * @return string msg Response answer
   */
  public function saveSortByAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->post->get('gallery_id');
    $this->saveSortChoice($galleryId, $request->post->get('sortby'), $request->post->get('sortto'));

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData('Save sorted'));
  }

  /**
   * Persists the gallery's Sort By/To choice. Shared by saveSortByAction
   * (dedicated AJAX call) and photosDataAction (which also accepts sort/dir
   * so the tile grid can change sort and re-fetch in one round-trip).
   */
  private function saveSortChoice($galleryId, $sortby, $sortto)
  {
    $sort_fields['sort'] = [
      'sortby' => $sortby,
      'sortto' => $sortto,
    ];

    global $wpdb;
    update_option($wpdb->prefix . $this->getConfig()->get('db_prefix') . 'rand_sorts', [
      'id' => $galleryId,
      'val' => $sort_fields['sort']['sortby'] === 'randomly' ? true : false,
    ]);

    $settings = $this->getModel('settings')->get($galleryId);
    $mydata = array_merge($settings->data, $sort_fields);

    $this->getModel('settings')->save($galleryId, $mydata);
    $this->getModule('galleries')->cleanCache($galleryId);

    return $mydata;
  }

  /**
   * Persists the display order of the Pro category bins (Images List page,
   * "Show Categories" view) - dragging a category block calls this the same
   * way dragging an image calls updatePosition, so the ordering the user
   * sees is what actually gets saved instead of reverting on reload.
   */
  public function saveCategoryOrderAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->post->get('gallery_id');
    $order = $request->post->get('order');

    if (!is_array($order)) {
      $order = [];
    }
    $order = array_values(array_map('sanitize_text_field', $order));

    $settings = $this->getModel('settings')->get($galleryId);
    $mydata = array_merge($settings->data, ['categoryOrder' => $order]);

    $this->getModel('settings')->save($galleryId, $mydata);
    $this->getModule('galleries')->cleanCache($galleryId);

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData('Category order saved'));
  }

  public function saveUiStateAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->post->get('gallery_id');
    // Not sanitize_key() - it lowercases, and these ids are case-sensitive
    // DOM ids (e.g. "useShadowRow") shared with the hidden ui[collapsedSections]
    // form field, which preserves case; lowercasing here would silently
    // split one section's state across two different array keys.
    $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->post->get('key'));
    $collapsed = (bool) (int) $request->post->get('collapsed');

    $settings = $this->getModel('settings')->get($galleryId);
    $data = $settings->data;
    if (!isset($data['ui']['collapsedSections']) || !is_array($data['ui']['collapsedSections'])) {
      $data['ui']['collapsedSections'] = [];
    }

    if ($collapsed) {
      $data['ui']['collapsedSections'][$key] = true;
    } else {
      unset($data['ui']['collapsedSections'][$key]);
    }

    $this->getModel('settings')->save($galleryId, $data);

    return $this->response(RscSgg_Http_Response::AJAX, $this->getSuccessResponseData('UI state saved'));
  }

  public function getGalleriesListAction(RscSgg_Http_Request $request)
  {
    $galleries = $this->getModel('galleries');

    return $this->response(RscSgg_Http_Response::AJAX, [
      'list' => $galleries->getList(),
    ]);
  }

  public function importSettingsAction(RscSgg_Http_Request $request)
  {
    $settingsModel = $this->getModel('settings');
    $from = $request->post->get('from');
    $to = $request->post->get('to');
    $settings = $settingsModel->get($from);
    $settingsModel->save($to, $settings->data);
    $this->getModule('galleries')->cleanCache($to, true);
    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => true,
    ]);
  }

  protected function getCloneParams()
  {
    // 	not copied tables: gg_folders, gg_photos, gg_photos_settings, gg_settings_presets, gg_stats
    $environment = $this->getEnvironment();
    return [
      'environment' => $environment,
      //'socialSharing' => 'GridGallery_Galleries_Model_SocialSharing',
      //'preset' => $this->getModel('preset'),
      //'photos' => $this->getModel('photos'),
      //'folders' => $this->getModel('folders'),
      'resources' => $this->getModel('resources'),
      'settings' => $this->getModel('settings'),
      'position' => $this->getModel('position'),
      // 'membership' => $this->getModel('membership'),
      'cdn' => $this->getModel('cdn'),
    ];
  }

  public function cloneAction(RscSgg_Http_Request $request)
  {
    $route = $request->post->get('route');
    $oldGalleryId = null;
    $result = [
      'isError' => true,
      'message' => $this->translate('Gallery clone error'),
    ];
    if (isset($route['gallery_id']) && isset($route['clone_type'])) {
      $oldGalleryId = (int) $route['gallery_id'];
      $cloneType = $route['clone_type'];
      if ($oldGalleryId && $cloneType) {
        $galleryModel = $this->getModel('galleries');
        $result = $galleryModel->cloneGallery($oldGalleryId, $cloneType, $this->getCloneParams());
      }
    }
    return $this->response(RscSgg_Http_Response::AJAX, $result);
  }

  public function createDefaultGallerySettingsAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->post->get('id');
    update_option('defaultgallerysettings', $galleryId);

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => true,
      'message' => 'success',
    ]);
  }

  public function removeDefaultGallerySettingsAction(RscSgg_Http_Request $request)
  {
    $galleryId = $request->post->get('id');
    delete_option('defaultgallerysettings', $galleryId);

    return $this->response(RscSgg_Http_Response::AJAX, [
      'success' => true,
      'message' => 'success',
    ]);
  }
}
