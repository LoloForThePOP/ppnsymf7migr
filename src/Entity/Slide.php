<?php

namespace App\Entity;

use App\Repository\SlideRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

use App\Entity\Traits\TimestampableTrait;  

#[ORM\Entity(repositoryClass: SlideRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Slide
{

    use TimestampableTrait; 


    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(['image', 'youtube_video'])]
    private ?string $type = null;


     /**
     * Adress of the media file (URL or local path) 
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    /**
     * File upload (not persisted)
     */
    #[Vich\UploadableField(mapping: 'presentation_slide_file', fileNameProperty: 'address')]
    #[Assert\Image(
        maxSize: '4500k',
        maxSizeMessage: 'Poids maximal accepté pour l\'image : {{ limit }} {{ suffix }}',
        mimeTypes: ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/apng', 'image/webp', 'image/bmp'],
        mimeTypesMessage: 'Le format ({{ type }}) n\'est pas pris en compte. Formats acceptés : {{ types }}'
    )]
    private ?File $file = null;


    #[ORM\Column(length: 400, nullable: true)]
    private ?string $caption = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $position = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $licence = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ────────────────────────────────────────
    // Getters / Setters
    // ────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file = null): void
    {
        $this->file = $file;
        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getCaption(): ?string
    {
        return $this->caption;
    }

    public function setCaption(?string $caption): self
    {
        $this->caption = $caption;
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

    public function getLicence(): ?string
    {
        return $this->licence;
    }

    public function setLicence(?string $licence): self
    {
        $this->licence = $licence;
        return $this;
    }


}
