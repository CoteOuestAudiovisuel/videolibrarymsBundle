<?php

namespace Coa\VideolibraryBundle\Entity;


use Coa\VideolibraryBundle\Entity\Traits\Timestampable;
use Coa\VideolibraryBundle\Entity\Scope;
use Coa\VideolibraryBundle\Entity\Video;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity(repositoryClass="Coa\VideolibraryBundle\Repository\ClientRepository")
 * @ORM\Table(name="videolibrary_client")
 * @ORM\HasLifecycleCallbacks()
 * @method string getUserIdentifier()
 */
class Client implements UserInterface
{
    use Timestampable;

    const GRANT_TYPES = [
        'Token' => 'token',
        'Credentials' => 'client_credentials',
        'Password' => 'password',
        'Authorization code' => 'authorization_code',
        'Implicit' => 'implicit'
    ];

    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $clientId;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $clientSecret;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $clientToken;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $routingSuffix;

    /**
     * @ORM\ManyToMany(targetEntity="GrantType", inversedBy="clients")
     * @ORM\JoinTable("videolibrary_client_grant_type")
     */
    private $grantTypes;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $domain;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $postbackUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $hlsKeyBaseurl;

    /**
     * @ORM\ManyToMany(targetEntity="Scope", inversedBy="clients")
     * @ORM\JoinTable("videolibrary_client_scope")
     */
    private $scopes;

    /**
     * @ORM\OneToMany(targetEntity=AccessToken::class, mappedBy="client")
     */
    private $accessTokens;

    /**
     * @ORM\OneToMany(targetEntity=Video::class, mappedBy="client")
     */
    private $videos;

    private $roles;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isEnabled;

    public function __construct()
    {
        $this->scopes = new ArrayCollection();
        $this->accessTokens = new ArrayCollection();
        $this->videos = new ArrayCollection();
        $this->roles = [];
    }

    /**
     * @return int
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @param mixed $name
     * @return Client
     */
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param mixed $clientId
     * @return Client
     */
    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * @param mixed $clientSecret
     * @return Client
     */
    public function setClientSecret(?string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    /**
     * @return string
     */
    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * @param mixed $routingSuffix
     * @return Client
     */
    public function setRoutingSuffix(string $routingSuffix): self
    {
        $this->routingSuffix = $routingSuffix;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRoutingSuffix(): string
    {
        return $this->routingSuffix;
    }

    /**
     * @param mixed $clientToken
     * @return Client
     */
    public function setClientToken(?string $clientToken): self
    {
        $this->clientToken = $clientToken;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClientToken(): ?string
    {
        return $this->clientToken;
    }



    /**
     * @return Collection|GrantType[]
     */
    public function getGrantTypes(): ?Collection
    {
        if($this->grantTypes) {
            $criteria = Criteria::create()
                ->andWhere(Criteria::expr()->eq('isEnabled', true));

            return $this->grantTypes->matching($criteria);
        }
        return null;
    }

    public function addGrantType(GrantType $grantType): self
    {
        if($this->grantTypes) {
            if (!$this->grantTypes->contains($grantType)) {
                $this->grantTypes[] = $grantType;
            }
        } else {
            $this->grantTypes[] = $grantType;
        }

        return $this;
    }

    public function removeGrantType(GrantType $grantType): self
    {
        $this->grantTypes->removeElement($grantType);

        return $this;
    }

    /**
     * @param mixed $postbackUrl
     * @return Client
     */
    public function setPostbackUrl(?string $postbackUrl): self
    {
        $this->postbackUrl = $postbackUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getPostbackUrl(): ?string
    {
        return $this->postbackUrl;
    }

    /**
     * @param ArrayCollection $accessTokens
     * @return Client
     */
    public function setAccessTokens(ArrayCollection $accessTokens): self
    {
        $this->accessTokens = $accessTokens;
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getAccessTokens(): ArrayCollection
    {
        return $this->accessTokens;
    }


    /**
     * @return Collection|Scope[]
     */
    public function getScopes(): Collection
    {
        $criteria = Criteria::create()
            ->andWhere(Criteria::expr()->eq('isEnabled', true));

        return $this->scopes->matching($criteria);
    }

    public function addScope(Scope $scope): self
    {
        if (!$this->scopes->contains($scope)) {
            $this->scopes[] = $scope;
        }

        return $this;
    }

    public function removeScope(Scope $scope): self
    {
        $this->scopes->removeElement($scope);

        return $this;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getRoles()
    {
        return $this->roles;
    }

    public function getPassword()
    {
        return '';
    }

    public function getSalt()
    {
        return '';
    }

    public function eraseCredentials()
    {
        return '';
    }

    public function getUsername()
    {
        return $this->name;
    }

    /**
     * @param mixed $videos
     */
    public function setVideos($videos): self
    {
        $this->videos = $videos;
        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getVideos(): ArrayCollection
    {
        return $this->videos;
    }

    /**
     * @param mixed $isEnabled
     */
    public function setIsEnabled($isEnabled): self
    {
        $this->isEnabled = $isEnabled;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsEnabled(): ?bool
    {
        return filter_var($this->isEnabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param mixed $domain
     */
    public function setDomain($domain): self
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * @return string
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * @param mixed $hlsKeyBaseurl
     */
    public function setHlsKeyBaseurl($hlsKeyBaseurl): self
    {
        $this->hlsKeyBaseurl = $hlsKeyBaseurl;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHlsKeyBaseurl(): ?string
    {
        return $this->hlsKeyBaseurl;
    }
}