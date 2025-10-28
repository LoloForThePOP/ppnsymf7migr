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

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $position = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(length: 255)]
    private string $fileName;

    /**
     * Virtual field used by VichUploader.
     * 
     * @var File|null
     */
    #[Vich\UploadableField(mapping: 'presentation_document_file', fileNameProperty: 'fileName')]
    #[Assert\File(
        maxSize: '13000k',
        maxSizeMessage: 'Ce fichier dépasse la limite de poids maximal accepté : {{ limit }} {{ suffix }}',
        mimeTypes: [
            'application/pdf',
            'application/x-pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/rtf',
            'application/vnd.oasis.opendocument.text',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/epub+zip',
            'text/plain'
        ],
        mimeTypesMessage: 'Veuillez sélectionner un fichier de type PDF, Word, Excel, PowerPoint, OpenDocument, ePub, RTF ou texte.'
    )]
    private ?File $file = null;

    #[ORM\ManyToOne(inversedBy: 'documents')]
    private ?PPBase $projectPresentation = null;

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

    // ─────────────────────────────────────────────
    // HELPER METHODS
    // ─────────────────────────────────────────────

    public function getReadableSize(string $uploadDir): ?string
    {
        $filePath = $uploadDir . '/' . $this->fileName;

        if (!file_exists($filePath)) {
            return null;
        }

        $bytes = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    public function __toString(): string
    {
        return $this->title ?? 'Document';
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


}

