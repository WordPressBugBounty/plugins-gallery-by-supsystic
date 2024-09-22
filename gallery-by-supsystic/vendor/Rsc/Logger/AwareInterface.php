<?php

/**
 * Describes a logger-aware instance
 */
interface RscSgg_Logger_AwareInterface
{
    /**
     * Sets a logger instance on the object
     *
     * @param RscSgg_Logger_Interface $logger
     * @return null
     */
    public function setLogger(RscSgg_Logger_Interface $logger);
}