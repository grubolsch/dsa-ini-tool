<?php

namespace App\Entity;

use App\Repository\StatusEffectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatusEffectRepository::class)]
#[ORM\Table(name: 'status_effect')]
class StatusEffect
{
    public const GROUP_ALL_ENEMIES = 'ALL_ENEMIES';
    public const GROUP_ALL_HEROES = 'ALL_HEROES';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Combatant::class, inversedBy: 'statusEffects')]
    #[ORM\JoinColumn(name: 'combatant_id', nullable: false, onDelete: 'CASCADE')]
    private ?Combatant $combatant = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $durationRounds = 1;

    #[ORM\Column]
    private bool $triggerAtRoundEnd = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $groupTag = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCombatant(): ?Combatant
    {
        return $this->combatant;
    }

    public function setCombatant(?Combatant $combatant): self
    {
        $this->combatant = $combatant;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getDurationRounds(): int
    {
        return $this->durationRounds;
    }

    public function setDurationRounds(int $durationRounds): self
    {
        $this->durationRounds = $durationRounds;

        return $this;
    }

    public function isTriggerAtRoundEnd(): bool
    {
        return $this->triggerAtRoundEnd;
    }

    public function setTriggerAtRoundEnd(bool $triggerAtRoundEnd): self
    {
        $this->triggerAtRoundEnd = $triggerAtRoundEnd;

        return $this;
    }

    public function getGroupTag(): ?string
    {
        return $this->groupTag;
    }

    public function setGroupTag(?string $groupTag): self
    {
        $this->groupTag = $groupTag;

        return $this;
    }
}
