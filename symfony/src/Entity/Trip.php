<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TripRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripRepository::class)]
#[ORM\Table(name: 'trips')]
#[ORM\HasLifecycleCallbacks]
class Trip
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Route::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private Route $route;

    #[ORM\ManyToOne(targetEntity: Bus::class, inversedBy: 'trips')]
    #[ORM\JoinColumn(nullable: false)]
    private Bus $bus;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $departureTime;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $arrivalTime;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'SCHEDULED'])]
    private string $status = 'SCHEDULED';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(mappedBy: 'trip', targetEntity: Booking::class)]
    private Collection $bookings;

    public function __construct()
    {
        $this->bookings = new ArrayCollection();
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

    public function getRoute(): Route
    {
        return $this->route;
    }

    public function setRoute(Route $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getBus(): Bus
    {
        return $this->bus;
    }

    public function setBus(Bus $bus): static
    {
        $this->bus = $bus;

        return $this;
    }

    public function getDepartureTime(): \DateTimeInterface
    {
        return $this->departureTime;
    }

    public function setDepartureTime(\DateTimeInterface $departureTime): static
    {
        $this->departureTime = $departureTime;

        return $this;
    }

    public function getArrivalTime(): \DateTimeInterface
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(\DateTimeInterface $arrivalTime): static
    {
        $this->arrivalTime = $arrivalTime;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }
}
