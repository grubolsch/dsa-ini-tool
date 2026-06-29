// TypeScript types mirroring the API CONTRACT shapes.

export type Side = 'PARTY' | 'ENEMIES';
export type Phase = 'COMBAT' | 'END_OF_ROUND';
export type GroupTag = null | 'ALL_ENEMIES' | 'ALL_HEROES';
export type HealthBand =
  | 'FULL'
  | 'DAMAGED'
  | 'HEAVILY_DAMAGED'
  | 'ALMOST_DEFEATED'
  | null;

export interface Hero {
  id: number;
  name: string;
  picture: string | null;
  initiative: number;
}

export interface MonsterTemplate {
  id: number;
  name: string;
  picture: string | null;
  initiative: number;
  le: number;
  description: string;
}

export interface EncounterSummary {
  id: number;
  name: string;
  atmospherePicture: string | null;
  createdAt: string;
  updatedAt: string;
  monsterCount: number;
}

export interface EncounterMonster {
  id: number;
  name: string;
  picture: string | null;
  initiative: number;
  le: number;
  description: string;
  monsterTemplateId: number | null;
}

export interface EncounterDetail {
  id: number;
  name: string;
  atmospherePicture: string | null;
  createdAt: string;
  updatedAt: string;
  monsters: EncounterMonster[];
}

export interface StatusEffect {
  id: number;
  name: string;
  description: string;
  durationRounds: number;
  triggerAtRoundEnd: boolean;
  groupTag: GroupTag;
}

export interface Combatant {
  id: number;
  side: Side;
  name: string;
  /** Name disambiguated with "#n" when several combatants share a name (e.g. "Goblin #2"). */
  displayName: string;
  picture: string | null;
  initiative: number;
  le: number | null;
  maxLe: number | null;
  description: string | null;
  isDead: boolean;
  isOutOfCombat: boolean;
  sortOrder: number;
  iniChangedThisRound: boolean;
  healthBand: HealthBand;
  statusEffects: StatusEffect[];
}

export interface RoundEndEffect {
  label: string;
  name: string;
  description: string;
  durationRounds: number;
}

export interface LiveEncounter {
  code: string;
  encounter: {
    id: number;
    name: string;
    atmospherePicture: string | null;
  };
  round: number;
  activeIndex: number;
  phase: Phase;
  order: number[];
  activeCombatantId: number | null;
  combatants: Combatant[];
  roundEndEffects: RoundEndEffect[];
}

export interface StatusEffectTargets {
  combatantIds: number[];
  allEnemies: boolean;
  allHeroes: boolean;
}

export interface StatusEffectRequest {
  name: string;
  description: string;
  durationRounds: number;
  triggerAtRoundEnd: boolean;
  targets: StatusEffectTargets;
}

export interface GalleryImage {
  /** Filename in public/gallery, sent back as `galleryImage` when chosen. */
  name: string;
  /** Renderable URL (bmp is converted to PNG server-side). */
  url: string;
}
