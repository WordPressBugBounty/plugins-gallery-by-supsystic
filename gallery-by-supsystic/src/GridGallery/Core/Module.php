<?php

/**
 * Class GridGallery_Core_Module
 * Core module
 *
 * @package GridGallery\Core
 * @author Artur Kovalevsky
 */
class GridGallery_Core_Module extends RscSgg_Mvc_Module
{
    /**
     * {@inheritdoc}
     */
    public function onInit()
    {
        parent::onInit();
        $path = dirname(dirname(dirname(dirname(__FILE__))));
        $url = plugins_url(basename($path));
        $config = $this->getEnvironment()->getConfig();

        //Clear plugin cache after update
        $optionName = $config->get('hooks_prefix') . 'plugin_version';
        $currentVersion = $config->get('plugin_version');
        $oldVersion = get_option($optionName);

        if (version_compare($oldVersion, $currentVersion) === -1) {
            $this->cleanGalleryCache();
            update_option($optionName, $currentVersion);
        }

        $config->add('plugin_url', $url);
        $config->add('plugin_path', $path);

        add_filter('gg_hooks_prefix', array($this, 'addHooksPrefix'), 10, 1);
		    add_action('init', array($this, 'registerTwigFunctions'), 10, 1);
    }
    /**
     * Adds the plugin's hooks prefix to the hook name
     *
     * @param string $hook The name of the hook
     * @return string
     */
    public function addHooksPrefix($hook)
    {
        $config = $this->getEnvironment()->getConfig();

        return $config->get('hooks_prefix') . $hook;
    }

    public function afterUiLoaded(Callable $callback)
    {
        if (!is_callable($callback)) {
            throw new InvalidArgumentException('$callback must be a callable');
        }

        add_action($this->addHooksPrefix('after_ui_loaded'), $callback);
    }

    public function buildProUrl(array $parameters = array())
    {
        $config = $this->getEnvironment()->getConfig();
        $homepage = 'https://supsystic.com/plugins/photo-gallery/';
        $campaign = 'gallery';

        if (!array_key_exists('utm_source', $parameters)) {
            $parameters['utm_source'] = 'plugin';
        }

        if (!array_key_exists('utm_campaign', $parameters)) {
            $parameters['utm_campaign'] = $campaign;
        }

        return $homepage . '?' . http_build_query($parameters);
    }

	public function getPluginDirectoryUrl($path)
	{
		return plugin_dir_url($this->getEnvironment()->getPluginPath() . '/index.php') . '/' . $path;
	}
    public function getCdnUrl() {
        return (is_ssl() ? 'https' : 'http').'://supsystic-42d7.kxcdn.com/';
    }

    public function registerTwigFunctions()
    {
        $twig = $this->getTwig();
    		$twig->addFunction(
    			new Twig_SupTwgSgg_SimpleFunction(
    				'plugin_directory_url', array($this, 'getPluginDirectoryUrl')
    			)
    		);
        $twig->addFunction(
            new Twig_SupTwgSgg_SimpleFunction(
                'build_pro_url', array($this, 'buildProUrl')
            )
        );
        $twig->addFunction(
            new Twig_SupTwgSgg_SimpleFunction(
                'translate', array($this, 'translate')
            )
        );
        $twig->addFunction(
            new Twig_SupTwgSgg_SimpleFunction(
                'getProUrl', array($this, 'getProUrl')
            )
        );
        $twig->addFunction(
            new Twig_SupTwgSgg_SimpleFunction(
                'dump', array($this, 'varDump')
            )
        );
        $config = $this->getEnvironment()->getConfig();
        $twig->addGlobal('SGG_PLUGIN_URL', SGG_PLUGIN_URL);
        $twig->addGlobal('SGG_PLUGIN_VERSION', $config->get('plugin_version'));
        $twig->addGlobal('SGG_PLUGIN_NAME', $config->get('plugin_name'));
        global $current_user;
        $twig->addGlobal('SGG_USER_NAME', $current_user->user_firstname.' '.$current_user->user_lastname);
        $twig->addGlobal('SGG_USER_EMAIL', $current_user->user_email);
        $twig->addGlobal('SGG_WEBSITE', get_bloginfo('url'));
        $twig->addGlobal('_wpnonce', wp_create_nonce('supsystic-gallery'));

        $show = true;
        $acRemind = get_option('sgg_ac_remind', false);
        if (!empty($acRemind)) {
          $currentDate = date('Y-m-d h:i:s');
          if ($currentDate > $acRemind) {
            $show = true;
          } else {
            $show = false;
          }
        }
        $acSubscribe = get_option('sgg_ac_subscribe', false);
        if (!empty($acSubscribe)) {
          $show = false;
        }
        $acDisabled = get_option('sgg_ac_disabled', false);
        if (!empty($acDisabled)) {
          $show = false;
        }

        $twig->addGlobal('SGG_AC_SHOW', $show);
        // delete_option('sgg_ac_remind');
        // delete_option('sgg_ac_disabled');
    }

    //Clear gallery cache after update
    private function cleanGalleryCache() {
        $cachePath = $this->getConfig()->get('plugin_cache_tables');
        if ($cachePath) {
            array_map('unlink', glob("$cachePath/*"));
        }
    }
}
