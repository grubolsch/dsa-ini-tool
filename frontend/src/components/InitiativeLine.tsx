import { AnimatePresence, LayoutGroup } from 'framer-motion';
import type { LiveEncounter } from '../types';
import { orderedCombatants } from '../logic';
import { CombatantMedallion } from './CombatantMedallion';

interface Props {
  state: LiveEncounter;
  dm: boolean;
  onSelect?: (combatantId: number) => void;
}

/**
 * The headline feature: the ornate horizontal initiative rail. Combatants are
 * laid left→right in this round's frozen order; the active one is big + glows;
 * the end of the line marks the end of the round.
 */
export function InitiativeLine({ state, dm, onSelect }: Props) {
  const ordered = orderedCombatants(state);
  const activeId = state.activeCombatantId;

  return (
    <div className="ini-rail">
      <LayoutGroup>
        <div className="ini-track">
          <AnimatePresence mode="popLayout" initial={false}>
            {ordered.map((c) => (
              <div
                key={c.id}
                onClick={() => onSelect?.(c.id)}
                style={{ cursor: onSelect ? 'pointer' : 'default' }}
              >
                <CombatantMedallion
                  combatant={c}
                  active={c.id === activeId}
                  dm={dm}
                />
              </div>
            ))}
          </AnimatePresence>
          <div className="ini-end" aria-hidden>
            ⚔
            <div style={{ fontSize: '0.7rem' }}>end of round</div>
          </div>
        </div>
      </LayoutGroup>
    </div>
  );
}
