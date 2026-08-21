<?php

/**
 * Class SocialSharing_Promo_Module
 *
 * Promo module.
 */
class GridGallery_Promo_Module extends GridGallery_Core_Module
{
  const WELCOME_TOUR_CAMPAIGN = '2026-08-gallery-welcome-tour';
  const WELCOME_TOUR_USER_META = 'sgg-welcome-tour-campaign-seen';

  /**
   * Module initialization.
   */
  public function onInit()
  {
    parent::onInit();

    //add_action($this->getConfig()->get('hooks_prefix') . 'after_ui_loaded', array($this, 'loadAdminPromoAssets'));
    add_action('admin_init', [$this, 'loadAdminPromoAssets']);
    add_action('wp_ajax_sgg-tutorial-close', [$this, 'endTutorial']);
  }
  public function loadAdminPromoAssets()
  {
    $ui = $this->getEnvironment()->getModule('Ui');

    // Loaded on the Overview page unconditionally so the replay button always
    // works. Auto-open is controlled by a versioned per-user campaign flag in
    // enqueueWelcomeTourAssets(), which lets us show new welcome content once
    // to every user after future redesigns.
    if ($this->isModule('overview')) {
      $ui->asset->enqueue(
        'styles',
        [$this->getPromoAssetUrl('/assets/css/welcome-tour.css')],
        'backend',
        true,
      );
      $ui->asset->enqueue(
        'scripts',
        [
          [
            'handle' => 'sgg-welcome-tour',
            'source' => $this->getPromoAssetUrl('/assets/js/welcome-tour.js'),
            'dependencies' => ['jquery'],
          ],
        ],
        'backend',
        true,
      );

      add_action('admin_enqueue_scripts', [$this, 'enqueueWelcomeTourAssets']);
    }

    // The detailed click-through walkthrough (tutorial.js) - unlike the
    // welcome-tour modal above, this one is never shown just because a user
    // is new; it only runs while 'sgg-detailed-tour-active' is set, which
    // happens exclusively via promo/showTutorial (the welcome-tour modal's
    // "Start step-by-step tour" button, and the Overview page's own replay
    // button both route through it). Loaded globally, not Overview-only,
    // since steps 2 onward navigate across several different admin pages
    // and rely on tutorial.js's own sessionStorage step tracking to resume.
    if (get_user_meta(get_current_user_id(), 'sgg-detailed-tour-active', true)) {
      $ui->asset->enqueue(
        'scripts',
        [
          [
            'handle' => 'sgg-step-tutorial',
            'source' => $this->getPromoAssetUrl('/assets/js/tutorial.js'),
            'dependencies' => ['wp-pointer', 'sg-ajax.js'],
          ],
        ],
        'backend',
        true,
      );

      add_action('admin_enqueue_scripts', [$this, 'enqueueTutorialAssets']);
    }

    if ($this->isModule('promo', 'welcome') && !$this->getConfig()->get('welcome_page_was_showed')) {
      $ui->asset->enqueue('styles', [$this->getConfig()->get('plugin_url') . '/app/assets/css/libraries/supsystic/suptablesui.min.css']);
      update_option($this->getConfig()->get('db_prefix') . 'welcome_page_was_showed', 1);
    }
  }

  private function getPromoAssetUrl($path)
  {
    $file = $this->getLocation() . $path;
    $version = file_exists($file) ? filemtime($file) : time();

    return add_query_arg('sgg_tour_v', $version, $this->getLocationUrl() . $path);
  }

  public function enqueueWelcomeTourAssets()
  {
    $data = [
      'i18n' => [
        'next' => $this->translate('Next'),
        'prev' => $this->translate('Back'),
        'finish' => $this->translate('Start tour'),
        'close' => $this->translate('Close'),
        'getPro' => $this->translate('Get PRO'),
        'startTour' => $this->translate('Start step-by-step tour'),
      ],
      'closeAction' => 'sgg-tutorial-close',
      // Auto-plays once per WP user for the current welcome campaign. Bump
      // WELCOME_TOUR_CAMPAIGN when a future welcome tour should be shown once
      // to everyone again. The replay button still opens it any time.
      'autoShow' => $this->shouldAutoShowWelcomeTour(),
      'slides' => $this->tourSlides(),
    ];

    wp_localize_script('sgg-welcome-tour', 'GalleryWelcomeTourData', $data);
  }

  /**
   * Slide content for the welcome tour modal (welcome-tour.js). Free
   * features are shown alongside a Pro pitch for the features this plugin
   * gates behind a license. The left side of every slide is a normal image
   * from src/GridGallery/Promo/assets/img/welcome-tour/1.jpg ... 10.jpg so
   * screenshots can be replaced without touching the tour text or layout.
   */
  public function tourSlides()
  {
    return $this->modernTourSlides();
  }

  private function modernTourSlides()
  {
    $proUtm = 'utm_source=plugin&utm_medium=welcome-tour&utm_campaign=gallery';
    $proCtaUrl = $this->isProActive() ? false : $this->getProUrl($proUtm);

    return [
      [
        'eyebrow' => $this->translate('Quick start'),
        'title' => $this->translate('Build a gallery that is ready to publish'),
        'text' => sprintf('<p>%s</p>', $this->translate('In a few minutes you can create a responsive photo gallery, tune the look, add image data, and publish it with a shortcode.')),
        'free' => [
          $this->translate('Create unlimited galleries from ready presets'),
          $this->translate('Preview the result before publishing'),
          $this->translate('Publish with shortcode or PHP code'),
        ],
        'pro' => [
          $this->translate('Unlock advanced layouts, imports, filters and business tools'),
        ],
        'image' => $this->getWelcomeTourImage(1),
        'imageAlt' => $this->translate('Gallery builder overview'),
      ],
      [
        'eyebrow' => $this->translate('Layouts'),
        'title' => $this->translate('Start with the right layout'),
        'text' => sprintf('<p>%s</p>', $this->translate('Choose the gallery structure first, then fine-tune spacing, columns, alignment, image size and crop behavior later in Settings.')),
        'free' => [
          $this->translate('Standard, Vertical, Rounded and Horizontal presets'),
          $this->translate('Fixed, Vertical, Horizontal and Fixed Columns gallery types'),
          $this->translate('Responsive columns for desktop, tablet and mobile'),
        ],
        'pro' => [
          $this->translate('Mosaic gallery and Pro showcase presets'),
          $this->translate('Post Feed gallery layouts'),
        ],
        'image' => $this->getWelcomeTourImage(2),
        'imageAlt' => $this->translate('Gallery layout presets'),
      ],
      [
        'eyebrow' => $this->translate('Import'),
        'title' => $this->translate('Bring media in from where it already lives'),
        'text' => sprintf('<p>%s</p>', $this->translate('Free users can build galleries from the WordPress Media Library. PRO removes the busywork when photos and videos live in external sources.')),
        'free' => [
          $this->translate('Upload new files'),
          $this->translate('Choose images from WordPress Media Library'),
        ],
        'pro' => [
          $this->translate('Import EXIF metadata'),
          $this->translate('Add YouTube and Vimeo videos'),
          $this->translate('Import from Flickr, Tumblr, FTP and Google Drive'),
        ],
        'image' => $this->getWelcomeTourImage(3),
        'imageAlt' => $this->translate('Media import sources'),
      ],
      [
        'eyebrow' => $this->translate('Image manager'),
        'title' => $this->translate('Control every image after upload'),
        'text' => sprintf('<p>%s</p>', $this->translate('The image list is not just storage. It is where you prepare photos for visitors, search engines and clicks.')),
        'free' => [
          $this->translate('SEO Alt / Title and custom Link'),
          $this->translate('Drag reorder, sort, crop, rotate, replace and delete'),
          $this->translate('Bulk copy, move and rotate selected images'),
        ],
        'pro' => [
          $this->translate('Captions and descriptions for image context'),
          $this->translate('Visual image editor'),
          $this->translate('Linked images, hover image, categories and per-image video'),
        ],
        'image' => $this->getWelcomeTourImage(4),
        'imageAlt' => $this->translate('Image manager and captions'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Style'),
        'title' => $this->translate('Make thumbnails feel like part of the page'),
        'text' => sprintf('<p>%s</p>', $this->translate('Tune the visual system of the gallery without touching CSS: size, radius, border, shadow, loader and hover behavior are all available in the settings flow.')),
        'free' => [
          $this->translate('Image spacing, size, radius, crop quality and alignment'),
          $this->translate('Border and shadow controls'),
          $this->translate('Gallery loader and horizontal scroll'),
        ],
        'pro' => [
          $this->translate('Caption Builder for styled text blocks'),
          $this->translate('Hover icons for popup, link, video and download actions'),
        ],
        'image' => $this->getWelcomeTourImage(5),
        'imageAlt' => $this->translate('Thumbnail style settings'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Lightbox'),
        'title' => $this->translate('Give visitors a polished popup experience'),
        'text' => sprintf('<p>%s</p>', $this->translate('The gallery is the first impression. The popup is where visitors slow down, read details, watch slides and interact with each image.')),
        'free' => [
          $this->translate('Preview the gallery before publishing'),
          $this->translate('Open images in the gallery flow'),
        ],
        'pro' => [
          $this->translate('Popup styling and behavior controls'),
          $this->translate('Slideshow options'),
          $this->translate('Video, download, details and rotate actions'),
        ],
        'image' => $this->getWelcomeTourImage(6),
        'imageAlt' => $this->translate('Popup and slideshow'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Large galleries'),
        'title' => $this->translate('Keep big collections fast and browsable'),
        'text' => sprintf('<p>%s</p>', $this->translate('When a gallery grows, visitors need structure. PRO adds the browsing tools that turn a long wall of photos into a clean experience.')),
        'free' => [
          $this->translate('Admin search, sorting and bulk actions'),
          $this->translate('Responsive columns for different screen sizes'),
        ],
        'pro' => [
          $this->translate('Categories for one-click filtering'),
          $this->translate('Pagination and Load More button'),
          $this->translate('Lazy Load for image-heavy pages'),
        ],
        'image' => $this->getWelcomeTourImage(7),
        'imageAlt' => $this->translate('Pagination and Load More'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Sharing'),
        'title' => $this->translate('Turn views into traffic'),
        'text' => sprintf('<p>%s</p>', $this->translate('Social sharing makes each image easier to send, save and promote from the gallery or popup.')),
        'free' => [
          $this->translate('Use clean gallery links and image SEO data'),
        ],
        'pro' => [
          $this->translate('Social buttons for Facebook, X, Pinterest and more'),
          $this->translate('Share from gallery thumbnails or popup view'),
          $this->translate('Combine sharing with download and link actions'),
        ],
        'image' => $this->getWelcomeTourImage(8),
        'imageAlt' => $this->translate('Social sharing buttons'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Professional details'),
        'title' => $this->translate('Add trust, protection and searchable data'),
        'text' => sprintf('<p>%s</p>', $this->translate('These PRO tools matter most for photographers, stores, agencies and catalogs where every image carries business context.')),
        'free' => [
          $this->translate('SEO Alt / Title fields for every image'),
          $this->translate('Custom links for visitor actions'),
        ],
        'pro' => [
          $this->translate('Captions and descriptions for visitor context'),
          $this->translate('Watermark with position, size and transparency controls'),
          $this->translate('EXIF camera data'),
          $this->translate('Custom Attributes with frontend filter and search'),
        ],
        'image' => $this->getWelcomeTourImage(9),
        'imageAlt' => $this->translate('Attributes, EXIF and Watermark'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Hands-on tutorial'),
        'title' => $this->translate('Now create one gallery step by step'),
        'text' => sprintf('<p>%s</p>', $this->translate('The quick tour showed what Gallery can do. The step-by-step tutorial will now walk through presets, image import, image data, settings, Pro-only upgrades and publishing.')),
        'free' => [
          $this->translate('Create a gallery from a preset'),
          $this->translate('Add images and edit SEO fields and links'),
          $this->translate('Adjust layout, style and publish settings'),
        ],
        'pro' => [
          $this->translate('See where PRO features fit naturally while building'),
          $this->translate('Already own PRO? Activate the license to unlock them'),
        ],
        'image' => $this->getWelcomeTourImage(10),
        'imageAlt' => $this->translate('Start step by step tutorial'),
        'tourUrl' => $this->getEnvironment()->generateUrl('promo', 'showTutorial'),
        'ctaLabel' => $this->translate('See PRO plans'),
        'ctaUrl' => $proCtaUrl,
      ],
    ];

  }

  private function isProActive()
  {
    if ($this->getEnvironment()->isPro()) {
      return true;
    }

    if (!function_exists('is_plugin_active')) {
      include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    return function_exists('is_plugin_active') && is_plugin_active('supsystic-gallery-pro/index.php');
  }

  private function shouldAutoShowWelcomeTour()
  {
    return get_user_meta(get_current_user_id(), self::WELCOME_TOUR_USER_META, true) !== self::WELCOME_TOUR_CAMPAIGN;
  }

  private function markWelcomeTourShown()
  {
    update_user_meta(get_current_user_id(), self::WELCOME_TOUR_USER_META, self::WELCOME_TOUR_CAMPAIGN);
  }

  private function getWelcomeTourImage($number)
  {
    return $this->getPromoAssetUrl('/assets/img/welcome-tour/' . (int)$number . '.webp');
  }

  private function getProUrl($utmParams)
  {
    return $this->getEnvironment()->getProUrl($utmParams);
  }

  /**
   * The detailed click-through walkthrough (wp-pointer bubbles anchored to
   * real UI elements, tutorial.js). Unlike the welcome-tour modal above,
   * this one only ever runs once explicitly started - see
   * Controller::showTutorialAction() and the 'sgg-detailed-tour-active'
   * usermeta gate in loadAdminPromoAssets().
   *
   * Steps 9-17 are new: the original 12-step version only pointed at
   * Border/Shadow/Captions/Icons/Watermark/Attributes/EXIF/Social
   * Sharing/Categories/Pagination in passing text on 1-2 catch-all steps,
   * or (for the old steps 9-10) targeted '.form-tabs a:eq(2)'/'eq(3)' -
   * indexes that don't exist any more now that Posts is the *second* tab
   * (eq(1)), not the fourth. Every setting below now gets its own step
   * pointed straight at its actual row id on the Settings page instead
   * (openPointer() already auto-scrolls to whatever target is offscreen).
   */
  public function pointers()
  {
    return $this->modernPointers();
  }

  private function modernPointers()
  {
    $finalTitle = $this->isProActive()
      ? $this->translate('Done - your PRO workflow is ready')
      : $this->translate('Done - and ready for PRO');
    $finalContent = $this->isProActive()
      ? $this->translate('<p>You now know the full gallery workflow: create, import, edit image data, tune settings and publish.</p><p>Because PRO is active, you can keep going with advanced imports, videos, Mosaic, categories, pagination, Load More, Caption Builder, watermark, EXIF, Custom Attributes, social sharing and post-feed galleries.</p>')
      : $this->translate('<p>You now know the full Free workflow: create, import, edit image data, tune settings and publish.</p><p>Upgrade to PRO when you need advanced imports, videos, Mosaic, categories, pagination, Load More, Caption Builder, watermark, EXIF, Custom Attributes, social sharing and post-feed galleries.</p>');

    return [
      [
        'id' => 'step-2',
        'class' => 'sgg-tutorial-step-2',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Pick a preset and create the gallery')),
        'content' => sprintf('%s', $this->translate('<p>Choose one of the free presets: Standard, Vertical, Rounded or Horizontal. Enter a gallery name, then click Save.</p><p>PRO adds more specialized starters such as Mosaic, Categories and Icons, Post Feed and Pagination layouts.</p>')),
        'target' => '#gallery-create',
        'fallbackTarget' => '.presetSelect',
        'edge' => 'top',
        'align' => 'middle',
        'nextURL' => false,
      ],
      [
        'id' => 'step-3',
        'class' => 'sgg-tutorial-step-3',
        'title' => sprintf('<h3>%s</h3>', $this->translate('The gallery toolbar')),
        'content' => sprintf('<p>%s</p>', $this->translate('After saving, you arrive inside the gallery editor. The toolbar keeps the core actions close: Add Images, Settings, Preview, Save, and publishing code.')),
        'target' => '#single-gallery-toolbar',
        'fallbackTarget' => '.supsystic-plugin',
        'edge' => 'top',
        'align' => 'left',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-4',
        'class' => 'sgg-tutorial-step-4',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Add images')),
        'content' => sprintf('%s', $this->translate('<p>Click Add Images to open the import window.</p><p>Free users can add images from WordPress Media Library. PRO users can add video and import from external sources.</p>')),
        'target' => 'button.gallery.import-to-gallery',
        'fallbackTarget' => '#single-gallery-toolbar',
        'edge' => 'top',
        'align' => 'left',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-5',
        'class' => 'sgg-tutorial-step-5',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Import sources')),
        'content' => sprintf('%s', $this->translate('<p>Click Import from WordPress Media Library and choose a few images. This is the main Free import path and the fastest way to make the tutorial gallery real.</p><p>PRO keeps this workflow and adds Import EXIF data, video, Flickr, Tumblr, FTP and Google Drive for larger professional libraries.</p>')),
        'target' => '#gg-btn-upload',
        'fallbackTarget' => '#importDialog',
        'edge' => 'top',
        'align' => 'middle',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-6',
        'class' => 'sgg-tutorial-step-6',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Manage the image list')),
        'content' => sprintf('%s', $this->translate('<p>This screen is where images become a gallery. Drag photos to reorder, select several images for bulk actions, search the list, sort by date, size, name or position, and switch tile sizes for easier editing.</p>')),
        'target' => '#single-gallery-toolbar',
        'fallbackTarget' => '.supsystic-plugin',
        'edge' => 'top',
        'align' => 'left',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-7',
        'class' => 'sgg-tutorial-step-7',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Image cards and free editing')),
        'content' => sprintf('%s', $this->translate('<p>Each image card has useful free tools: SEO Alt / Title, Link, Choose effect, Copy to, Move to, Rotate, Crop, Meta, Replace and Delete.</p><p>If the gallery is empty, add at least one image later and these tools will appear on each card.</p>')),
        'target' => '#gg-tile-grid .gg-tile:visible:first',
        'fallbackTarget' => '#single-gallery-toolbar',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-8',
        'class' => 'sgg-tutorial-step-8',
        'title' => sprintf('<h3>%s</h3>', $this->translate('SEO, links and PRO captions')),
        'content' => sprintf('%s', $this->translate('<p>Free users can prepare every image with SEO Alt / Title fields and a custom Link for clicks to pages, products or files.</p><p>PRO adds Captions and Description content for richer thumbnail, hover and popup presentation.</p>')),
        'target' => '#gg-tile-grid .gg-tile:visible:first',
        'fallbackTarget' => '.supsystic-plugin',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-9',
        'class' => 'sgg-tutorial-step-9',
        'title' => sprintf('<h3>%s</h3>', $this->translate('PRO image-level tools')),
        'content' => sprintf('%s', $this->translate('<p>PRO image tools are made for serious galleries: visual image editing, Custom Attributes, Linked Images, Image on Hover, Categories and Video per image.</p><p>They are shown close to the free image tools so the upgrade path is visible right where the need appears.</p>')),
        'target' => '#gg-tile-grid .gg-tile:visible:first',
        'fallbackTarget' => '.supsystic-plugin',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '@gallery-settings',
      ],
      [
        'id' => 'step-10',
        'class' => 'sgg-tutorial-step-10',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Settings preview')),
        'content' => sprintf('<p>%s</p>', $this->translate('Settings update the gallery preview, so users can tune the result before placing it on a live page.')),
        'target' => '#btnPreviewWindow',
        'fallbackTarget' => '#single-gallery-toolbar',
        'edge' => 'top',
        'align' => 'middle',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-11',
        'class' => 'sgg-tutorial-step-11',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Main layout settings')),
        'content' => sprintf('%s', $this->translate('<p>Free settings cover the core layout: gallery type, responsive columns, gallery name, alignment, width, spacing, image size, radius and crop quality.</p><p>PRO expands the layout family with Mosaic and post-feed oriented displays.</p>')),
        'target' => '#gg-anl-main tr:first-child th:first-child',
        'fallbackTarget' => '#gg-anl-main',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-12',
        'class' => 'sgg-tutorial-step-12',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Loader and horizontal scroll')),
        'content' => sprintf('<p>%s</p>', $this->translate('Gallery Loader and Horizontal Scroll are free quality-of-life settings: the first improves perceived loading, the second turns the grid into a clean horizontal showcase.')),
        'target' => '#gg-anl-preloader',
        'fallbackTarget' => '#gg-anl-main',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-13',
        'class' => 'sgg-tutorial-step-13',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Popup and slideshow')),
        'content' => sprintf('%s', $this->translate('<p>Popup controls shape the full-size image experience. PRO turns this into a much stronger presentation area with slideshow, video-oriented controls, buttons and richer lightbox behavior.</p>')),
        'target' => '#gg-anl-popup',
        'fallbackTarget' => '#gg-anl-main',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-14',
        'class' => 'sgg-tutorial-step-14',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Categories, pagination and Load More')),
        'content' => sprintf('%s', $this->translate('<p>For small galleries, Free settings are enough. Once the gallery grows, PRO adds structure: Categories for filters, Pagination for page-by-page browsing and Load More for smoother browsing without overwhelming the page.</p>')),
        'target' => '#useCats',
        'fallbackTarget' => '#gg-anl-main',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-15',
        'class' => 'sgg-tutorial-step-15',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Border and shadow')),
        'content' => sprintf('<p>%s</p>', $this->translate('Border and Shadow are free styling controls. They are small settings, but they often make the difference between a pasted-in grid and a gallery that feels designed for the page.')),
        'target' => '#gg-anl-border-type',
        'fallbackTarget' => '#useShadowRow',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-16',
        'class' => 'sgg-tutorial-step-16',
        'title' => sprintf('<h3>%s</h3>', $this->translate('PRO captions, icons and Caption Builder')),
        'content' => sprintf('%s', $this->translate('<p>Captions, Description and Caption Builder are PRO presentation tools for users who need more than a plain image grid.</p><p>This is the upsell moment for styled text blocks, hover actions and a more branded gallery experience.</p>')),
        'target' => '#useCaptionBuilder',
        'fallbackTarget' => '#useCaptions',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-17',
        'class' => 'sgg-tutorial-step-17',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Watermark, EXIF and Custom Attributes')),
        'content' => sprintf('%s', $this->translate('<p>These PRO features sell especially well to photographers and businesses: Watermark protects images, EXIF shows camera details, and Custom Attributes enable filterable/searchable metadata.</p>')),
        'target' => '#showWatermRow',
        'fallbackTarget' => '#gg-anl-attributes',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-18',
        'class' => 'sgg-tutorial-step-18',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Social sharing')),
        'content' => sprintf('<p>%s</p>', $this->translate('PRO Social Sharing lets visitors share images from the gallery, individual image actions or the popup. It is a direct value upgrade for portfolios, events, products and visual campaigns.')),
        'target' => '#social-sharing',
        'fallbackTarget' => '#gg-anl-main',
        'edge' => 'left',
        'align' => 'top',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-19',
        'class' => 'sgg-tutorial-step-19',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Posts tab')),
        'content' => sprintf('<p>%s</p>', $this->translate('The PRO Posts tab can build galleries from WordPress posts and pages. It is useful for portfolios, product stories, news grids and any site where images and content should update together.')),
        'target' => '#ggPostsTabLink',
        'fallbackTarget' => '.form-tabs',
        'edge' => 'top',
        'align' => 'left',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-20',
        'class' => 'sgg-tutorial-step-20',
        'title' => sprintf('<h3>%s</h3>', $this->translate('Publish the gallery')),
        'content' => sprintf('%s', $this->translate('<p>When the gallery is ready, copy the shortcode into any page or post. Developers can use the PHP code.</p><p>Save after changes, preview again, then publish with confidence.</p>')),
        'target' => '#ggCodeValueShortcode',
        'fallbackTarget' => '.gg-code-switcher',
        'edge' => 'top',
        'align' => 'right',
        'nextURL' => '#',
      ],
      [
        'id' => 'step-21',
        'class' => 'sgg-tutorial-step-21',
        'title' => sprintf('<h3>%s</h3>', $finalTitle),
        'content' => sprintf('%s', $finalContent),
        'target' => '.supsystic-plugin',
        'fallbackTarget' => '#wpbody-content',
        'edge' => 'top',
        'align' => 'center',
        'nextURL' => '#',
      ],
    ];

  }

  public function enqueueTutorialAssets()
  {
    wp_enqueue_style('wp-pointer');

    $data = [
      'next' => $this->translate('Next'),
      'close' => $this->translate('Close Tutorial'),
      'closeIcon' => $this->translate('Close'),
      'autoGalleryTitle' => $this->translate('Step by step gallery'),
      'resetStep' => isset($_GET['sgg_tutorial_reset']) && $_GET['sgg_tutorial_reset'],
      'pointersData' => $this->pointers(),
    ];

    wp_localize_script('sgg-step-tutorial', 'GalleryPromoPointers', $data);
  }

  public function endTutorial()
  {
    $this->markWelcomeTourShown();
    update_user_meta(get_current_user_id(), 'sgg-detailed-tour-active', false);
    update_user_meta(get_current_user_id(), 'sgg-tutorial_was_showed', true);
  }

  public function render($template, $parameters = [])
  {
    $twig = $this->getEnvironment()->getTwig();
    try {
      return preg_replace('/\s+/', ' ', trim($twig->render($template, $parameters)));
    } catch (Exception $e) {
      if (WP_DEBUG) {
        return $e->getMessage();
      }
    }
    return preg_replace('/\s+/', ' ', trim($twig->render($template, $parameters)));
  }
}
