<?php

/**
 * Class SocialSharing_Promo_Module
 *
 * Promo module.
 */
class GridGallery_Promo_Module extends GridGallery_Core_Module
{
  // 2026-11: carousels, sliders and the creation wizard - the new tour opens once for everyone
  const WELCOME_TOUR_CAMPAIGN = '2026-11-gallery-welcome-tour';
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
        'free' => $this->translate('Free'),
        'pro' => 'PRO',
        'included' => $this->translate('Included'),
      ],
      // With PRO active the slides show one list of what is included, without Free / PRO labels or PRO buttons
      'isPro' => $this->isProActive(),
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
        'title' => $this->translate('Galleries, carousels and sliders in one plugin'),
        'text' => sprintf('<p>%s</p>', $this->translate('Build a portfolio grid, a 3D carousel or a hero slider with the creation wizard, check it on a live preview and publish it with a shortcode or a block.')),
        'free' => [
          $this->translate('A creation wizard with seventeen goals'),
          $this->translate('Galleries, carousels and sliders, each with its own editor'),
          $this->translate('Publish with a shortcode or the block editor'),
        ],
        'pro' => [
          $this->translate('100+ styles per type, 3D effects and animated text layers'),
          $this->translate('Mosaic, categories, posts and before / after'),
        ],
        'image' => $this->getWelcomeTourImage(1),
        'imageAlt' => $this->translate('A slider, a carousel and a gallery'),
      ],
      [
        'eyebrow' => $this->translate('Creation wizard'),
        'title' => $this->translate('A finished gallery in about a minute'),
        'text' => sprintf('<p>%s</p>', $this->translate('Tell the wizard what you are making, add photos from the Media Library, try styles on your own images and turn on the extras you need. The editor then opens with everything already set up.')),
        'free' => [
          $this->translate('Photo grid, justified rows, masonry and round thumbnails'),
          $this->translate('Image carousel, logo marquee and product carousel'),
          $this->translate('Image slider, hero slider and image viewer'),
        ],
        'pro' => [
          $this->translate('Mosaic, Polaroid, categories and post feed galleries'),
          $this->translate('Posts carousels and posts sliders'),
          $this->translate('Before / After slider'),
        ],
        'image' => $this->getWelcomeTourImage(2),
        'imageAlt' => $this->translate('The goals of the creation wizard'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Style library'),
        'title' => $this->translate('Change the whole look in one click'),
        'text' => sprintf('<p>%s</p>', $this->translate('Every type has its own styles: arrows, dots, captions, corners, shadows and colors change together, and each of them can be fine-tuned later.')),
        'free' => [
          $this->translate('5 ready styles for every type'),
          $this->translate('Your own colors, corners and shadows'),
        ],
        'pro' => [
          $this->translate('100+ styles per type in ten collections'),
          $this->translate('Studio, Nature, Ocean, Editorial, Pastel, Night, Earth, Atelier, Pop and Seasonal'),
        ],
        'image' => $this->getWelcomeTourImage(3),
        'imageAlt' => $this->translate('The style library'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Carousels'),
        'title' => $this->translate('Carousels that visitors want to swipe'),
        'text' => sprintf('<p>%s</p>', $this->translate('Show several images in a row with arrows, dots, autoplay and swipe. Slides in a row, gaps and heights are set for desktop, tablet and phone separately.')),
        'free' => [
          $this->translate('Image carousels and a logo marquee that never stops'),
          $this->translate('Slides in a row and gaps for every device'),
          $this->translate('Autoplay, loop, swipe, keyboard and right-to-left'),
        ],
        'pro' => [
          $this->translate('3D effects: Coverflow, Cube, Flip, Cards and Creative'),
          $this->translate('Vertical carousels'),
          $this->translate('Carousels of your latest posts'),
        ],
        'image' => $this->getWelcomeTourImage(4),
        'imageAlt' => $this->translate('A 3D coverflow carousel'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Sliders'),
        'title' => $this->translate('Hero sliders with text, buttons and motion'),
        'text' => sprintf('<p>%s</p>', $this->translate('One large slide at a time, with headings, text, buttons and badges placed over the image for each device. The image viewer adds a strip of thumbnails under the slides.')),
        'free' => [
          $this->translate('Image slider, hero slider and image viewer'),
          $this->translate('Text layers placed for desktop, tablet and phone'),
          $this->translate('A separate image for phones'),
        ],
        'pro' => [
          $this->translate('Up to 20 layers per slide with entrance animations'),
          $this->translate('Video slides from YouTube, Vimeo or your files'),
          $this->translate('Before / After comparison slides'),
        ],
        'image' => $this->getWelcomeTourImage(5),
        'imageAlt' => $this->translate('A hero slider with text layers'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Live editor'),
        'title' => $this->translate('See every change on desktop, tablet and phone'),
        'text' => sprintf('<p>%s</p>', $this->translate('Each carousel and slider has its own editor: slides, size, motion, arrows, dots, captions and style, next to a live preview that switches between devices.')),
        'free' => [
          $this->translate('Live preview for desktop, tablet and phone'),
          $this->translate('Arrows, dots and heights for every device'),
          $this->translate('Captions under or over the slides, a lightbox on click'),
        ],
        'pro' => [
          $this->translate('3D effects and vertical direction'),
          $this->translate('Layer animations and video slides'),
        ],
        'image' => $this->getWelcomeTourImage(6),
        'imageAlt' => $this->translate('The carousel editor with a live preview'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('WooCommerce and posts'),
        'title' => $this->translate('Product carousels that sell'),
        'text' => sprintf('<p>%s</p>', $this->translate('Show your WooCommerce products with price, rating, sale badge and Add to cart: the newest, best-selling or top-rated ones, or the ones you pick by hand.')),
        'free' => [
          $this->translate('Product carousels and sliders'),
          $this->translate('Price, sale price, rating, sale badge and Add to cart'),
          $this->translate('Cards in your style or in the look of your shop'),
        ],
        'pro' => [
          $this->translate('Take or skip whole product categories'),
          $this->translate('Carousels and sliders of posts, pages and other post types'),
        ],
        'image' => $this->getWelcomeTourImage(7),
        'imageAlt' => $this->translate('A WooCommerce product carousel'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Galleries'),
        'title' => $this->translate('Galleries that stay easy to browse'),
        'text' => sprintf('<p>%s</p>', $this->translate('Grids, masonry, justified rows and round thumbnails with a modern lightbox, and the tools that keep hundreds of photos fast and easy to browse.')),
        'free' => [
          $this->translate('Grid, masonry, justified rows, fixed columns and round thumbnails'),
          $this->translate('Modern lightbox with swipe, zoom and fullscreen'),
          $this->translate('Albums with Gallery Groups'),
        ],
        'pro' => [
          $this->translate('Mosaic layout'),
          $this->translate('Category tabs, pagination, Load more and infinite scroll'),
          $this->translate('Caption Builder, hover icons and EXIF data'),
        ],
        'image' => $this->getWelcomeTourImage(8),
        'imageAlt' => $this->translate('Gallery layouts'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Protect · Share · Sell'),
        'title' => $this->translate('Protect, share and sell your photos'),
        'text' => sprintf('<p>%s</p>', $this->translate('The tools photographers, studios and agencies ask for: a watermark on every image, sharing buttons, paid access and fast loading pages.')),
        'free' => [
          $this->translate('SEO alt text and titles for every image'),
          $this->translate('Custom links on images'),
        ],
        'pro' => [
          $this->translate('Watermark and right-click protection'),
          $this->translate('Social sharing from the gallery and the lightbox'),
          $this->translate('Paid access, paid downloads and private galleries'),
          $this->translate('Lazy load, WebP and AVIF, and a CDN'),
        ],
        'image' => $this->getWelcomeTourImage(9),
        'imageAlt' => $this->translate('Lightbox, watermark and sharing'),
        'ctaUrl' => $proCtaUrl,
      ],
      [
        'eyebrow' => $this->translate('Hands-on tutorial'),
        'title' => $this->translate('Now build one step by step'),
        'text' => sprintf('<p>%s</p>', $this->translate('The guided tour walks you through the wizard and then through the editor of what you create: a carousel, a slider or a gallery. It takes about three minutes.')),
        'free' => [
          $this->translate('Pick a goal and add your images'),
          $this->translate('Choose a style and create'),
          $this->translate('Tune it in the editor and publish'),
        ],
        'pro' => [
          $this->translate('See where PRO features fit as you build'),
          $this->translate('Already own PRO? Activate the license to unlock them'),
        ],
        'image' => $this->getWelcomeTourImage(10),
        'imageAlt' => $this->translate('Start the step-by-step tutorial'),
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
   * The steps of the click-through tutorial (tutorial.js). It follows the real flow of the plugin: the creation
   * wizard first, then the editor of what was created - a carousel or slider (page "editor") or a classic gallery
   * (pages "settings" and "images"). Each step says on which page it lives; on the wizard page each step belongs to
   * one pane of the wizard ("pane"). Optional steps are skipped when their card is not on the page (the row card of
   * a carousel, the layers card of a slider). "end" closes a branch, "anchor" offsets the bubble beside a settings
   * row, "prepare" opens the settings tab first, "group" picks the group of settings (the tabs of the settings page)
   * and "next" names the page the step leads to.
   * With PRO active the steps never mention PRO: the extra sentences about it are for the free version only.
   */
  public function pointers()
  {
    return $this->modernPointers();
  }

  private function modernPointers()
  {
    $pro = $this->isProActive();
    $step = function ($title, $content, $upsell = '') use ($pro) {
      $html = '';
      foreach (array_merge((array) $content, $pro || $upsell === '' ? [] : [$upsell]) as $paragraph) {
        $html .= '<p>' . $paragraph . '</p>';
      }
      return ['title' => '<h3>' . $title . '</h3>', 'content' => $html];
    };
    $doneTitle = $pro ? $this->translate('Done - your gallery is ready') : $this->translate('Done - and ready for more');
    $doneUpsell = $this->translate('PRO adds 100+ styles per type, 3D effects, animated layers, video and before / after slides, posts, mosaic, categories, watermark and sharing.');

    $steps = [
      // ---------- The creation wizard
      ['id' => 'wz-goal', 'page' => 'wizard', 'pane' => 'goal', 'target' => '.sgg-wz-group:has([data-wz-goal="carousel"]) > h3', 'fallbackTarget' => '[data-wz-step="goal"] header', 'edge' => 'top', 'align' => 'left']
        + $step(
          $this->translate('What are you making?'),
          [
            $this->translate('A gallery shows a grid of images with a lightbox, a carousel slides several images or products in a row, and a slider shows one large slide at a time.'),
            $this->translate('Click any card to continue. For this tour we suggest the Image carousel: Next picks it for you.'),
          ],
          $this->translate('Cards marked PRO - mosaic, categories, posts and before / after - open with PRO.')
        ),
      ['id' => 'wz-images', 'page' => 'wizard', 'pane' => 'images', 'target' => '[data-wz-media]', 'fallbackTarget' => '[data-wz-step="images"] header', 'edge' => 'top', 'align' => 'right']
        + $step($this->translate('Add your images'), [
          $this->translate('Click Choose images and select several photos in the Media Library, or upload new ones in the same window. Drag the thumbnails to change their order.'),
          $this->translate('No images at hand? Continue anyway and add them later on the Images page.'),
        ]),
      ['id' => 'wz-style', 'page' => 'wizard', 'pane' => 'style', 'target' => '.sgg-wz-style-tools', 'fallbackTarget' => '[data-wz-step="style"] header', 'edge' => 'top', 'align' => 'left']
        + $step(
          $this->translate('Try styles on your own images'),
          $this->translate('Click a style and the preview shows it at once: arrows, dots, captions, corners, shadows and colors change together. The collection menu narrows the list.'),
          $this->translate('Five styles per type are free; PRO adds 100 more in ten collections.')
        ),
      ['id' => 'wz-extras', 'page' => 'wizard', 'pane' => 'extras', 'target' => '.sgg-wz-extras-list', 'fallbackTarget' => '[data-wz-step="extras"] header', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Turn on the extras'),
          $this->translate('Arrows, pagination, autoplay, captions, the lightbox and more: every switch shows on the preview at once, and the new gallery gets them all.'),
          $this->translate('Options marked PRO - 3D effects, the category filter, pages, Load more and lightbox tools - open with PRO. Point at one to see it on the preview.')
        ),
      ['id' => 'wz-name', 'page' => 'wizard', 'pane' => 'name', 'target' => '[data-wz-title]', 'fallbackTarget' => '[data-wz-create]', 'edge' => 'top', 'align' => 'left', 'nextLabel' => $this->translate('Create gallery')]
        + $step($this->translate('Name it and create'), $this->translate('The name is for you only. Create the gallery and its editor opens with every option and a live preview - the tour goes on there.')),

      // ---------- Editor of a carousel or a slider
      ['id' => 'ed-preview', 'page' => 'editor', 'target' => '.sgg-sle-devices-switch', 'fallbackTarget' => '.sgg-sle-preview', 'edge' => 'top', 'align' => 'right']
        + $step($this->translate('A live preview for every device'), [
          $this->translate('Every change shows here at once. Switch between desktop, tablet and phone: slides in a row, arrows, dots and heights are set for each device separately.'),
        ]),
      ['id' => 'ed-slides', 'page' => 'editor', 'target' => '[data-sgg-sle-card="content"]', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('What the slides show'),
          $this->translate('The images of this gallery, your WooCommerce products - newest, best-selling, top-rated or picked by hand - or your latest posts.'),
          $this->translate('Posts as slides and category rules for products come with PRO.')
        ),
      ['id' => 'ed-row', 'page' => 'editor', 'optional' => true, 'target' => '[data-sgg-sle-card="row"]', 'edge' => 'left', 'align' => 'top']
        + $step($this->translate('Slides in a row'), $this->translate('How many slides fit in a row and the space between them, on desktop, tablet and phone.')),
      ['id' => 'ed-layers', 'page' => 'editor', 'optional' => true, 'target' => '[data-sgg-sle-card="layers"]', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Text, buttons and badges on the slides'),
          $this->translate('The slide editor places headings, text, buttons and badges over each image, separately for desktop, tablet and phone. A slide can also get its own phone image.'),
          $this->translate('PRO allows up to 20 layers per slide with entrance animations, and adds video and before / after slides.')
        ),
      ['id' => 'ed-size', 'page' => 'editor', 'target' => '[data-sgg-sle-card="size"]', 'edge' => 'left', 'align' => 'top']
        + $step($this->translate('Size'), $this->translate('Width, maximum width and alignment, and the height: fixed, the height of the image or an aspect ratio.')),
      ['id' => 'ed-motion', 'page' => 'editor', 'target' => '[data-sgg-sle-card="motion"]', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Motion and 3D effects'),
          $this->translate('The effect, speed, autoplay, loop, swipe and keyboard. Coverflow, Cube, Flip, Cards and Creative turn a plain carousel or slider into a 3D one.'),
          $this->translate('Slide and fade are free; the 3D effects and the vertical direction come with PRO.')
        ),
      ['id' => 'ed-nav', 'page' => 'editor', 'target' => '[data-sgg-sle-card="navigation"]', 'edge' => 'left', 'align' => 'top']
        + $step($this->translate('Arrows and dots'), $this->translate('The look and place of the arrows and of the dots, bars or numbers, each turned on or off per device.')),
      ['id' => 'ed-captions', 'page' => 'editor', 'optional' => true, 'target' => '[data-sgg-sle-card="captions"]', 'edge' => 'left', 'align' => 'top']
        + $step($this->translate('Captions and clicks'), $this->translate('Show the caption under the slide, over it or on hover, and choose what a click does: open the lightbox, follow the link of the image or nothing.')),
      ['id' => 'ed-style', 'page' => 'editor', 'target' => '[data-sgg-sle-card="style"]', 'edge' => 'left', 'align' => 'top']
        + $step($this->translate('Style and colors'), $this->translate('Switch to another style of the library at any time, or fine-tune the look, corners, shadow and colors of this one.')),
      ['id' => 'ed-images', 'page' => 'editor', 'target' => '.sgg-sle-actions a[href*="action=view"]', 'fallbackTarget' => '.sgg-sle-actions', 'edge' => 'top', 'align' => 'right']
        + $step($this->translate('Images, captions and links'), $this->translate('The Images page holds the slides: change their order, add more, and give each image a caption, alt text and a link.')),
      ['id' => 'ed-publish', 'page' => 'editor', 'target' => '.sgg-sle-code', 'fallbackTarget' => '.sgg-sle-head', 'edge' => 'top', 'align' => 'left']
        + $step($this->translate('Save and publish'), $this->translate('Click Save, then copy the shortcode into any page or post - or add the Photo Gallery block in the block editor.')),
      ['id' => 'ed-done', 'page' => 'editor', 'end' => true, 'target' => '.sgg-sle-head', 'fallbackTarget' => '#wpbody-content', 'edge' => 'top', 'align' => 'center']
        + $step($doneTitle, $this->translate('You have built a carousel or slider from the wizard to the shortcode. Create the next one from Add new: a gallery, a carousel or a slider.'), $doneUpsell),

      // ---------- Settings and images of a classic gallery
      ['id' => 'cl-preview', 'page' => 'settings', 'prepare' => 'top', 'group' => 'layout', 'target' => '#btnPreviewWindow', 'fallbackTarget' => '#single-gallery-toolbar', 'edge' => 'top', 'align' => 'middle']
        + $step($this->translate('Your gallery is ready'), $this->translate('The wizard has created the gallery with your images and style. These settings fine-tune it; Preview shows the result before it goes on a page.')),
      ['id' => 'cl-main', 'page' => 'settings', 'prepare' => 'settings', 'group' => 'layout', 'anchor' => true, 'target' => '#gg-anl-main tr:first-child th:first-child', 'fallbackTarget' => '#gg-anl-main', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Layout'),
          $this->translate('The gallery type, columns for desktop, tablet and phone, width, alignment, spacing, image size, corners and crop.'),
          $this->translate('PRO adds the mosaic layout and galleries made from your posts.')
        ),
      ['id' => 'cl-popup', 'page' => 'settings', 'prepare' => 'settings', 'group' => 'lightbox', 'anchor' => true, 'target' => '#gg-anl-popup', 'fallbackTarget' => '#gg-anl-main', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Lightbox'),
          $this->translate('How the images open: the modern lightbox with swipe, zoom, fullscreen and a counter, in the colors of your style.'),
          $this->translate('PRO adds 6 more lightbox themes, thumbnails, download, share and a slideshow.')
        ),
      ['id' => 'cl-cats', 'page' => 'settings', 'prepare' => 'settings', 'group' => 'categories', 'anchor' => true, 'optional' => true, 'target' => '#useCats', 'fallbackTarget' => '#gg-anl-main', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Categories, pages and Load more'),
          $this->translate('When a gallery grows, category tabs, pagination, a Load more button or infinite scroll keep it light and easy to browse.'),
          $this->translate('These tools come with PRO.')
        ),
      ['id' => 'cl-captions', 'page' => 'settings', 'prepare' => 'settings', 'group' => 'captions', 'anchor' => true, 'optional' => true, 'target' => '#useCaptionBuilder', 'fallbackTarget' => '#useCaptions', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Captions and hover effects'),
          $this->translate('Build captions from the title, description and icons, and add hover effects and overlays.'),
          $this->translate('The Caption Builder, hover icons and EXIF data come with PRO.')
        ),
      ['id' => 'cl-protect', 'page' => 'settings', 'prepare' => 'settings', 'group' => 'sharing', 'anchor' => true, 'optional' => true, 'target' => '#showWatermRow', 'fallbackTarget' => '#social-sharing', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Watermark and sharing'),
          $this->translate('Stamp your logo or text on the images, turn off right click over them, and let visitors share photos on social networks.'),
          $this->translate('Watermark, protection and sharing come with PRO.')
        ),
      ['id' => 'cl-publish', 'page' => 'settings', 'prepare' => 'top', 'group' => 'layout', 'next' => 'images', 'target' => '#ggCodeValueShortcode', 'fallbackTarget' => '.gg-code-switcher', 'edge' => 'top', 'align' => 'right']
        + $step($this->translate('Save and publish'), $this->translate('Save after changes, then copy the shortcode into any page or post - or add the Photo Gallery block in the block editor. Next: the images of the gallery.')),
      ['id' => 'cl-images', 'page' => 'images', 'target' => 'button.gallery.import-to-gallery', 'fallbackTarget' => '#single-gallery-toolbar', 'edge' => 'top', 'align' => 'left']
        + $step($this->translate('The images of the gallery'), $this->translate('Add more images here, drag them into a new order, search, sort and change the size of the tiles. Select several images for bulk actions.')),
      ['id' => 'cl-tile', 'page' => 'images', 'optional' => true, 'target' => '#gg-tile-grid .gg-tile:visible:first', 'fallbackTarget' => '#single-gallery-toolbar', 'edge' => 'left', 'align' => 'top']
        + $step(
          $this->translate('Each image'),
          $this->translate('Every image has SEO alt text and a title, a link for clicks, an effect, crop, rotate, replace, copy and move.'),
          $this->translate('PRO adds captions and descriptions, categories, a hover image and a video for each image.')
        ),
      ['id' => 'cl-done', 'page' => 'images', 'end' => true, 'target' => '#single-gallery-toolbar', 'fallbackTarget' => '#wpbody-content', 'edge' => 'top', 'align' => 'center']
        + $step($doneTitle, $this->translate('You have built a gallery from the wizard to the shortcode. Create the next one from Add new: a gallery, a carousel or a slider.'), $doneUpsell),
    ];

    return $steps;
  }

  public function enqueueTutorialAssets()
  {
    wp_enqueue_style('wp-pointer');

    $data = [
      'next' => $this->translate('Next'),
      'finish' => $this->translate('Finish'),
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
