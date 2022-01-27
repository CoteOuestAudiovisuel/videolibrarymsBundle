<?php

namespace Coa\VideolibraryBundle\Entity;

use App\Entity\User;
use Coa\VideolibraryBundle\Repository\VideoRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\MappedSuperclass;

/**
 * @MappedSuperclass
 */
abstract class Video
{
    /**
     * @ORM\Column(type="string", length=64)
     */
    private $code;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $originalFilename;

    /**
     * @ORM\Column(type="integer")
     */
    private $fileSize;

    /**
     * @ORM\Column(type="string", length=15)
     */
    private $state;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isTranscoded;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $poster;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $screenshots = [];

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $webvtt;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $manifest;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    private $duration;


    /**
     * @ORM\Column(type="datetime_immutable")
     */
    private $createdAt;

    /**
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="videos")
     */
    private $author;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $jobRef;

    /**
     * @ORM\Column(type="json", nullable=true)
     */
    private $variants = [];

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $jobStartTime;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $jobSubmitTime;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $jobFinishTime;

    /**
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    private $bucket;

    /**
     * @ORM\Column(type="string", length=50, nullable=true)
     */
    private $region;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $jobPercent;


    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): self
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getIsTranscoded(): ?bool
    {
        return $this->isTranscoded;
    }

    public function setIsTranscoded(bool $isTranscoded): self
    {
        $this->isTranscoded = $isTranscoded;
        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(?string $poster): self
    {
        $this->poster = $poster;

        return $this;
    }

    public function getScreenshots(): ?array
    {
        return $this->screenshots;
    }

    public function setScreenshots(?array $screenshots): self
    {
        $this->screenshots = $screenshots;

        return $this;
    }

    public function getWebvtt(): ?string
    {
        return $this->webvtt;
    }

    public function setWebvtt(?string $webvtt): self
    {
        $this->webvtt = $webvtt;

        return $this;
    }

    public function getManifest(): ?string
    {
        return $this->manifest;
    }

    public function setManifest(?string $manifest): self
    {
        $this->manifest = $manifest;

        return $this;
    }

    public function getDuration(): ?float
    {
        return $this->duration;
    }

    public function setDuration(?float $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getJobRef(): ?string
    {
        return $this->jobRef;
    }

    public function setJobRef(?string $jobRef): self
    {
        $this->jobRef = $jobRef;

        return $this;
    }

    public function getVariants(): ?array
    {
        return $this->variants;
    }

    public function setVariants(?array $variants): self
    {
        $this->variants = $variants;

        return $this;
    }

    public function getJobStartTime(): ?\DateTimeImmutable
    {
        return $this->jobStartTime;
    }

    public function setJobStartTime(?\DateTimeImmutable $jobStartTime): self
    {
        $this->jobStartTime = $jobStartTime;

        return $this;
    }

    public function getJobSubmitTime(): ?\DateTimeImmutable
    {
        return $this->jobSubmitTime;
    }

    public function setJobSubmitTime(?\DateTimeImmutable $jobSubmitTime): self
    {
        $this->jobSubmitTime = $jobSubmitTime;

        return $this;
    }

    public function getJobFinishTime(): ?\DateTimeImmutable
    {
        return $this->jobFinishTime;
    }

    public function setJobFinishTime(?\DateTimeImmutable $jobFinishTime): self
    {
        $this->jobFinishTime = $jobFinishTime;

        return $this;
    }

    public function getBucket(): ?string
    {
        return $this->bucket;
    }

    public function setBucket(?string $bucket): self
    {
        $this->bucket = $bucket;

        return $this;
    }


    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): self
    {
        $this->region = $region;
        return $this;
    }

    public function getJobPercent(): ?int
    {
        return $this->jobPercent;
    }

    public function setJobPercent(?int $jobPercent): self
    {
        $this->jobPercent = $jobPercent;

        return $this;
    }
}
