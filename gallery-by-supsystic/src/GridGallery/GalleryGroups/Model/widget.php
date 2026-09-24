<?php
class sggGroupWidget extends Wp_Widget
{
  private $groups;

  /**
   * Register widget with WordPress.
   */
  function __construct()
  {
    $this->groups = new GridGallery_GalleryGroups_Model_Groups();
    parent::__construct('sggGroupWidget', 'Gallery Group by Supsystic Widget', ['description' => 'Displays every gallery that belongs to a Gallery Group']);
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
    echo wp_kses_post($args['before_widget']);
    if (!empty($instance['title'])) {
      echo wp_kses_post($args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title']);
    }
    echo do_shortcode('[supsystic-gallery-group id=' . (int) $instance['group_id'] . ']');
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
    if (isset($instance['title'])) {
      $title = $instance['title'];
    } else {
      $title = 'Title';
    }

    $groups = $this->groups->getAll();
    ?>
            <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php esc_html_e('Title:'); ?></label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>">
            <label for="<?php echo esc_attr($this->get_field_id('group_id')); ?>"><?php esc_html_e('Select gallery group: '); ?></label>
            <select id="<?php echo esc_attr($this->get_field_id('group_id')); ?>" class="widefat" name="<?php echo esc_attr($this->get_field_name('group_id')); ?>" type="text">
            <?php foreach ((array) $groups as $group): ?>
                <option value="<?php echo esc_attr($group->group_id); ?>" <?php selected(isset($instance['group_id']) && $instance['group_id'] == $group->group_id); ?>>
                    <?php echo esc_html($group->name . ' ' . $group->group_id); ?>
                </option>
            <?php endforeach; ?>
            </select>
            </p>


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
