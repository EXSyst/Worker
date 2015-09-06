<?php

namespace EXSyst\Component\Worker\Exception;

/**
 * Exception thrown if an error which can only be found on runtime occurs.
 *
 * @author Ener-Getick <egetick@gmail.com>
 * @author Nicolas "Exter-N" L. <exter-n@exter-n.fr>
 */
class RuntimeException extends \RuntimeException implements ExceptionInterface
{
}
