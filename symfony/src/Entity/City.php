<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CityRepository::class)]
#[ORM\Table(name: 'cities')]
#[ORM\HasLifecycleCallbacks]
class City
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Route> */
    #[ORM\OneToMany(mappedBy: 'departureCity', targetEntity: Route::class)]
    private Collection $departureRoutes;

    /** @var Collection<int, Route> */
    #[ORM\OneToMany(mappedBy: 'arrivalCity', targetEntity: Route::class)]
    private Collection $arrivalRoutes;

    public function __construct()
    {
        $this->departureRoutes = new ArrayCollection();
        $this->arrivalRoutes = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    /** @return Collection<int, Route> */
    public function getDepartureRoutes(): Collection
    {
        return $this->departureRoutes;
    }

    /** @return Collection<int, Route> */
    public function getArrivalRoutes(): Collection
    {
        return $this->arrivalRoutes;
    }
}
