<?php

declare(strict_types=1);

namespace IfCastle\Services\UserManager;

interface UserAwareInterface
{
    public function findUser(): UserInterface|null;

    public function getUser(): UserInterface;
}
