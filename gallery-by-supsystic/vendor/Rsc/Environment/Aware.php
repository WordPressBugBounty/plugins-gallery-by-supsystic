<?php


class RscSgg_Environment_Aware implements RscSgg_Environment_AwareInterface
{

    /**
     * @var RscSgg_Environment
     */
    protected $environment;

    /**
     * Sets the environment.
     *
     * @param RscSgg_Environment $environment
     */
    public function setEnvironment(RscSgg_Environment $environment)
    {
        $this->environment = $environment;
    }
}