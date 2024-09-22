<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

//@trigger_error('The Twig_SupTwgSgg_Test_Function class is deprecated since version 1.12 and will be removed in 2.0. Use Twig_SupTwgSgg_SimpleTest instead.', E_USER_DEPRECATED);

/**
 * Represents a function template test.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated since 1.12 (to be removed in 2.0)
 */
class Twig_SupTwgSgg_Test_Function extends Twig_SupTwgSgg_Test
{
    protected $function;

    public function __construct($function, array $options = array())
    {
        $options['callable'] = $function;

        parent::__construct($options);

        $this->function = $function;
    }

    public function compile()
    {
        return $this->function;
    }
}
