<?php

namespace Coa\VideolibraryBundle\Entity;


use Coa\VideolibraryBundle\Entity\Traits\Timestampable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Coa\VideolibraryBundle\Repository\AccessTokenRepository")
 * @ORM\Table(name="videolibrary_access_token")
 * @ORM\HasLifecycleCallbacks()
 */
class AccessToken
{
    use Timestampable;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $accessToken;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $tokenType;

    /**
     * @ORM\Column(type="datetime_immutable", nullable=true)
     */
    private $expiresIn;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $refreshToken;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $scope;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $revoked;

    /**
     * @ORM\ManyToOne(targetEntity=Client::class, inversedBy="accessTokens")
     */
    private $client;

    public function __construct()
    {
        $this->revoked = false;
    }


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param mixed $client
     * @return AccessToken
     */
    public function setClient(Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * @param mixed $accessToken
     * @return AccessToken
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    /**
     * @param mixed $tokenType
     * @return AccessToken
     */
    public function setTokenType(string $tokenType): self
    {
        $this->tokenType = $tokenType;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    /**
     * @param mixed $expiresIn
     * @return AccessToken
     */
    public function setExpiresIn(\DateTimeImmutable $expiresIn): self
    {
        $this->expiresIn = $expiresIn;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExpiresIn(): \DateTimeImmutable
    {
        return $this->expiresIn;
    }

    /**
     * @param mixed $refreshToken
     * @return AccessToken
     */
    public function setRefreshToken(?string $refreshToken): self
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    /**
     * @param mixed $scope
     * @return AccessToken
     */
    public function setScope(?string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getScope(): string
    {
        return $this->scope;
    }

    /**
     * @param mixed $revoked
     * @return AccessToken
     */
    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRevoked(): bool
    {
        return $this->revoked;
    }

}