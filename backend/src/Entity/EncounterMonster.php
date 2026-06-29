<?php

namespace App\Entity;

use App\Repository\EncounterMonsterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EncounterMonsterRepository::class)]
#[ORM\Table(name: 'encounter_monster')]
class EncounterMonster
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Encounter::class, inversedBy: 'monsters')]
    #[ORM\JoinColumn(name: 'encounter_id', nullable: false, onDelete: 'CASCADE')]
    private ?Encounter $encounter = null;

    // SET NULL: deleting the template keeps the snapshot row, only nulls the link.
    #[ORM\ManyToOne(targetEntity: MonsterTemplate::class)]
    #[ORM\JoinColumn(name: 'monster_template_id', nullable: true, onDelete: 'SET NULL')]
    private ?MonsterTemplate $monsterTemplate = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $picture = null;

    #[ORM\Column]
    private int $initiative = 0;

    #[ORM\Column]
    private int $le = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEncounter(): ?Encounter
    {
        return $this->encounter;
    }

    public function setEncounter(?Encounter $encounter): self
    {
        $this->encounter = $encounter;

        return $this;
    }

    public function getMonsterTemplate(): ?MonsterTemplate
    {
        return $this->monsterTemplate;
    }

    public function setMonsterTemplate(?MonsterTemplate $monsterTemplate): self
    {
        $this->monsterTemplate = $monsterTemplate;

        return $this;
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

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture): self
    {
        $this->picture = $picture;

        return $this;
    }

    public function getInitiative(): int
    {
        return $this->initiative;
    }

    public function setInitiative(int $initiative): self
    {
        $this->initiative = $initiative;

        return $this;
    }

    public function getLe(): int
    {
        return $this->le;
    }

    public function setLe(int $le): self
    {
        $this->le = $le;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }
}
