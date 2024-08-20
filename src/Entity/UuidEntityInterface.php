<?php

namespace App\Entity;

use Symfony\Component\Uid\UuidV7;

interface UuidEntityInterface
{
    public function getId(): UuidV7;
}
