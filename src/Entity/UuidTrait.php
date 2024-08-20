<?php

namespace App\Entity;

use App\Doctrine\UuidGenerator;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

trait UuidTrait
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true, nullable: false, updatable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected ?UuidV7 $id;

    public function getId(): UuidV7
    {
        return $this->id ??= Uuid::v7();
    }

    /**
     * The ID is set automatically when first accessed ({@see getId()}), but if you need to provide your onw, use this
     * method.
     *
     * Important, never allow changing the primary key via this method. If it's already set, it should be left untouched.
     *
     * @internal
     */
    public function setId(UuidV7 $id): UuidV7
    {
        return $this->id ??= $id;
    }

    public function equals(?UuidEntityInterface $other): bool
    {
        return $this->getId()->equals($other?->getId());
    }
}
