<?php

/*
 * This file is part of Cognetiq.
 *
 * Copyright (c) iTools Pty Ltd
 * Author: Jan Klan <jan@itools.net.au>
 *
 * This source file contains proprietary IP of iTools Pty Ltd.
 * Any distribution or unauthorised disclosure is prohibited.
 */

namespace App\Doctrine;

use App\Entity\UuidEntityInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Symfony\Component\Uid\Uuid;

class UuidGenerator extends AbstractIdGenerator
{
    public function generate(EntityManager $em, $entity): Uuid
    {
        if ($entity instanceof UuidEntityInterface) {
            return $entity->getId();
        }

        return Uuid::v7();
    }

    public function generateId(EntityManagerInterface $em, $entity): Uuid
    {
        if ($entity instanceof UuidEntityInterface) {
            return $entity->getId();
        }

        return Uuid::v7();
    }
}
