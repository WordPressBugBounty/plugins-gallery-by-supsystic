<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_SupTwgSgg_Node_Expression_Binary_Greater extends Twig_SupTwgSgg_Node_Expression_Binary
{
    public function operator(Twig_SupTwgSgg_Compiler $compiler)
    {
        return $compiler->raw('>');
    }
}
