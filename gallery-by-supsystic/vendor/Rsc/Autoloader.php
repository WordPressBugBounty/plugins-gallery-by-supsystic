<?php

class RscSgg_Autoloader
{
  /**
   * Register RscSgg_Autoloader in the SPL autoloader stack
   */
  public static function register()
  {
    spl_autoload_register([__CLASS__, 'load']);
  }

  /**
   * Load the specified class
   * @param string $classname Name of the class to be loaded
   */
  public static function load($classname)
  {
    if (substr($classname, 0, 6) !== 'RscSgg') {
      return;
    }
    $classname = str_replace('RscSgg', '', $classname);
    $file = dirname(__FILE__) . '/' . str_replace(['_', '\0'], ['/', ''], $classname) . '.php';
    if (is_file($file)) {
      require $file;
    }
  }
}
