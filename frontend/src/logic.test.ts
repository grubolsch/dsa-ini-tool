import { describe, expect, it } from 'vitest';
import {
  activeCombatant,
  deriveHealthBand,
  healthBandLabel,
  isActive,
  orderedCombatants,
  sortByInitiative,
} from './logic';
import type { Combatant, LiveEncounter } from './types';

function enemy(id: number, ini: number, over: Partial<Combatant> = {}): Combatant {
  return {
    id,
    side: 'ENEMIES',
    name: `Enemy ${id}`,
    displayName: `Enemy ${id}`,
    picture: null,
    initiative: ini,
    le: 10,
    maxLe: 10,
    description: '',
    isDead: false,
    isOutOfCombat: false,
    sortOrder: id,
    iniChangedThisRound: false,
    healthBand: 'FULL',
    statusEffects: [],
    ...over,
  };
}

function hero(id: number, ini: number, over: Partial<Combatant> = {}): Combatant {
  return {
    id,
    side: 'PARTY',
    name: `Hero ${id}`,
    displayName: `Hero ${id}`,
    picture: null,
    initiative: ini,
    le: null,
    maxLe: null,
    description: null,
    isDead: false,
    isOutOfCombat: false,
    sortOrder: id,
    iniChangedThisRound: false,
    healthBand: null,
    statusEffects: [],
    ...over,
  };
}

function makeState(combatants: Combatant[], over: Partial<LiveEncounter> = {}): LiveEncounter {
  return {
    code: 'A1B2C3D4',
    encounter: { id: 1, name: 'Test', atmospherePicture: null },
    round: 1,
    activeIndex: 0,
    phase: 'COMBAT',
    order: combatants.filter((c) => !c.isDead).map((c) => c.id),
    activeCombatantId: combatants.find((c) => !c.isDead)?.id ?? null,
    combatants,
    roundEndEffects: [],
    ...over,
  };
}

describe('health band labels', () => {
  it('maps every band to its player label', () => {
    expect(healthBandLabel('FULL')).toBe('Full health');
    expect(healthBandLabel('DAMAGED')).toBe('Damaged');
    expect(healthBandLabel('HEAVILY_DAMAGED')).toBe('Heavily damaged');
    expect(healthBandLabel('ALMOST_DEFEATED')).toBe('Almost defeated');
    expect(healthBandLabel(null)).toBeNull();
  });
});

describe('deriveHealthBand (mirrors §6.1)', () => {
  it('classifies by fraction of max LE', () => {
    expect(deriveHealthBand(10, 10)).toBe('FULL');
    expect(deriveHealthBand(6, 10)).toBe('DAMAGED'); // > 50%
    expect(deriveHealthBand(5, 10)).toBe('HEAVILY_DAMAGED'); // > 25%, <= 50%
    expect(deriveHealthBand(2, 10)).toBe('ALMOST_DEFEATED'); // > 0, <= 25%
    expect(deriveHealthBand(0, 10)).toBeNull(); // dead/removed
  });
});

describe('sortByInitiative', () => {
  it('orders highest INI first, heroes before enemies on a tie', () => {
    const list = [enemy(2, 12), hero(1, 12), enemy(3, 15)];
    const sorted = sortByInitiative(list);
    expect(sorted.map((c) => c.id)).toEqual([3, 1, 2]);
  });
});

describe('orderedCombatants & active logic', () => {
  it('follows state.order and skips dead combatants', () => {
    const combatants = [enemy(100, 11), enemy(101, 9, { isDead: true }), hero(102, 14)];
    const state = makeState(combatants, { order: [102, 100, 101], activeCombatantId: 102 });
    const ordered = orderedCombatants(state);
    expect(ordered.map((c) => c.id)).toEqual([102, 100]); // dead 101 dropped
  });

  it('resolves the active combatant by id', () => {
    const combatants = [hero(102, 14), enemy(100, 11)];
    const state = makeState(combatants, { order: [102, 100], activeCombatantId: 100, activeIndex: 1 });
    expect(activeCombatant(state)?.id).toBe(100);
    expect(isActive(state, 100)).toBe(true);
    expect(isActive(state, 102)).toBe(false);
  });

  it('falls back to activeIndex when id is missing', () => {
    const combatants = [hero(1, 14), enemy(2, 11)];
    const state = makeState(combatants, { order: [1, 2], activeCombatantId: null, activeIndex: 1 });
    expect(activeCombatant(state)?.id).toBe(2);
  });
});
