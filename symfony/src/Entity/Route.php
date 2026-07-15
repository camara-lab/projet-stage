<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RouteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RouteRepository::class)]
#[ORM\Table(name: 'routes')]
#[ORM\HasLifecycleCallbacks]
class Route
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'departureRoutes')]
    #[ORM\JoinColumn(nullable: false)]
    private City $departureCity;

    #[ORM\ManyToOne(targetEntity: City::class, inversedBy: 'arrivalRoutes')]
    #[ORM\JoinColumn(nullable: false)]
    private City $arrivalCity;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $basePrice;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Trip> */
    #[ORM\OneToMany(mappedBy: 'route', targetEntity: Trip::class)]
    private Collection $trips;

    public function __construct()
    {
        $this->trips = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDepartureCity(): City
    {
        return $this->departureCity;
    }

    public function setDepartureCity(City $departureCity): static
    {
        $this->departureCity = $departureCity;

        return $this;
    }

    public function getArrivalCity(): City
    {
        return $this->arrivalCity;
    }

    public function setArrivalCity(City $arrivalCity): static
    {
        $this->arrivalCity = $arrivalCity;

        return $this;
    }

    public function getBasePrice(): string
    {
        return $this->basePrice;
    }

    public function setBasePrice(string $basePrice): static
    {
        $this->basePrice = $basePrice;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return Collection<int, Trip> */
    public function getTrips(): Collection
    {
        return $this->trips;
    }
}
