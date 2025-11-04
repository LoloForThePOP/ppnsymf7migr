<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\DocumentRepository;
use App\Entity\Traits\TimestampableTrait;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[Vich\Uploadable]
class Document
{
    // ─────────────────────────────────────────────
    // PROPERTIES
    // ─────────────────────────────────────────────

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    use TimestampableTrait;

    // ────────────────────────────────────────
    // Title
    // ────────────────────────────────────────

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre ne peut pas être vide.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/\S+/',
        message: 'Le titre ne peut pas contenir uniquement des espaces.'
    )]
    private string $title;

    // ────────────────────────────────────────
    // Position (for ordering)
    // ────────────────────────────────────────

    #[ORM\Column(type: 'smallint', nullable: true)]
    #[Assert\PositiveOrZero(message: 'La position doit être un nombre positif ou nul.')]
    private ?int $position = null;

    // ────────────────────────────────────────
    // MIME Type (stored from upload)
    // ────────────────────────────────────────

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le type MIME ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $mimeType = null;

    // ────────────────────────────────────────
    // File Name
    // ────────────────────────────────────────

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le nom du fichier ne peut pas être vide.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le nom du fichier ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $fileName;

    // ────────────────────────────────────────
    // Virtual Upload Field (VichUploader)
    // ────────────────────────────────────────

    /**
     * Virtual field used by VichUploader.
     *
     * @var File|null
     */
    #[Vich\UploadableField(
        mapping: 'presentation_document_file',
        fileNameProperty: 'fileName',
        mimeType: 'mimeType',
        size: 'size'
    )]
    #[Assert\File(
        maxSize: '13M',
        maxSizeMessage: 'Ce fichier dépasse la limite de taille maximale : {{ limit }} {{ suffix }}.',
        mimeTypes: [
            // Documents
            'application/pdf',
            'application/x-pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation',
            // Other text formats
            'text/plain',
            'text/rtf',
            'application/rtf',
            'application/epub+zip',
        ],
        mimeTypesMessage: 'Veuillez sélectionner un fichier valide (PDF, Word, Excel, PowerPoint, OpenDocument, ePub, RTF, texte).'
    )]
    private ?File $file = null;

    // ──────────────────────────────────────── 
    // Relations    
    // ──────────────────────────────────────── 

    #[ORM\ManyToOne(inversedBy: 'documents')]
    private ?PPBase $projectPresentation = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    // ─────────────────────────────────────────────
    // CONSTRUCTOR
    // ─────────────────────────────────────────────

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ─────────────────────────────────────────────
    // GETTERS & SETTERS
    // ─────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);
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


    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    /**
     * @param File|\Symfony\Component\HttpFoundation\File\UploadedFile|null $file
     */
    public function setFile(?File $file = null): void
    {
        $this->file = $file;

        // If a new file is uploaded, force Doctrine update
        if ($file !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }


    public function getProjectPresentation(): ?PPBase
    {
        return $this->projectPresentation;
    }

    public function setProjectPresentation(?PPBase $projectPresentation): static
    {
        $this->projectPresentation = $projectPresentation;

        return $this;
    }

    public function __toString(): string
    {
        return $this->title ?? 'Document';
    }

}

