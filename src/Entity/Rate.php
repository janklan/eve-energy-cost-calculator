<?php

namespace App\Entity;

use App\Repository\RateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RateRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[ORM\UniqueConstraint('rate_uq', ['name', 'effective_since', 'effective_until'])]
class Rate implements UuidEntityInterface
{
    use UuidTrait;

    #[ORM\Column(length: 255, nullable: false)]
    public string $name;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: false)]
    public \DateTimeImmutable $effectiveSince;

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: false)]
    public \DateTimeImmutable $effectiveUntil;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $timeOfDayStart = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $timeOfDayEnd = null;

    #[ORM\Column]
    public ?float $ratePerKWh = null;

    #[ORM\Column]
    public ?float $ratePerDay = null;
}
