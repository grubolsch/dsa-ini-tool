<?php

namespace App\Entity;

use App\Repository\EncounterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EncounterRepository::class)]
#[ORM\Table(name: 'encounter')]
#[ORM\UniqueConstraint(name: 'uniq_encounter_name', columns: ['name'])]
class Encounter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $atmospherePicture = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, EncounterMonster> */
    #[ORM\OneToMany(targetEntity: EncounterMonster::class, mappedBy: 'encounter', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $monsters;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->monsters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getAtmospherePicture(): ?string
    {
        return $this->atmospherePicture;
    }

    public function setAtmospherePicture(?string $atmospherePicture): self
    {
        $this->atmospherePicture = $atmospherePicture;

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

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /** @return Collection<int, EncounterMonster> */
    public function getMonsters(): Collection
    {
        return $this->monsters;
    }

    public function addMonster(EncounterMonster $monster): self
    {
        if (!$this->monsters->contains($monster)) {
            $this->monsters->add($monster);
            $monster->setEncounter($this);
        }

        return $this;
    }

    public function removeMonster(EncounterMonster $monster): self
    {
        $this->monsters->removeElement($monster);

        return $this;
    }
}
