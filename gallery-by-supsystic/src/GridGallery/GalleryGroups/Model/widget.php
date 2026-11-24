<?php
class sggGroupWidget extends Wp_Widget
{
  private $groups;

  /**
   * Register widget with WordPress.
   * The id base 'sggGroupWidget' and the saved keys (title, group_id) stay as they are: widgets already placed keep working.
   */
  function __construct()
  {
    $this->groups = new GridGallery_GalleryGroups_Model_Groups();
    parent::__construct('sggGroupWidget', __('Photo Gallery Group by Supsystic', 'sgg'), [
      'description' => __('Shows every gallery in a gallery group.', 'sgg'),
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
    $groupId = !empty($instance['group_id']) ? (int) $instance['group_id'] : 0;
    // Nothing chosen yet (a new widget, or one added in a page builder before its settings were saved)
    if (!$groupId) {
      return;
    }
    echo wp_kses_post($args['before_widget']);
    if (!empty($instance['title'])) {
      echo wp_kses_post($args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title']);
    }
    echo do_shortcode('[supsystic-gallery-group id=' . $groupId . ']');
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
    $selectedId = isset($instance['group_id']) ? (string) $instance['group_id'] : '';
    $groups = (array) $this->groups->getAll();
    ?>
            <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            </p>
            <p>
            <label for="<?php echo esc_attr($this->get_field_id('group_id')); ?>"><?php esc_html_e('Gallery group:', 'sgg'); ?></label>
            <select id="<?php echo esc_attr($this->get_field_id('group_id')); ?>" class="widefat" name="<?php echo esc_attr($this->get_field_name('group_id')); ?>">
                <option value=""><?php echo esc_html($groups ? __('— Select a gallery group —', 'sgg') : __('No gallery groups yet', 'sgg')); ?></option>
            <?php foreach ($groups as $group): ?>
                <option value="<?php echo esc_attr($group->group_id); ?>" <?php selected($selectedId, (string) $group->group_id); ?>>
                    <?php echo esc_html($group->name . ' (#' . $group->group_id . ')'); ?>
                </option>
            <?php endforeach; ?>
            </select>
            </p>
            <?php if (!$groups && current_user_can('manage_options')): ?>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=supsystic-gallery&module=gallerygroups&action=create')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Create a gallery group in Photo Gallery', 'sgg'); ?></a></p>
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
    $instance['group_id'] = !empty($new_instance['group_id']) ? strip_tags($new_instance['group_id']) : '';

    return $instance;
  }
}
