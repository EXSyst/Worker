<?php

/*
 * This file is part of the Worker package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Component\Worker\Exception;

/**
 * Exception that represents error in the program logic. This kind of exception should lead directly to a fix in your code.
 *
 * @author Ener-Getick <egetick@gmail.com>
 * @author Nicolas "Exter-N" L. <exter-n@exter-n.fr>
 */
class LogicException extends \LogicException implements ExceptionInterface
{
}
