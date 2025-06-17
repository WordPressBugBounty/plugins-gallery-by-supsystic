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
 * Internal class.
 *
 * This class is used by Twig_SupTwgSgg_Environment as a staging area and must not be used directly.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class Twig_SupTwgSgg_Extension_Staging extends Twig_SupTwgSgg_Extension
{
    protected $functions = array();
    protected $filters = array();
    protected $visitors = array();
    protected $tokenParsers = array();
    protected $globals = array();
    protected $tests = array();

    public function addFunction($name, $function)
    {
        if (isset($this->functions[$name])) {
        }

        $this->functions[$name] = $function;
    }

    public function getFunctions()
    {
        return $this->functions;
    }

    public function addFilter($name, $filter)
    {
        if (isset($this->filters[$name])) {
        }

        $this->filters[$name] = $filter;
    }

    public function getFilters()
    {
        return $this->filters;
    }

    public function addNodeVisitor(Twig_SupTwgSgg_NodeVisitorInterface $visitor)
    {
        $this->visitors[] = $visitor;
    }

    public function getNodeVisitors()
    {
        return $this->visitors;
    }

    public function addTokenParser(Twig_SupTwgSgg_TokenParserInterface $parser)
    {
        if (isset($this->tokenParsers[$parser->getTag()])) {
        }

        $this->tokenParsers[$parser->getTag()] = $parser;
    }

    public function getTokenParsers()
    {
        return $this->tokenParsers;
    }

    public function addGlobal($name, $value)
    {
        $this->globals[$name] = $value;
    }

    public function getGlobals()
    {
        return $this->globals;
    }

    public function addTest($name, $test)
    {
        if (isset($this->tests[$name])) {
        }

        $this->tests[$name] = $test;
    }

    public function getTests()
    {
        return $this->tests;
    }

    public function getName()
    {
        return 'staging';
    }
}
