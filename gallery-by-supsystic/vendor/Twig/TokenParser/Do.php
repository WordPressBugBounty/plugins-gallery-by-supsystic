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
 * Evaluates an expression, discarding the returned value.
 *
 * @final
 */
class Twig_SupTwgSgg_TokenParser_Do extends Twig_SupTwgSgg_TokenParser
{
    public function parse(Twig_SupTwgSgg_Token $token)
    {
        $expr = $this->parser->getExpressionParser()->parseExpression();

        $this->parser->getStream()->expect(Twig_SupTwgSgg_Token::BLOCK_END_TYPE);

        return new Twig_SupTwgSgg_Node_Do($expr, $token->getLine(), $this->getTag());
    }

    public function getTag()
    {
        return 'do';
    }
}
