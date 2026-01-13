<?php

declare(strict_types=1);

namespace IfCastle\Services\Session;

interface TokenAwareInterface
{
    public function findToken(): string|null;

    public function getToken(): string;
}
