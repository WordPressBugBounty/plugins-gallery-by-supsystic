<?php

/*
 * This file is part of Twig.
 *
 * (c) Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_SupTwgSgg_Node_Expression_Function extends Twig_SupTwgSgg_Node_Expression_Call
{
    public function __construct($name, Twig_SupTwgSgg_NodeInterface $arguments, $lineno)
    {
        parent::__construct(array('arguments' => $arguments), array('name' => $name, 'is_defined_test' => false), $lineno);
    }

    public function compile(Twig_SupTwgSgg_Compiler $compiler)
    {
        $name = $this->getAttribute('name');
        $function = $compiler->getEnvironment()->getFunction($name);

        $this->setAttribute('name', $name);
        $this->setAttribute('type', 'function');
        $this->setAttribute('thing', $function);
        $this->setAttribute('needs_environment', $function->needsEnvironment());
        $this->setAttribute('needs_context', $function->needsContext());
        $this->setAttribute('arguments', $function->getArguments());
        if ($function instanceof Twig_SupTwgSgg_FunctionCallableInterface || $function instanceof Twig_SupTwgSgg_SimpleFunction) {
            $callable = $function->getCallable();
            if ('constant' === $name && $this->getAttribute('is_defined_test')) {
                $callable = 'Twig_SupTwgSgg_constant_is_defined';
            }

            $this->setAttribute('callable', $callable);
        }
        if ($function instanceof Twig_SupTwgSgg_SimpleFunction) {
            $this->setAttribute('is_variadic', $function->isVariadic());
        }

        $this->compileCallable($compiler);
    }
}
