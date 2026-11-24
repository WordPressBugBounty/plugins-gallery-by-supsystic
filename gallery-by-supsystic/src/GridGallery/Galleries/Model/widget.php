<?php
class sggWidget extends Wp_Widget
{
  private $gallery;

  /**
   * Register widget with WordPress.
   * The id base 'sggWidget' and the saved keys (title, gallery_id) stay as they are: widgets already placed keep working.
   */
  function __construct()
  {
    $this->gallery = new GridGallery_Galleries_Model_Galleries();
    parent::__construct('sggWidget', __('Photo Gallery by Supsystic', 'sgg'), [
      'description' => __('Shows one of your galleries: a grid, a carousel or a slider.', 'sgg'),
    ]);
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget($args, $instance)
  {
    $galleryId = !empty($instance['gallery_id']) ? (int) $instance['gallery_id'] : 0;
    // Nothing chosen yet (a new widget, or one added in a page builder before its settings were saved)
    if (!$galleryId) {
      return;
    }
    echo wp_kses_post($args['before_widget']);
    if (!empty($instance['title'])) {
      echo wp_kses_post($args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title']);
    }
    echo do_shortcode('[supsystic-gallery id=' . $galleryId . ' position="center"]');
    echo wp_kses_post($args['after_widget']);
  }

  /**
   * Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form($instance)
  {
    $title = isset($instance['title']) ? $instance['title'] : '';
    $selectedId = isset($instance['gallery_id']) ? (string) $instance['gallery_id'] : '';
    $galleries = (array) $this->gallery->getList();
    ?>
            <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <p>
            <label for="<?php echo esc_attr($this->get_field_id('gallery_id')); ?>"><?php esc_html_e('Gallery:', 'sgg'); ?></label>
            <select id="<?php echo esc_attr($this->get_field_id('gallery_id')); ?>" class="widefat" name="<?php echo esc_attr($this->get_field_name('gallery_id')); ?>">
                <option value=""><?php echo esc_html($galleries ? __('— Select a gallery —', 'sgg') : __('No galleries yet', 'sgg')); ?></option>
            <?php foreach ($galleries as $gallery): ?>
                <option value="<?php echo esc_attr($gallery->id); ?>" <?php selected($selectedId, (string) $gallery->id); ?>>
                    <?php echo esc_html($gallery->title . ' (#' . $gallery->id . ')'); ?>
                </option>
            <?php endforeach; ?>
            </select>
            </p>
            <?php if (!$galleries && current_user_can('manage_options')): ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=supsystic-gallery&module=galleries&action=showPresets')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Create a gallery in Photo Gallery', 'sgg'); ?></a></p>
            <?php endif; ?>
        <?php
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @see WP_Widget::update()
   *
   * @param array $new_instance Values just sent to be saved.
   * @param array $old_instance Previously saved values from database.
   *
   * @return array Updated safe values to be saved.
   */
  public function update($new_instance, $old_instance)
  {
    $instance = [];
    $instance['title'] = !empty($new_instance['title']) ? strip_tags($new_instance['title']) : '';
    $instance['gallery_id'] = !empty($new_instance['gallery_id']) ? strip_tags($new_instance['gallery_id']) : '';

    return $instance;
  }
}
