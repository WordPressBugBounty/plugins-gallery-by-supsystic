<?php

/**
 * Creation wizard of Photo Gallery, made after the table wizard of Data Tables. On the New Gallery page it asks what
 * the gallery is for, takes images from the Media Library, offers the styles of the chosen type with a live picture
 * and creates the gallery in one step: type, settings of the goal, style and images. Then the editor opens.
 */
class GridGallery_Wizard_Module extends RscSgg_Mvc_Module
{
  public function onInit()
  {
    parent::onInit();

    if (is_admin() && $this->getEnvironment()->isPluginPage()) {
      add_action('admin_enqueue_scripts', [$this, 'enqueueAssets'], 30);
    }
  }

  public function enqueueAssets()
  {
    if (!$this->getEnvironment()->isModule('galleries', 'showPresets')) {
      return;
    }
    $url = plugin_dir_url(__FILE__) . 'assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-wz-22';
    // The pictures of the goals and styles are the ones of the Style library
    $mock = plugin_dir_url(dirname(dirname(__FILE__)) . '/Styles/Module.php') . 'assets';
    wp_enqueue_media();
    wp_enqueue_style('sgg-style-mock', $mock . '/css/mock.css', [], $version);
    wp_enqueue_script('sgg-style-mock', $mock . '/js/mock.js', [], $version, true);
    wp_enqueue_style('sgg-wizard', $url . '/css/wizard.css', ['sgg-style-mock'], $version);
    wp_enqueue_script('sgg-wizard', $url . '/js/wizard.js', ['jquery', 'sgg-style-mock'], $version, true);
  }

  /**
   * What the wizard page needs: the goals and, for the live picture, the styles of each type in short.
   * @return array
   */
  public function viewData()
  {
    $styles = $this->getEnvironment()->getModule('styles');
    $open = $styles && method_exists($styles, 'proOpen') && $styles->proOpen();
    $byType = [];
    foreach (GridGallery_Styles_Library::TYPES as $type) {
      foreach (GridGallery_Styles_Library::forType($type) as $id => $style) {
        $byType[$type][] = [
          'id' => $id,
          'name' => $this->getEnvironment()->translate($style['name']),
          'collection' => $style['collection'] === 'Free' && $open ? 'Essentials' : $style['collection'],
          'pro' => $style['pro'],
          'locked' => $style['pro'] && !$open,
          'swatch' => $style['swatch'],
          'shape' => GridGallery_Styles_Library::shape($style['profile']),
        ];
      }
    }

    $env = $this->getEnvironment();
    $t = function ($text) use ($env) {
      return $env->translate($text);
    };

    return [
      'goals' => $this->goals(),
      'extras' => $this->extras(),
      'extraGroups' => $this->extraGroups(),
      'styles' => $byType,
      'collections' => array_map(function ($collection) use ($t) {
        return ['id' => $collection, 'name' => $t($collection)];
      }, array_merge([$open ? 'Essentials' : 'Free'], array_keys(GridGallery_Styles_Library::collections()))),
      'proOpen' => $open,
      'labels' => [
        'spacing' => $t('Spacing'), 'corners' => $t('Corners'), 'shadow' => $t('Shadow'), 'frame' => $t('Frame'),
        'arrows' => $t('Arrows'), 'pagination' => $t('Pagination'), 'captions' => $t('Captions'), 'hover' => $t('Caption on hover'),
        'none' => $t('none'), 'soft' => $t('soft'), 'strong' => $t('strong'), 'px' => $t('px'),
        'circle' => $t('round'), 'square' => $t('square'), 'minimal' => $t('minimal'), 'bar' => $t('bar'),
        'inside' => $t('inside'), 'outside' => $t('outside'),
        'bullets' => $t('dots'), 'dynamic' => $t('dynamic dots'), 'fraction' => $t('counter'), 'progressbar' => $t('progress bar'),
        'below' => $t('under the slide'), 'overlay' => $t('over the image'), 'left' => $t('left'), 'center' => $t('center'),
        'box' => $t('Caption box over the image'), 'paper' => $t('Photo frames'), 'ink' => $t('Title under the photo'),
        'title' => $t('Photo title'), 'heading' => $t('Your heading'), 'button' => $t('Learn more'), 'cart' => $t('Add to cart'),
        'more' => $t('Read more'), 'before' => $t('Before'), 'after' => $t('After'), 'post' => $t('Post title'),
        'lead_polaroid' => $t('A Polaroid gallery keeps its frames and spacing; the style colors the paper of the frames and the title under each photo. The picture uses your images.'),
        'lead_gallery' => $t('In a gallery a style sets the spacing between the images, the corners, shadow and frame of the thumbnails and the colors of the caption on hover. The picture uses your images.'),
        'lead_carousel' => $t('In a carousel a style sets the arrows, the pagination, the captions and the corners and shadow of the slides. The picture uses your images.'),
        'lead_slider' => $t('In a slider a style sets the arrows, the pagination, the caption box over the image and the corners and shadow of the slide. The picture uses your images.'),
        'noStyles' => $t('No styles in this collection.'),
        // Words of the live picture for the Additional step
        'all' => $t('All'), 'cat1' => $t('Nature'), 'cat2' => $t('City'), 'cat3' => $t('People'),
        'loadMore' => $t('Load more'), 'proPreview' => $t('Preview of a PRO option'),
        'lead_extras_gallery' => $t('Turn on what visitors get around the images. The picture shows every switch at once, in the style you chose.'),
        'lead_extras_carousel' => $t('Turn on the navigation, motion and click of the carousel. The picture shows every switch at once, in the style you chose.'),
        'lead_extras_slider' => $t('Turn on the navigation, motion and click of the slider. The picture shows every switch at once, in the style you chose.'),
      ],
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('supsystic-gallery'),
    ];
  }

  /**
   * Whether the PRO features of the classic gallery are here (the PRO plugin is on).
   * @return bool
   */
  private function classicPro()
  {
    return (bool) $this->getEnvironment()->isPro();
  }

  /**
   * Whether a slide source is open in this version.
   * @param string $source
   * @return bool
   */
  private function sourceOpen($source)
  {
    $carousel = $this->getEnvironment()->getModule('carousel');

    return !$carousel || !method_exists($carousel, 'lockedSources') || !in_array($source, $carousel->lockedSources(), true);
  }

  /**
   * The headings the options of the Additional step sit under.
   * @return array id => name
   */
  public function extraGroups()
  {
    $env = $this->getEnvironment();

    return [
      'page' => $env->translate('On the page'),
      'nav' => $env->translate('Navigation'),
      'motion' => $env->translate('Motion'),
      'look' => $env->translate('Look'),
      'click' => $env->translate('On click'),
    ];
  }

  /**
   * The options of the Additional step, by type: switches the new gallery gets, each one drawn at once on the live
   * picture. pro: a PRO option; locked: PRO and closed in this version (a click opens its PRO window, upsell);
   * off: options it turns off (they cannot work together); needs: an option it turns on.
   * Which options a goal shows, and how they start, is in goals() (extras).
   * @return array type => id => option
   */
  public function extras()
  {
    $env = $this->getEnvironment();
    $t = function ($text) use ($env) {
      return $env->translate($text);
    };
    $classicPro = $this->classicPro();
    $lightbox = $env->getModule('lightbox');
    $lightboxOpen = $lightbox && method_exists($lightbox, 'proOpen') && $lightbox->proOpen();
    $carousel = $env->getModule('carousel');
    $effectsOpen = $carousel && method_exists($carousel, 'lockedEffects') && !$carousel->lockedEffects();
    $verticalOpen = $carousel && method_exists($carousel, 'lockedFeatures') && !in_array('vertical', $carousel->lockedFeatures(), true);
    $slider = $env->getModule('slider');
    $animationsOpen = $slider && method_exists($slider, 'layerAnimations') && $slider->layerAnimations();

    $lightboxOn = ['group' => 'click', 'icon' => 'fa-expand', 'name' => $t('Open in a lightbox'), 'note' => $t('A click shows the image large, with arrows, zoom and full screen.')];
    $lightboxTools = ['group' => 'click', 'icon' => 'fa-film', 'name' => $t('Lightbox thumbnails and slideshow'), 'note' => $t('A strip of thumbnails and a slideshow with a progress line in the lightbox.'), 'pro' => true, 'open' => $lightboxOpen, 'upsell' => 'lightbox', 'needs' => 'lightbox'];
    $arrows = ['group' => 'nav', 'icon' => 'fa-angle-right', 'name' => $t('Arrows'), 'note' => $t('Previous and next buttons, in the shape of the style.')];
    $dots = ['group' => 'nav', 'icon' => 'fa-ellipsis-h', 'name' => $t('Pagination'), 'note' => $t('Dots, a counter or a progress bar, as the style draws them.')];
    $vertical = ['group' => 'nav', 'icon' => 'fa-arrows-v', 'name' => $t('Vertical direction'), 'note' => $t('The slides move up and down instead of sideways.'), 'pro' => true, 'open' => $verticalOpen, 'upsell' => 'effects'];
    $autoplay = ['group' => 'motion', 'icon' => 'fa-play-circle', 'name' => $t('Autoplay'), 'note' => $t('Moves on by itself and stops while the mouse is over it.')];

    $list = [
      'gallery' => [
        // Captions of a classic gallery are PRO (the Captions row of its settings is closed in the free version)
        'captions' => ['group' => 'page', 'icon' => 'fa-font', 'name' => $t('Captions on hover'), 'note' => $t('The title slides in at the bottom of an image.'), 'pro' => true, 'open' => $classicPro, 'upsell' => 'useCaptions'],
        'hscroll' => ['group' => 'page', 'icon' => 'fa-arrows-h', 'name' => $t('Horizontal scroll'), 'note' => $t('All images in one row that scrolls sideways.')],
        'categories' => ['group' => 'page', 'icon' => 'fa-filter', 'name' => $t('Category filter'), 'note' => $t('Buttons above the grid. Three sample categories go to your images, rename them on the Images page.'), 'pro' => true, 'open' => $classicPro, 'upsell' => 'useCats'],
        'pagination' => ['group' => 'page', 'icon' => 'fa-list-ol', 'name' => $t('Pages'), 'note' => $t('Numbered page buttons under the grid.'), 'pro' => true, 'open' => $classicPro, 'upsell' => 'usePages', 'off' => ['loadmore', 'hscroll']],
        'loadmore' => ['group' => 'page', 'icon' => 'fa-plus-circle', 'name' => $t('Load more button'), 'note' => $t('The first images, and the rest on a click.'), 'pro' => true, 'open' => $classicPro, 'upsell' => 'show-more', 'off' => ['pagination', 'hscroll']],
        'lightbox' => $lightboxOn,
        'lightboxPro' => $lightboxTools,
      ],
      'carousel' => [
        'arrows' => $arrows,
        'dots' => $dots,
        'vertical' => $vertical + ['off' => ['marquee', 'effect3d']],
        'autoplay' => $autoplay,
        'marquee' => ['group' => 'motion', 'icon' => 'fa-exchange', 'name' => $t('Running line'), 'note' => $t('The slides move on without stops, like a ticker.'), 'off' => ['effect3d', 'vertical']],
        'effect3d' => ['group' => 'motion', 'icon' => 'fa-cube', 'name' => $t('3D Coverflow effect'), 'note' => $t('The side slides turn away in depth.'), 'pro' => true, 'open' => $effectsOpen, 'upsell' => 'effects', 'off' => ['marquee', 'vertical']],
        'captions' => ['group' => 'look', 'icon' => 'fa-font', 'name' => $t('Captions'), 'note' => $t('The title of each image, as the style places it.')],
        'grayscale' => ['group' => 'look', 'icon' => 'fa-adjust', 'name' => $t('Black and white'), 'note' => $t('Images turn to color when the mouse is over them.')],
        'lightbox' => $lightboxOn,
        'lightboxPro' => $lightboxTools,
      ],
      'slider' => [
        'arrows' => $arrows,
        'dots' => $dots,
        'thumbs' => ['group' => 'nav', 'icon' => 'fa-th-large', 'name' => $t('Thumbnails'), 'note' => $t('A strip of small images under the slider to pick a slide.')],
        'vertical' => $vertical + ['off' => ['effect3d']],
        'autoplay' => $autoplay,
        'fade' => ['group' => 'motion', 'icon' => 'fa-clone', 'name' => $t('Fade between slides'), 'note' => $t('The next slide fades in over the one before.'), 'off' => ['effect3d']],
        'effect3d' => ['group' => 'motion', 'icon' => 'fa-cube', 'name' => $t('3D Cube effect'), 'note' => $t('Slides turn like the sides of a cube.'), 'pro' => true, 'open' => $effectsOpen, 'upsell' => 'effects', 'off' => ['fade', 'vertical']],
        'animations' => ['group' => 'motion', 'icon' => 'fa-magic', 'name' => $t('Animated text'), 'note' => $t('The heading, the line and the button come in one after another.'), 'pro' => true, 'open' => $animationsOpen, 'upsell' => 'animations'],
        'captions' => ['group' => 'look', 'icon' => 'fa-font', 'name' => $t('Caption over the slide'), 'note' => $t('The title and text of each image in the box of the style.')],
        'lightbox' => $lightboxOn,
        'lightboxPro' => $lightboxTools,
      ],
    ];
    foreach ($list as $type => $options) {
      foreach ($options as $id => $option) {
        $option += ['pro' => false, 'open' => true, 'upsell' => '', 'off' => [], 'needs' => ''];
        $option['locked'] = $option['pro'] && !$option['open'];
        unset($option['open']);
        $list[$type][$id] = $option;
      }
    }

    return $list;
  }

  /**
   * The options of the Additional step a goal shows, each with the value it starts with (from the settings of the
   * goal and the defaults of its type).
   * @param string $id Goal
   * @param array $goal
   * @return array option id => bool
   */
  private function extrasOf($id, array $goal)
  {
    $values = isset($goal['values']) ? $goal['values'] : [];
    $value = function ($path, $default) use ($values) {
      return array_key_exists($path, $values) ? $values[$path] : $default;
    };
    $device = function ($path, $default) use ($value) {
      $set = $value($path, null);

      return is_array($set) ? !empty($set['d']) : $default;
    };
    $slider = $goal['kind'] === 'slider';
    $start = [
      'arrows' => $device('nav.arrows', true),
      'dots' => $device('nav.dots', true),
      'thumbs' => $device('nav.thumbs', false),
      'autoplay' => (bool) $value('motion.autoplay', $slider),
      'marquee' => (bool) $value('motion.marquee', 0),
      'fade' => $value('motion.effect', 'slide') === 'fade',
      'grayscale' => (bool) $value('style.grayscale', 0),
      'captions' => $goal['kind'] === 'gallery' ? !empty($goal['strip']) : $value('captions.mode', 'auto') !== 'none',
      'lightbox' => $value('click.action', 'lightbox') === 'lightbox',
      'categories' => $id === 'categories',
      'pagination' => $id === 'postfeed',
    ];
    $shown = [
      'grid' => ['captions', 'hscroll', 'categories', 'pagination', 'loadmore', 'lightbox', 'lightboxPro'],
      'justified' => ['captions', 'categories', 'pagination', 'loadmore', 'lightbox', 'lightboxPro'],
      'masonry' => ['captions', 'categories', 'pagination', 'loadmore', 'lightbox', 'lightboxPro'],
      'rounded' => ['captions', 'hscroll', 'categories', 'pagination', 'loadmore', 'lightbox', 'lightboxPro'],
      'categories' => ['hscroll', 'categories', 'pagination', 'loadmore', 'lightbox', 'lightboxPro'],
      'postfeed' => ['pagination', 'loadmore'],
      // Mosaic is a fixed 2+3 table with a "+N" tile for the rest: it has no pages and no Load more (its settings hide
      // both), and a category filter hides cells inside the table, which breaks its rows (the tile heights collapse)
      'mosaic' => ['captions', 'lightbox', 'lightboxPro'],
      // The title under the photo is part of the Polaroid look, so it has no switch of its own
      'polaroid' => ['categories', 'pagination', 'loadmore', 'lightbox', 'lightboxPro'],
      'carousel' => ['arrows', 'dots', 'vertical', 'autoplay', 'marquee', 'effect3d', 'captions', 'grayscale', 'lightbox', 'lightboxPro'],
      'logos' => ['arrows', 'dots', 'marquee', 'grayscale'],
      'products' => ['arrows', 'dots', 'vertical', 'autoplay', 'marquee', 'effect3d'],
      'posts_carousel' => ['arrows', 'dots', 'vertical', 'autoplay', 'effect3d', 'captions'],
      'slider' => ['arrows', 'dots', 'thumbs', 'vertical', 'autoplay', 'fade', 'effect3d', 'captions', 'lightbox', 'lightboxPro'],
      'hero' => ['arrows', 'dots', 'vertical', 'autoplay', 'fade', 'effect3d', 'animations'],
      'compare' => ['arrows', 'dots'],
      'viewer' => ['arrows', 'dots', 'thumbs', 'autoplay', 'fade', 'effect3d', 'lightbox', 'lightboxPro'],
      'posts_slider' => ['arrows', 'dots', 'vertical', 'autoplay', 'fade', 'effect3d'],
    ];
    $extras = [];
    foreach (isset($shown[$id]) ? $shown[$id] : [] as $option) {
      $extras[$option] = isset($start[$option]) ? $start[$option] : false;
    }

    return $extras;
  }

  /**
   * The options the wizard sent for a goal, checked: only the options of the goal, PRO ones only where they are open,
   * the ones that cannot work together left out, and the ones they need turned on.
   * @param array $goal
   * @param mixed $sent option id => 1|0
   * @return array option id => bool
   */
  private function chosenExtras(array $goal, $sent)
  {
    $catalog = $this->extras();
    $options = isset($catalog[$goal['kind']]) ? $catalog[$goal['kind']] : [];
    $sent = is_array($sent) ? $sent : [];
    $chosen = [];
    foreach ($goal['extras'] as $id => $start) {
      $on = array_key_exists($id, $sent) ? !empty($sent[$id]) && $sent[$id] !== '0' && $sent[$id] !== 'false' : $start;
      $chosen[$id] = $on && isset($options[$id]) && !$options[$id]['locked'];
    }
    // Of two options that cannot work together the one listed first stays (the wizard never sends both)
    $kept = [];
    foreach ($chosen as $id => $on) {
      if ($on && !array_intersect($this->conflicts($options, $id), $kept)) {
        $kept[] = $id;
      }
      $chosen[$id] = in_array($id, $kept, true);
    }
    foreach ($chosen as $id => $on) {
      $needs = $options[$id]['needs'];
      if ($on && $needs !== '' && array_key_exists($needs, $chosen)) {
        $chosen[$needs] = true;
      }
    }

    return $chosen;
  }

  /**
   * The options that cannot work together with one: the ones it turns off and the ones that turn it off.
   * @param array $options Options of a type
   * @param string $id
   * @return string[]
   */
  private function conflicts(array $options, $id)
  {
    $list = isset($options[$id]) ? $options[$id]['off'] : [];
    foreach ($options as $other => $option) {
      if (in_array($id, $option['off'], true)) {
        $list[] = $other;
      }
    }

    return array_values(array_unique($list));
  }

  /**
   * What a gallery can be made for. kind: the type; preset: the template of a classic gallery; values: settings of
   * the goal (paths); images: whether the gallery shows images of its own. best, tags and hint explain the goal in the
   * wizard; min is how many images make it look right; pairs: the images come in Before / After pairs.
   * @return array
   */
  public function goals()
  {
    $env = $this->getEnvironment();
    $t = function ($text) use ($env) {
      return $env->translate($text);
    };
    $classicPro = $this->classicPro();
    $postsOpen = $this->sourceOpen('posts');
    $slider = $this->getEnvironment()->getModule('slider');
    $compareOpen = $slider && method_exists($slider, 'slideCompare') && $slider->slideCompare();
    $goals = [
      'grid' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 1, 'name' => $t('Photo grid'), 'text' => $t('Even tiles in neat rows, a lightbox on click.'), 'style' => 'free_clean',
        // Captions of a classic gallery are PRO, so the free version does not promise them on the card
        'best' => $t('Portfolios, events, products'), 'tags' => [$t('Lightbox'), $classicPro ? $t('Captions on hover') : $t('Even rows')], 'min' => 6,
        'hint' => $t('Six or more images fill the rows. Photos of one shape (all square or all 4:3) keep the grid even.')],
      'justified' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 4, 'name' => $t('Justified rows'), 'text' => $t('Rows that fill the width, every photo in its own proportions.'), 'style' => 'free_minimal',
        'best' => $t('Travel, weddings, photo stories'), 'tags' => [$t('No cropping'), $t('Lightbox')], 'min' => 6,
        'values' => ['area.justified' => '1', 'area.photo_height' => '240'],
        'hint' => $t('Mix landscape and portrait photos: every row adapts to them and nothing is cropped.')],
      'masonry' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 2, 'name' => $t('Masonry'), 'text' => $t('Columns of photos of any height, like a mood board.'), 'style' => 'free_soft',
        'best' => $t('Mood boards, art, interiors'), 'tags' => [$t('Any height'), $t('Lightbox')], 'min' => 6,
        'hint' => $t('Photos of different heights make the columns lively. Tall and wide pictures both work.')],
      'rounded' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 3, 'name' => $t('Round thumbnails'), 'text' => $t('Circles in a grid, good for teams and avatars.'), 'style' => 'free_clean',
        'best' => $t('Teams, speakers, testimonials'), 'tags' => [$t('Circle crop'), $classicPro ? $t('Captions') : $t('Lightbox')], 'min' => 3,
        'values' => ['thumbnail.border.radius' => '100', 'thumbnail.border.radius_unit' => '1'],
        'hint' => $classicPro
          ? $t('Portraits with the face in the middle crop best into circles. Add names as captions later.')
          : $t('Portraits with the face in the middle crop best into circles.')],
      'categories' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 5, 'name' => $t('Categories and icons'), 'text' => $t('Filter buttons above the grid and icons over the images.'), 'style' => 'free_clean', 'pro' => true, 'locked' => !$classicPro, 'upsell' => 'useCats',
        'best' => $t('Portfolios with several projects'), 'tags' => [$t('Filter buttons'), $t('Icons')], 'min' => 6,
        'hint' => $t('Three sample categories go to your images. Rename them or give your own on the Images page.')],
      'postfeed' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 6, 'name' => $t('Post feed'), 'text' => $t('Your posts as tiles with pages.'), 'style' => 'free_clean', 'pro' => true, 'locked' => !$classicPro, 'upsell' => 'sgg-posts-layout-enable', 'noImages' => true,
        'best' => $t('Blogs and news'), 'tags' => [$t('Posts and pages'), $t('Pagination')]],
      'mosaic' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 9, 'name' => $t('Mosaic'), 'text' => $t('Big and small tiles in one magazine-style grid.'), 'style' => 'free_clean', 'pro' => true, 'locked' => !$classicPro, 'upsell' => 'mosaic',
        'best' => $t('Magazines, featured work'), 'tags' => [$t('Big and small tiles'), $t('Lightbox')], 'min' => 5,
        'hint' => $t('Put your best photo first: it gets the big tile.')],
      // Polaroid: the fixed grid in instant-photo frames with the title under each photo. Its look is the caption
      // effect "Polaroid" of the classic captions (the Caption Builder turns that effect off) with the icons on: they
      // write the title of an image under it when the image has no caption of its own (tooltip off), and show the
      // magnifier over the photo on hover. Captions and icons are PRO, so the goal is PRO; create() colors the frames,
      // the title and the icons from the style.
      'polaroid' => ['group' => 'gallery', 'kind' => 'gallery', 'preset' => 1, 'name' => $t('Polaroid'), 'text' => $t('Photos in instant-photo frames with the title under each.'), 'style' => 'free_clean', 'pro' => true, 'locked' => !$classicPro, 'upsell' => 'useCaptions',
        'best' => $t('Memories, events, travel, family'), 'tags' => [$t('Photo frames'), $t('Title under the photo')], 'min' => 6,
        'values' => [
          'area.grid' => '0', 'area.photo_width' => '240', 'area.photo_width_unit' => '0', 'area.photo_height' => '240', 'area.photo_height_unit' => '0',
          'area.distance' => '30',
          'thumbnail.border.type' => 'none', 'thumbnail.border.width' => '0', 'thumbnail.border.radius' => '0', 'thumbnail.border.radius_unit' => '0',
          'use_shadow' => '1', 'mouse_shadow' => '0', 'thumbnail.shadow.color' => '#0f172a', 'thumbnail.shadow.blur' => '16', 'thumbnail.shadow.x' => '0', 'thumbnail.shadow.y' => '6',
          'thumbnail.overlay.enabled' => 'true', 'thumbnail.overlay.effect' => 'polaroid', 'thumbnail.overlay.personal' => 'false',
          'thumbnail.overlay.polaroidFrameWidth' => '12', 'thumbnail.overlay.polaroidScattering' => 'false', // straight; Scattering in the settings turns them
          'thumbnail.overlay.polaroidAnimation' => 'true',
          'thumbnail.overlay.polaroidAlwaysShowCaption' => 'true', 'thumbnail.overlay.polaroidCaptionHeight' => '56', 'thumbnail.overlay.transparency' => '0',
          'thumbnail.overlay.text_size' => '15', 'thumbnail.overlay.text_size_unit' => '0', 'thumbnail.overlay.fontStyle' => ['bold'],
          'thumbnail.overlay.text_align' => 'center', 'thumbnail.overlay.position' => 'middle', 'thumbnail.tooltip' => 'false',
          'icons.enabled' => 'true', 'icons.overlay_enabled' => 'false', 'icons.size' => '18', 'icons.margin' => '5', 'icons.bgTransparency' => '0',
          'icons.effect' => 'hi-icon-effect-5d',
          'captionBuilder.enabled' => '0',
        ],
        'hint' => $t('Give your images titles: each title is written under its photo. Square photos fill the frames best.')],

      'carousel' => ['group' => 'carousel', 'kind' => 'carousel', 'name' => $t('Image carousel'), 'text' => $t('Several images in a row that slide, sized for every device.'), 'style' => 'free_clean',
        'best' => $t('Home pages, product shots'), 'tags' => [$t('Arrows and dots'), $t('Touch swipe')], 'min' => 4,
        'hint' => $t('Four or more images, so there is always something to slide to.')],
      'logos' => ['group' => 'carousel', 'kind' => 'carousel', 'name' => $t('Logo marquee'), 'text' => $t('Logos of clients and partners moving without stops.'), 'style' => 'free_minimal',
        'best' => $t('Clients, partners, sponsors'), 'tags' => [$t('Non-stop motion'), $t('Grayscale')], 'min' => 5,
        'values' => [
          'motion.marquee' => 1, 'style.grayscale' => 1, 'layout.fit' => 'contain', 'layout.ratio' => '3:2',
          'responsive.perView' => ['d' => 5, 't' => 4, 'm' => 2], 'captions.mode' => 'none', 'click.action' => 'none',
          'nav.arrows' => ['d' => 0, 't' => 0, 'm' => 0], 'nav.dots' => ['d' => 0, 't' => 0, 'm' => 0],
        ],
        'hint' => $t('Logos of a similar size with a transparent background (PNG or SVG) line up best.')],
      'posts_carousel' => ['group' => 'carousel', 'kind' => 'carousel', 'name' => $t('Posts carousel'), 'text' => $t('Your latest posts with image, title and date.'), 'style' => 'free_clean', 'pro' => true, 'locked' => !$postsOpen, 'upsell' => 'posts', 'noImages' => true,
        'best' => $t('Latest articles on the home page'), 'tags' => [$t('Featured image'), $t('Date')],
        'values' => ['content.source' => 'posts', 'captions.text' => 1]],

      'slider' => ['group' => 'slider', 'kind' => 'slider', 'name' => $t('Image slider'), 'text' => $t('One large slide at a time with captions over it.'), 'style' => 'free_clean',
        'best' => $t('Showcases, portfolios'), 'tags' => [$t('Captions'), $t('Autoplay')], 'min' => 2,
        'hint' => $t('Wide images (1600 px or more) stay sharp on large screens.')],
      'hero' => ['group' => 'slider', 'kind' => 'slider', 'name' => $t('Hero slider'), 'text' => $t('A full-height banner for headings and buttons on each slide.'), 'style' => 'free_dark',
        'best' => $t('The top of a landing page'), 'tags' => [$t('Full height'), $t('Text layers')], 'min' => 1,
        // A banner: its button leads on, a click elsewhere on the photo does nothing (it opened the photo in the lightbox)
        'values' => ['layout.heightMode' => 'viewport', 'layout.vh' => 80, 'motion.effect' => 'fade', 'captions.mode' => 'none', 'click.action' => 'none'],
        'hint' => $t('Very wide images (1920 px or more) with a calm area for the heading. Add headings and buttons in the slide editor.')],
      'compare' => ['group' => 'slider', 'kind' => 'slider', 'name' => $t('Before / After'), 'text' => $t('Two images under a line that visitors drag to compare.'), 'style' => 'free_minimal', 'pro' => true, 'locked' => !$compareOpen, 'upsell' => 'compare',
        'best' => $t('Renovations, retouching, makeovers'), 'tags' => [$t('Drag handle'), $t('Labels')], 'min' => 2, 'pairs' => true,
        'values' => ['motion.autoplay' => 0, 'motion.loop' => 0, 'click.action' => 'none', 'captions.mode' => 'none'],
        'hint' => $t('Pick images in pairs of the same size: the first is Before, the second is After. Each pair becomes one slide.')],
      'posts_slider' => ['group' => 'slider', 'kind' => 'slider', 'name' => $t('Posts slider'), 'text' => $t('Featured posts as big slides with a link to read on.'), 'style' => 'free_dark', 'pro' => true, 'locked' => !$postsOpen, 'upsell' => 'posts', 'noImages' => true,
        'best' => $t('Featured stories'), 'tags' => [$t('Featured image'), $t('Read more')],
        'values' => ['content.source' => 'posts', 'captions.text' => 1]],
    ];
    // The product carousel is always there; without WooCommerce it is a grey card that asks to install or turn it on
    $woo = function_exists('wc_get_product');
    $products = ['group' => 'carousel', 'kind' => 'carousel', 'name' => $t('Product carousel'), 'text' => $t('WooCommerce products with price and Add to cart.'), 'style' => 'free_soft', 'noImages' => true,
      'best' => $t('Shops, featured products'), 'tags' => [$t('Price'), $t('Add to cart')],
      'values' => ['content.source' => 'products']];
    if (!$woo) {
      $installed = file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
      $products['off'] = $installed ? $t('Activate WooCommerce') : $t('Install WooCommerce');
      $products['offUrl'] = current_user_can($installed ? 'activate_plugins' : 'install_plugins')
        ? ($installed ? admin_url('plugins.php?s=woocommerce') : admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'))
        : '';
    }
    $after = array_search('logos', array_keys($goals), true) + 1;
    $goals = array_slice($goals, 0, $after, true) + ['products' => $products] + array_slice($goals, $after, null, true);
    // Image viewer: one large photo and a strip of thumbnails under it (the Slider with its thumbnails)
    $goals = array_slice($goals, 0, -2, true) + ['viewer' => ['group' => 'slider', 'kind' => 'slider', 'name' => $t('Image viewer'), 'text' => $t('One large photo and a strip of thumbnails to pick the next one.'), 'style' => 'free_clean',
      'best' => $t('Product photos, real estate, portfolios'), 'tags' => [$t('Thumbnails'), $t('Lightbox')], 'min' => 3,
      'values' => [
        'layout.heightMode' => 'ratio', 'layout.ratio' => '3:2', 'layout.fit' => 'contain',
        'motion.effect' => 'fade', 'motion.autoplay' => 0, 'motion.loop' => 0,
        'nav.thumbs' => ['d' => 1, 't' => 1, 'm' => 1], 'nav.thumbHeight' => 80,
        'nav.dots' => ['d' => 0, 't' => 0, 'm' => 0], 'captions.mode' => 'below', 'captions.text' => 0, 'click.action' => 'lightbox',
      ],
      'hint' => $t('Photos of one subject from several sides work best. The whole photo is shown, nothing is cropped.')]]
      + array_slice($goals, -2, null, true);
    // The caption of the wizard's picture: a strip at the bottom of the image with the title, shown on hover, in
    // the colors of the style. In PRO it is the title row of the Caption Builder without its full cover (the
    // Caption Builder falls back to the title when an image has no caption). Captions are PRO in a classic gallery:
    // the free version keeps the switch of the Additional step closed and off, and galleryExtras() turns the caption
    // off. Colors and alignment come from the style.
    $hoverStrip = [
      'captionBuilder.enabled' => '1',
      'captionBuilder.title.enable' => '1',
      'captionBuilder.title.vAlign' => 'middle',
      'captionBuilder.title.height' => '40px',
      'captionBuilder.title.inset' => '12',
      'captionBuilder.description.enable' => '0',
      'captionBuilder.icons.enable' => '0',
      'thumbnail.overlay.enabled' => 'true',
      'thumbnail.overlay.effect' => 'quarter-slide-up',
      'thumbnail.overlay.position' => 'bottom',
      'thumbnail.overlay.text_size' => '14',
      'thumbnail.overlay.text_size_unit' => '0',
      'thumbnail.overlay.fontStyle' => ['bold'],
      'icons.enabled' => 'false',
    ];
    if ($classicPro) {
      // The Caption Builder reveals on hover only with its cover on: the cover stays clear, only the strip shows
      $hoverStrip['captionBuilder.background.enable'] = '1';
      $hoverStrip['thumbnail.overlay.transparency'] = '10';
    } else {
      // The classic caption is the strip itself (tens of percent of transparency)
      $hoverStrip['captionBuilder.background.enable'] = '0';
      $hoverStrip['thumbnail.overlay.transparency'] = '1';
    }
    foreach (['grid', 'justified', 'masonry', 'rounded', 'mosaic'] as $id) {
      $goals[$id]['values'] = (isset($goals[$id]['values']) ? $goals[$id]['values'] : []) + $hoverStrip;
      $goals[$id]['strip'] = true;
    }
    // Galleries made here open their images in the new lightbox, in the colors of their style
    foreach (['grid', 'justified', 'masonry', 'rounded', 'categories', 'mosaic', 'polaroid'] as $id) {
      $goals[$id]['values'] = (isset($goals[$id]['values']) ? $goals[$id]['values'] : []) + [
        'box.enabled' => 'true',
        'box.type' => '3',
        'box.theme' => 'modern',
        'lightbox.theme' => 'style',
        'lightbox.colors' => 'style',
      ];
    }
    $catalog = $this->extras();
    foreach ($goals as $id => $goal) {
      $goals[$id] += ['pro' => false, 'locked' => false, 'off' => '', 'offUrl' => '', 'values' => [], 'noImages' => false, 'preset' => 1, 'upsell' => '', 'tags' => [], 'best' => '', 'hint' => '', 'min' => 0, 'pairs' => false, 'strip' => false];
      // The options of the Additional step and how they start; a PRO option that is closed starts off
      $extras = $this->extrasOf($id, $goals[$id]);
      foreach ($extras as $option => $on) {
        if (!empty($catalog[$goal['kind']][$option]['locked'])) {
          $extras[$option] = false;
        }
      }
      $goals[$id]['extras'] = $extras;
    }

    return $goals;
  }

  /**
   * Creates the gallery of a goal: the record with its template, the type, the settings of the goal and the style,
   * then the images.
   * @param RscSgg_Http_Request $request Has title, goal, style and images
   * @return array id and editor url
   * @throws RuntimeException
   */
  public function create(RscSgg_Http_Request $request)
  {
    $environment = $this->getEnvironment();
    $goals = $this->goals();
    $goalId = (string) $request->post->get('goal');
    if (!isset($goals[$goalId])) {
      throw new RuntimeException($environment->translate('Choose what the gallery is for.'));
    }
    $goal = $goals[$goalId];
    if ($goal['locked']) {
      throw new RuntimeException($environment->translate('This kind of gallery is in Photo Gallery PRO.'));
    }
    if ($goal['off'] !== '') {
      throw new RuntimeException($environment->translate('Turn on WooCommerce to make a product carousel.'));
    }

    // The record and the template of a classic gallery, read by createFromRequest() from the request
    // set() changes only a key that is there, add() only a new one
    if ($request->post->has('preset')) {
      $request->post->set('preset', (int) $goal['preset']);
    } else {
      $request->post->add('preset', (int) $goal['preset']);
    }
    $galleriesModule = $environment->getModule('galleries');
    $galleries = $galleriesModule->getModel('galleries');
    $galleries->createFromRequest($request, $environment->getLang(), $environment->getConfig());
    $galleryId = (int) $galleries->getInsertId();
    if ($galleryId < 1) {
      throw new RuntimeException($environment->translate('The gallery could not be created.'));
    }

    // A style the version has; a locked PRO style falls back to the style of the goal
    $type = $goal['kind'];
    $styles = $environment->getModule('styles');
    $styleId = (string) $request->post->get('style');
    $style = GridGallery_Styles_Library::get($type, $styleId);
    if (!$style || ($style['pro'] && !($styles && $styles->proOpen()))) {
      $styleId = $goal['style'];
      $style = GridGallery_Styles_Library::get($type, $styleId);
    }
    $values = ($style ? $style['values'] : []) + [];
    foreach ($goal['values'] as $path => $value) {
      $values[$path] = $value;
    }
    // The caption strip sits in the bottom row, aligned like the captions of the style; in a circle it is centered
    if ($goal['strip'] && $style) {
      $align = $goalId === 'rounded' ? 'center' : GridGallery_Styles_Library::shape($style['profile'])['align'];
      $values['captionBuilder.title.position'] = $align . '_bottom';
      $values['captionBuilder.title.hAlign'] = $align;
      // In a circle a 40 px strip at the very bottom is narrower than the title: the curve cut its ends off. A strip
      // of a third of the circle with more side room keeps the title inside (it wraps if it is long)
      if ($goalId === 'rounded') {
        $values['captionBuilder.title.height'] = '33%';
        $values['captionBuilder.title.inset'] = '18';
      }
    }
    // Polaroid: the frames and the title row in the paper and ink of the style (white and dark in the light styles);
    // the rest of its look stays as the goal sets it, whatever the style
    if ($goalId === 'polaroid') {
      $values['thumbnail.overlay.background'] = $style ? $style['swatch']['surface'] : '#ffffff';
      $values['thumbnail.overlay.foreground'] = $style ? $style['swatch']['text'] : '#1f2937';
      // The magnifier on the photo: a round button in the accent of the style
      $accent = $style ? $style['swatch']['accent'] : '#0f766e';
      $lightbox = $environment->getModule('lightbox');
      $values['icons.background'] = $accent;
      $values['icons.background_hover'] = $accent;
      $values['icons.color'] = $lightbox && method_exists($lightbox, 'readableOn') ? $lightbox->readableOn($accent) : '#ffffff';
      $values['icons.hover_color'] = $values['icons.color'];
    }
    // The switches of the Additional step
    $extras = $this->chosenExtras($goal, $request->post->get('extras'));

    // Before / After: the images come in pairs, the first of each pair is the slide, the second its After image
    $images = array_values(array_filter(array_map('intval', (array) $request->post->get('images'))));
    $after = [];
    if ($goal['pairs']) {
      $slides = [];
      for ($i = 0; $i < count($images); $i += 2) {
        $slides[] = $images[$i];
        if (isset($images[$i + 1])) {
          $after[$images[$i]] = $images[$i + 1];
        }
      }
      $images = $slides;
    }
    $photoIds = $goal['noImages'] ? [] : $this->attachImages($galleryId, $images);

    $settingsModel = new GridGallery_Galleries_Model_Settings();
    if ($type === 'gallery') {
      $values = $this->galleryExtras($values, $extras, $style, count($photoIds));
      if (!empty($extras['categories'])) {
        $this->sampleCategories(array_values($photoIds));
      }
      if ($goalId === 'postfeed') {
        $this->samplePosts($galleryId);
      }
      $stored = $settingsModel->get($galleryId);
      $data = $stored && is_array($stored->data) ? $stored->data : [];
      foreach ($values as $path => $value) {
        $data = $this->setPath($data, $path, $value);
      }
      $settingsModel->save($galleryId, $data);
    } else {
      $typeModule = $environment->getModule($type);
      if (!$typeModule instanceof GridGallery_Carousel_Module) {
        throw new RuntimeException($environment->translate('The gallery could not be created.'));
      }
      $typeModule->createType($galleryId);
      $input = $typeModule->getDefaults();
      foreach ($values as $path => $value) {
        $input = $this->setPath($input, $path, $value);
      }
      $input = $this->typeExtras($input, $extras, $type);
      $input['style']['theme'] = $styleId;
      if ($goalId === 'hero' && $photoIds) {
        $input['layers'] = $this->heroLayers(array_values($photoIds), (int) $typeModule->layerLimit(), !empty($extras['animations']));
      }
      if ($after) {
        $input['slides'] = [];
        foreach ($after as $beforeId => $afterId) {
          if (isset($photoIds[$beforeId])) {
            $input['slides'][$photoIds[$beforeId]] = ['compare' => $afterId, 'before' => $environment->translate('Before'), 'after' => $environment->translate('After'), 'start' => 50];
          }
        }
      }
      $typeModule->saveSettings($galleryId, $input);
    }
    if (method_exists($galleriesModule, 'cleanCache')) {
      $galleriesModule->cleanCache($galleryId);
    }

    return [
      'id' => $galleryId,
      'url' => $environment->generateUrl('galleries', 'settings', ['gallery_id' => $galleryId]),
    ];
  }

  /**
   * The text of a hero slide, centred: a heading, a short line and a button. Their colors come from the style (the
   * caption color, the accent), so another style recolors them. The user writes the real text in the slide editor.
   * With two layers (the free version) the slide gets the heading and the button.
   * With animations (PRO) the layers come in from below one after another.
   * @param int[] $photoIds
   * @param int $limit Layers a slide shows in this version
   * @param bool $animate
   * @return array photo id => layers
   */
  private function heroLayers(array $photoIds, $limit, $animate = false)
  {
    $env = $this->getEnvironment();
    $short = $limit < 3;
    $layers = [];
    // A phone gets values of its own: following the tablet (text x 0.8 x 0.85) a 52 px heading stays 36 px, and on
    // a low slider (21:9 is about 170 px high on a phone) the heading, the line and the button run into each other.
    // The places leave room for a heading and a line of two rows each, from about 170 px of height up to a full screen.
    foreach (array_slice($photoIds, 0, 50) as $photoId) {
      $slide = [
        ['type' => 'heading', 'text' => $env->translate('Your heading'), 'weight' => 800, 'align' => 'center', 'shadow' => 1,
          'd' => ['x' => 50, 'y' => $short ? 42 : 38, 'w' => 70, 'size' => 52],
          'm' => ['x' => 50, 'y' => $short ? 28 : 16, 'w' => 92, 'size' => 22]],
      ];
      if (!$short) {
        $slide[] = ['type' => 'text', 'text' => $env->translate('A short line about this slide'), 'align' => 'center', 'shadow' => 1,
          'd' => ['x' => 50, 'y' => 56, 'w' => 60, 'size' => 20],
          'm' => ['x' => 50, 'y' => 56, 'w' => 90, 'size' => 14]];
      }
      $slide[] = ['type' => 'button', 'text' => $env->translate('Learn more'), 'bg' => 'auto', 'align' => 'center', 'radius' => 10,
        'd' => ['x' => 50, 'y' => $short ? 62 : 72, 'w' => 0, 'size' => 17],
        'm' => ['x' => 50, 'y' => $short ? 78 : 86, 'w' => 0, 'size' => 13]];
      if ($animate) {
        foreach ($slide as $i => $layer) {
          $slide[$i]['anim'] = 'up';
          $slide[$i]['delay'] = $i * 250;
        }
      }
      $layers[$photoId] = $slide;
    }

    return $layers;
  }

  /**
   * The settings of a classic gallery for the switches of the Additional step. Only the options the goal showed are
   * written; the buttons of the category filter, the pages and Load more get the colors of the style.
   * @param array $values path => value
   * @param array $extras option id => bool (checked)
   * @param array|null $style
   * @param int $count Images of the gallery
   * @return array
   */
  private function galleryExtras(array $values, array $extras, $style, $count)
  {
    $on = function ($id) use ($extras) {
      return !empty($extras[$id]);
    };
    $has = function ($id) use ($extras) {
      return array_key_exists($id, $extras);
    };
    if ($has('lightbox')) {
      $values['box.enabled'] = $on('lightbox') ? 'true' : 'false';
    }
    if ($on('lightboxPro')) {
      $values['lightbox.thumbs'] = '1';
      $values['box.slideshow'] = 'true';
      $values['box.slideshowSpeed'] = '4000';
    }
    if ($has('captions') && !$on('captions')) {
      // No caption at all: neither the title row of the Caption Builder (PRO) nor the classic caption
      $values['captionBuilder.enabled'] = '0';
      $values['captionBuilder.title.enable'] = '0';
      $values['captionBuilder.background.enable'] = '0';
      $values['thumbnail.overlay.enabled'] = 'false';
    }
    if ($has('hscroll')) {
      $values['horizontalScroll.enabled'] = $on('hscroll') ? 'true' : 'false';
      if ($on('hscroll') && $style) {
        $values['horizontalScroll.color'] = $style['swatch']['accent'];
      }
    }
    // A page holds about a third of the images, so the pages work at once: 3 to 12 images a page. Without own
    // images (posts) the page size of the template stays.
    $perPage = (string) max(3, min(12, (int) ceil($count / 3)));
    if ($has('categories')) {
      $values['categories.enabled'] = $on('categories') ? 'true' : 'false';
      if ($on('categories')) {
        $values['categories.position'] = 'top';
        $values['categories.align'] = 'center';
      }
    }
    if ($has('pagination')) {
      $values['pagination.enabled'] = $on('pagination') ? 'true' : 'false';
      if ($on('pagination')) {
        $values['pagination.pos'] = 'bottom';
        $values['pagination.align'] = 'center';
        if ($count > 0) {
          $values['pagination.per_page'] = $perPage;
        }
      }
    }
    if ($has('loadmore')) {
      $values['showMore.enabled'] = $on('loadmore') ? 'true' : 'false';
      if ($on('loadmore')) {
        $values['showMore.loadOnScroll'] = 'false';
        $values['showMore.buttonText'] = $this->getEnvironment()->translate('Load more');
        if ($count > 0) {
          $values['showMore.imagesAmount'] = $perPage;
          $values['showMore.showMoreAmount'] = $perPage;
        }
      }
    }
    if ($style && ($on('categories') || $on('pagination') || $on('loadmore'))) {
      $values['buttons'] = $this->buttonsFor($style);
    }

    return $values;
  }

  /**
   * The Custom Buttons (PRO) of the category filter, the pages and Load more in the colors and corners of a style:
   * the accent with readable ink, rounded like the thumbnails (soft styles get pills). The sgg-btn classes give the
   * lift on hover, as the modern button presets do.
   * @param array $style
   * @return array
   */
  private function buttonsFor(array $style)
  {
    $accent = $style['swatch']['accent'];
    $radius = (int) GridGallery_Styles_Library::shape($style['profile'])['radius'];
    $lightbox = $this->getEnvironment()->getModule('lightbox');
    $ink = $lightbox && method_exists($lightbox, 'readableOn') ? $lightbox->readableOn($accent) : '#ffffff';

    return [
      'class' => 'sgg-btn sgg-btn-shadow',
      'color' => $ink,
      'backgroundColor' => $accent,
      'fontFamily' => 'Default',
      'fontSize' => '14',
      'fontWeight' => 'bold',
      'paddingV' => '8',
      'paddingH' => '16',
      'borderStyle' => 'none',
      'borderWidth' => '',
      'borderColor' => '',
      'borderRadius' => (string) ($radius >= 14 ? 999 : $radius),
    ];
  }

  /**
   * The posts of a new post feed. A post feed shows the posts chosen for it on its Posts tab (option
   * post_to_render{id} of PRO, ids as strings); made empty it showed nothing but a broken "1 0" pager, so it starts
   * with the twelve latest published posts. The user changes them on the Posts tab.
   * @param int $galleryId
   */
  private function samplePosts($galleryId)
  {
    if (!class_exists('GridGalleryPro_Galleries_Model_Posts') || get_option('post_to_render' . $galleryId)) {
      return;
    }
    $ids = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids', 'suppress_filters' => false]);
    if ($ids) {
      update_option('post_to_render' . (int) $galleryId, array_map('strval', array_map('intval', $ids)));
    }
  }

  /**
   * Sample categories for the category filter: three names given in turn to the images that have no categories yet.
   * Images that already have categories keep them (an image record is shared by every gallery it is in). The tags
   * model is the one of PRO, where the filter is.
   * @param int[] $photoIds
   */
  private function sampleCategories(array $photoIds)
  {
    if (!$photoIds || !class_exists('GridGalleryPro_Galleries_Model_Tags')) {
      return;
    }
    $env = $this->getEnvironment();
    $names = [$env->translate('Nature'), $env->translate('City'), $env->translate('People')];
    $tags = new GridGalleryPro_Galleries_Model_Tags();
    $index = 0;
    foreach ($photoIds as $photoId) {
      $current = $tags->getByPhotoId((int) $photoId);
      if (is_object($current) && isset($current->tags) && trim((string) $current->tags) !== '') {
        continue;
      }
      try {
        $tags->add((int) $photoId, $names[$index % 3]);
      } catch (Exception $e) {
        return;
      }
      $index++;
    }
  }

  /**
   * The settings of a Carousel or Slider for the switches of the Additional step. Only the options the goal showed
   * are written; the type module checks the result again when it saves (license, running line).
   * @param array $input Settings of the type
   * @param array $extras option id => bool (checked)
   * @param string $type carousel or slider
   * @return array
   */
  private function typeExtras(array $input, array $extras, $type)
  {
    $on = function ($id) use ($extras) {
      return !empty($extras[$id]);
    };
    $has = function ($id) use ($extras) {
      return array_key_exists($id, $extras);
    };
    $slider = $type === 'slider';
    $all = function ($value) {
      return ['d' => $value, 't' => $value, 'm' => $value];
    };
    if ($has('arrows')) {
      // A carousel keeps the phone without arrows: there it is swiped
      $input['nav']['arrows'] = $on('arrows') ? ['d' => 1, 't' => 1, 'm' => $slider ? 1 : 0] : $all(0);
    }
    if ($has('dots')) {
      $input['nav']['dots'] = $all($on('dots') ? 1 : 0);
    }
    if ($has('thumbs')) {
      $input['nav']['thumbs'] = $all($on('thumbs') ? 1 : 0);
    }
    if ($has('autoplay')) {
      $input['motion']['autoplay'] = $on('autoplay') ? 1 : 0;
    }
    if ($has('marquee')) {
      $input['motion']['marquee'] = $on('marquee') ? 1 : 0;
    }
    if ($has('fade')) {
      if ($on('fade')) {
        $input['motion']['effect'] = 'fade';
      } elseif ($input['motion']['effect'] === 'fade') {
        $input['motion']['effect'] = 'slide';
      }
    }
    if ($on('effect3d')) {
      $input['motion']['effect'] = $slider ? 'cube' : 'coverflow';
    }
    if ($has('vertical')) {
      $input['motion']['direction'] = $on('vertical') ? 'vertical' : 'horizontal';
    }
    if ($has('grayscale')) {
      $input['style']['grayscale'] = $on('grayscale') ? 1 : 0;
    }
    if ($has('captions')) {
      if (!$on('captions')) {
        $input['captions']['mode'] = 'none';
      } elseif ($input['captions']['mode'] === 'none') {
        $input['captions']['mode'] = $slider ? 'overlay' : 'below';
      }
    }
    // A vertical carousel stands in a box of a fixed height, and the default one is made for sideways slides: three
    // slides in 420 px were thin strips and a caption under a slide ran onto the next one. Two slides (one on a
    // phone) in a taller box, with the caption over the image.
    if ($on('vertical') && !$slider) {
      $input['responsive']['perView'] = ['d' => 2, 't' => 2, 'm' => 1];
      $input['layout']['height'] = ['d' => 640, 't' => 540, 'm' => 360];
      if (isset($input['captions']['mode']) && $input['captions']['mode'] === 'below') {
        $input['captions']['mode'] = 'overlay';
      }
    }
    if ($has('lightbox')) {
      $input['click']['action'] = $on('lightbox') ? 'lightbox' : 'none';
    }
    if ($on('lightboxPro')) {
      $input['lightbox']['thumbs'] = 1;
      $input['lightbox']['slideshow'] = 1;
    }

    return $input;
  }

  /**
   * Puts images of the Media Library into the gallery, in the chosen order, the way the Images page does.
   * @param int $galleryId
   * @param array $attachmentIds
   * @return array attachment id => photo id
   */
  private function attachImages($galleryId, array $attachmentIds)
  {
    $attached = [];
    // The Galleries module reaches the models of photos and resources through its controller
    $galleries = $this->getEnvironment()->getModule('galleries');
    $photos = $galleries ? $galleries->getModel('photos') : null;
    $resources = $galleries ? $galleries->getModel('resources') : null;
    if (!$photos || !$resources) {
      return $attached;
    }
    foreach (array_slice($attachmentIds, 0, 200) as $attachmentId) {
      $attachmentId = (int) $attachmentId;
      if ($attachmentId < 1 || !wp_attachment_is_image($attachmentId)) {
        continue;
      }
      $photo = $photos->getByAttachmentId($attachmentId);
      if (!$photo) {
        if (!$photos->add($attachmentId)) {
          continue;
        }
        $photo = $photos->getByAttachmentId($attachmentId);
      }
      if ($photo && !empty($photo->id)) {
        $resources->attach($galleryId, 'photo', (int) $photo->id, true);
        do_action('sgg_add_new_image_to_gallery', ['gallery_id' => $galleryId, 'attachment' => get_post($attachmentId)]);
        $attached[$attachmentId] = (int) $photo->id;
      }
    }

    return $attached;
  }

  /**
   * @param array $data
   * @param string $path a.b.c
   * @param mixed $value
   * @return array
   */
  private function setPath(array $data, $path, $value)
  {
    $keys = explode('.', $path);
    $last = array_pop($keys);
    $node = &$data;
    foreach ($keys as $key) {
      if (!isset($node[$key]) || !is_array($node[$key])) {
        $node[$key] = [];
      }
      $node = &$node[$key];
    }
    $node[$last] = $value;
    unset($node);

    return $data;
  }
}
