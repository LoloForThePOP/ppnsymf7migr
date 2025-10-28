<?php

namespace App\Entity\Embeddables;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
class GeoPoint
{
    
    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    public function __construct(?float $latitude = null, ?float $longitude = null)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function isDefined(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /** Returns the Algolia-compatible structure */
    public function toArray(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
        ];
    }


}
