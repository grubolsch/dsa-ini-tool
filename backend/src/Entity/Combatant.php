<?php

namespace App\Entity;

use App\Repository\CombatantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CombatantRepository::class)]
#[ORM\Table(name: 'combatant')]
class Combatant
{
    public const SIDE_PARTY = 'PARTY';
    public const SIDE_ENEMIES = 'ENEMIES';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LiveEncounter::class, inversedBy: 'combatants')]
    #[ORM\JoinColumn(name: 'live_encounter_id', nullable: false, onDelete: 'CASCADE')]
    private ?LiveEncounter $liveEncounter = null;

    #[ORM\Column(length: 20)]
    private string $side = self::SIDE_ENEMIES;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $picture = null;

    // Rolled initiative for THIS fight.
    #[ORM\Column]
    private int $initiative = 0;

    // Enemies only; null for heroes (heroes have no health).
    #[ORM\Column(nullable: true)]
    private ?int $le = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxLe = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $isDead = false;

    #[ORM\Column]
    private bool $isOutOfCombat = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    // Set when DM edits INI mid-round; applied at next round.
    #[ORM\Column(nullable: true)]
    private ?int $pendingInitiative = null;

    // Flags that this combatant changed INI this round and must not act again.
    #[ORM\Column]
    private bool $iniChangedThisRound = false;

    /** @var Collection<int, StatusEffect> */
    #[ORM\OneToMany(targetEntity: StatusEffect::class, mappedBy: 'combatant', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $statusEffects;

    public function __construct()
    {
        $this->statusEffects = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLiveEncounter(): ?LiveEncounter
    {
        return $this->liveEncounter;
    }

    public function setLiveEncounter(?LiveEncounter $liveEncounter): self
    {
        $this->liveEncounter = $liveEncounter;

        return $this;
    }

    public function getSide(): string
    {
        return $this->side;
    }

    public function setSide(string $side): self
    {
        $this->side = $side;

        return $this;
    }

    public function isParty(): bool
    {
        return self::SIDE_PARTY === $this->side;
    }

    public function isEnemy(): bool
    {
        return self::SIDE_ENEMIES === $this->side;
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

    public function getLe(): ?int
    {
        return $this->le;
    }

    public function setLe(?int $le): self
    {
        $this->le = $le;

        return $this;
    }

    public function getMaxLe(): ?int
    {
        return $this->maxLe;
    }

    public function setMaxLe(?int $maxLe): self
    {
        $this->maxLe = $maxLe;

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

    public function isDead(): bool
    {
        return $this->isDead;
    }

    public function setIsDead(bool $isDead): self
    {
        $this->isDead = $isDead;

        return $this;
    }

    public function isOutOfCombat(): bool
    {
        return $this->isOutOfCombat;
    }

    public function setIsOutOfCombat(bool $isOutOfCombat): self
    {
        $this->isOutOfCombat = $isOutOfCombat;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): self
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function getPendingInitiative(): ?int
    {
        return $this->pendingInitiative;
    }

    public function setPendingInitiative(?int $pendingInitiative): self
    {
        $this->pendingInitiative = $pendingInitiative;

        return $this;
    }

    public function isIniChangedThisRound(): bool
    {
        return $this->iniChangedThisRound;
    }

    public function setIniChangedThisRound(bool $iniChangedThisRound): self
    {
        $this->iniChangedThisRound = $iniChangedThisRound;

        return $this;
    }

    /** @return Collection<int, StatusEffect> */
    public function getStatusEffects(): Collection
    {
        return $this->statusEffects;
    }

    public function addStatusEffect(StatusEffect $statusEffect): self
    {
        if (!$this->statusEffects->contains($statusEffect)) {
            $this->statusEffects->add($statusEffect);
            $statusEffect->setCombatant($this);
        }

        return $this;
    }

    public function removeStatusEffect(StatusEffect $statusEffect): self
    {
        $this->statusEffects->removeElement($statusEffect);

        return $this;
    }
}
