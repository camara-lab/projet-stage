<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BookingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\UniqueConstraint(name: 'UNIQ_booking_seat', columns: ['trip_id', 'seat_number'])]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Trip::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private Trip $trip;

    #[ORM\Column(type: 'integer')]
    private int $seatNumber;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'PENDING'])]
    private string $status = 'PENDING';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'string', length: 10, options: ['default' => 'ADULT'])]
    private string $passengerType = 'ADULT';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    private string $unitPrice = '0.00';

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Payment::class)]
    private ?Payment $payment = null;

    public function __construct()
    {
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTrip(): Trip
    {
        return $this->trip;
    }

    public function setTrip(Trip $trip): static
    {
        $this->trip = $trip;

        return $this;
    }

    public function getSeatNumber(): int
    {
        return $this->seatNumber;
    }

    public function setSeatNumber(int $seatNumber): static
    {
        $this->seatNumber = $seatNumber;

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

    public function getPassengerType(): string
    {
        return $this->passengerType;
    }

    public function setPassengerType(string $passengerType): static
    {
        $this->passengerType = $passengerType;

        return $this;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }
}
