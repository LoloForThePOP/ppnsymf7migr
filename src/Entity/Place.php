<?php

namespace App\Entity;

use App\Entity\Embeddables\GeoPoint;
use App\Repository\PlaceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\NormalizableInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[ORM\Entity(repositoryClass: PlaceRepository::class)]
class Place implements NormalizableInterface, \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['place:read', 'place:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 40)]
    #[Groups(['place:read', 'place:write'])]
    private string $type = 'generic';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $administrativeAreaLevel1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $administrativeAreaLevel2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $locality = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sublocalityLevel1 = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\ManyToOne(targetEntity: PPBase::class, inversedBy: 'places')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?PPBase $presentation = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $position = null;

    #[ORM\Embedded(class: GeoPoint::class)]
    #[Groups(['place:read', 'place:write'])]
    private GeoPoint $geoloc;

    #[ORM\ManyToOne(inversedBy: 'places')]
    private ?PPBase $project = null;

    public function __construct()
    {
        $this->geoloc = new GeoPoint();
    }

    // ────────────────────────────────────────
    // Normalization (e.g. for Algolia)
    // ────────────────────────────────────────

    public function normalize(NormalizerInterface $normalizer, $format = null, array $context = []): array
    {
        return [
            '_geoloc' => $this->geoloc->toArray(),
            'name' => $this->name,
            'country' => $this->country,
            'locality' => $this->locality,
        ];
    }

    // ────────────────────────────────────────
    // Accessors
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = trim((string) $name);
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getAdministrativeAreaLevel1(): ?string
    {
        return $this->administrativeAreaLevel1;
    }

    public function setAdministrativeAreaLevel1(?string $level): self
    {
        $this->administrativeAreaLevel1 = $level;
        return $this;
    }

    public function getAdministrativeAreaLevel2(): ?string
    {
        return $this->administrativeAreaLevel2;
    }

    public function setAdministrativeAreaLevel2(?string $level): self
    {
        $this->administrativeAreaLevel2 = $level;
        return $this;
    }

    public function getLocality(): ?string
    {
        return $this->locality;
    }

    public function setLocality(?string $locality): self
    {
        $this->locality = $locality;
        return $this;
    }

    public function getSublocalityLevel1(): ?string
    {
        return $this->sublocalityLevel1;
    }

    public function setSublocalityLevel1(?string $sublocalityLevel1): self
    {
        $this->sublocalityLevel1 = $sublocalityLevel1;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getPresentation(): ?PPBase
    {
        return $this->presentation;
    }

    public function setPresentation(?PPBase $presentation): self
    {
        $this->presentation = $presentation;
        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position;
        return $this;
    }

    public function getGeoloc(): GeoPoint
    {
        return $this->geoloc;
    }

    public function setGeoloc(GeoPoint $geoloc): self
    {
        $this->geoloc = $geoloc;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name
            ? sprintf('%s (%s)', $this->name, $this->country ?? 'n/a')
            : 'Lieu non défini';
    }

    public function getProject(): ?PPBase
    {
        return $this->project;
    }

    public function setProject(?PPBase $project): static
    {
        $this->project = $project;

        return $this;
    }
}
