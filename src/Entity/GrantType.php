<?php

namespace Coa\VideolibraryBundle\Entity;


use Coa\VideolibraryBundle\Entity\Traits\Timestampable;
use Coa\VideolibraryBundle\Entity\Client;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="Coa\VideolibraryBundle\Repository\GrantTypeRepository")
 * @ORM\Table(name="videolibrary_grant_type")
 * @ORM\HasLifecycleCallbacks()
 */
class GrantType
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
    private $name;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $description;

    /**
     * @ORM\ManyToMany(targetEntity="Client", mappedBy="grantTypes")
     */
    private $clients;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $isEnabled;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->isEnabled = true;
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
     * @param mixed $label
     */
    public function setLabel($label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param mixed $description
     */
    public function setDescription($description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }


    /**
     * @return Collection|Client []
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): self
    {
        if (!$this->clients->contains($client)) {
            $this->clients[] = $client;
            $client->addGrantType($this);
        }

        return $this;
    }

    public function removeClient(Client $client): self
    {
        if ($this->clients->removeElement($client)) {
            $client->removeGrantType($this);
        }

        return $this;
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

}