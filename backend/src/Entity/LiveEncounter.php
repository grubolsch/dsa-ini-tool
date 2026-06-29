<?php

namespace App\Entity;

use App\Repository\LiveEncounterRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LiveEncounterRepository::class)]
#[ORM\Table(name: 'live_encounter')]
#[ORM\UniqueConstraint(name: 'uniq_live_code', columns: ['code'])]
class LiveEncounter
{
    public const PHASE_COMBAT = 'COMBAT';
    public const PHASE_END_OF_ROUND = 'END_OF_ROUND';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 8, unique: true)]
    private string $code = '';

    #[ORM\ManyToOne(targetEntity: Encounter::class)]
    #[ORM\JoinColumn(name: 'encounter_id', nullable: false, onDelete: 'CASCADE')]
    private ?Encounter $encounter = null;

    #[ORM\Column]
    private int $round = 1;

    #[ORM\Column]
    private int $activeIndex = 0;

    #[ORM\Column(length: 20)]
    private string $phase = self::PHASE_COMBAT;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Combatant> */
    #[ORM\OneToMany(targetEntity: Combatant::class, mappedBy: 'liveEncounter', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $combatants;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->combatants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
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

    public function getRound(): int
    {
        return $this->round;
    }

    public function setRound(int $round): self
    {
        $this->round = $round;

        return $this;
    }

    public function getActiveIndex(): int
    {
        return $this->activeIndex;
    }

    public function setActiveIndex(int $activeIndex): self
    {
        $this->activeIndex = $activeIndex;

        return $this;
    }

    public function getPhase(): string
    {
        return $this->phase;
    }

    public function setPhase(string $phase): self
    {
        $this->phase = $phase;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Combatant> */
    public function getCombatants(): Collection
    {
        return $this->combatants;
    }

    public function addCombatant(Combatant $combatant): self
    {
        if (!$this->combatants->contains($combatant)) {
            $this->combatants->add($combatant);
            $combatant->setLiveEncounter($this);
        }

        return $this;
    }

    public function removeCombatant(Combatant $combatant): self
    {
        $this->combatants->removeElement($combatant);

        return $this;
    }
}
