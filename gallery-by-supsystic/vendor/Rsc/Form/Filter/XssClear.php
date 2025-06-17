<?php


class RscSgg_Form_Filter_XssClear implements RscSgg_Form_Filter_Interface
{
    /**
     * Filters data
     * @param mixed $data The data that filter will be applied
     * @return string
     */
    public function apply($data)
    {
        return htmlspecialchars($data, ENT_QUOTES, get_bloginfo('charset'));
    }
}