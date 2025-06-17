<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Base class for all token parsers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Twig_SupTwgSgg_TokenParser implements Twig_SupTwgSgg_TokenParserInterface
{
    /**
     * @var Twig_SupTwgSgg_Parser
     */
    protected $parser;

    /**
     * Sets the parser associated with this token parser.
     */
    public function setParser(Twig_SupTwgSgg_Parser $parser)
    {
        $this->parser = $parser;
    }
}
