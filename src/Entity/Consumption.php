<?php

namespace App\Entity;

use App\Repository\ConsumptionRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsumptionRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\UniqueConstraint('consumption_uq', ['accessory_id', 'timestamp'])]
class Consumption implements UuidEntityInterface
{
    use UuidTrait;

    #[ORM\ManyToOne(inversedBy: 'consumption')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    public Accessory $accessory;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    public ?Rate $rate = null;

    #[ORM\Column(type: 'datetimetz_immutable')]
    public CarbonImmutable $timestamp;

    #[ORM\Column(type: Types::FLOAT)]
    public float $consumptionWh;
}
