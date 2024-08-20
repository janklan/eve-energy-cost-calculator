<?php

namespace App\Entity;

use App\Repository\AccessoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessoryRepository::class)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Accessory implements UuidEntityInterface
{
    use UuidTrait;

    #[ORM\Column(length: 255, nullable: false)]
    public string $name;

    #[ORM\Column(type: 'float', nullable: false, options: ['default' => 1.0])]
    public float $businessUse = 1.0;

    /**
     * @var Collection<int, Consumption>
     */
    #[ORM\OneToMany(targetEntity: Consumption::class, mappedBy: 'accessory', orphanRemoval: true)]
    public Collection $consumption;

    public function __construct()
    {
        $this->consumption = new ArrayCollection();
    }

    public function addConsumption(Consumption $consumption): static
    {
        if (!$this->consumption->contains($consumption)) {
            $this->consumption->add($consumption);
            $consumption->setAccessory($this);
        }

        return $this;
    }

    public function removeConsumption(Consumption $consumption): static
    {
        if ($this->consumption->removeElement($consumption)) {
            if ($consumption->getAccessory() === $this) {
                $consumption->setAccessory(null);
            }
        }

        return $this;
    }
}
