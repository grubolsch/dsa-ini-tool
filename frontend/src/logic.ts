// Pure, framework-free helpers used by the UI and unit tests.

import type { Combatant, HealthBand, LiveEncounter, StatusEffect } from './types';

/**
 * Effects to show in the LIVE status list: hide round-end-only effects (they
 * belong on the end-of-round screen), and order soonest-to-expire on top
 * (ascending durationRounds, stable for ties).
 */
export function liveStatusEffects(effects: StatusEffect[]): StatusEffect[] {
  return effects
    .filter((e) => !e.triggerAtRoundEnd)
    .map((e, i) => [e, i] as const)
    .sort((a, b) => a[0].durationRounds - b[0].durationRounds || a[1] - b[1])
    .map(([e]) => e);
}

export const HEALTH_BAND_LABELS: Record<Exclude<HealthBand, null>, string> = {
  FULL: 'Full health',
  DAMAGED: 'Damaged',
  HEAVILY_DAMAGED: 'Heavily damaged',
  ALMOST_DEFEATED: 'Almost defeated',
};

export function healthBandLabel(band: HealthBand): string | null {
  if (!band) return null;
  return HEALTH_BAND_LABELS[band];
}

// Derive the band locally (mirrors backend §6.1) — useful when only le/maxLe are known.
export function deriveHealthBand(le: number, maxLe: number): HealthBand {
  if (maxLe <= 0) return null;
  if (le <= 0) return null; // dead/removed
  if (le >= maxLe) return 'FULL';
  if (le > 0.5 * maxLe) return 'DAMAGED';
  if (le > 0.25 * maxLe) return 'HEAVILY_DAMAGED';
  return 'ALMOST_DEFEATED';
}

/**
 * Returns the combatants in this round's frozen turn order (living only),
 * following `state.order`. Dead/missing ids are skipped defensively.
 */
export function orderedCombatants(state: LiveEncounter): Combatant[] {
  const byId = new Map(state.combatants.map((c) => [c.id, c]));
  return state.order
    .map((id) => byId.get(id))
    .filter((c): c is Combatant => !!c && !c.isDead);
}

/** The combatant whose turn it is, or null. */
export function activeCombatant(state: LiveEncounter): Combatant | null {
  if (state.activeCombatantId != null) {
    const c = state.combatants.find((x) => x.id === state.activeCombatantId);
    if (c) return c;
  }
  const ordered = orderedCombatants(state);
  return ordered[state.activeIndex] ?? null;
}

/** True if the combatant at `id` is the active one this turn. */
export function isActive(state: LiveEncounter, id: number): boolean {
  const active = activeCombatant(state);
  return !!active && active.id === id;
}

/**
 * Ordering used when we must sort from scratch (no server `order`):
 * highest INI first; PARTY before ENEMIES on a tie; then sortOrder; then id.
 */
export function sortByInitiative(combatants: Combatant[]): Combatant[] {
  const sideRank = (s: Combatant['side']) => (s === 'PARTY' ? 0 : 1);
  return [...combatants].sort((a, b) => {
    if (b.initiative !== a.initiative) return b.initiative - a.initiative;
    if (sideRank(a.side) !== sideRank(b.side))
      return sideRank(a.side) - sideRank(b.side);
    if (a.sortOrder !== b.sortOrder) return a.sortOrder - b.sortOrder;
    return a.id - b.id;
  });
}

export const isHero = (c: Combatant) => c.side === 'PARTY';
export const isEnemy = (c: Combatant) => c.side === 'ENEMIES';
