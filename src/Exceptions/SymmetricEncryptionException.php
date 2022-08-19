<?php

namespace Smoren\EncryptionTools\Exceptions;

class SymmetricEncryptionException extends EncryptionException
{
    public const CANNOT_DECRYPT = 1;
    public const UNKNOWN_METHOD = 2;
}
