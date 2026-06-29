import { useEffect, useRef } from 'react';
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
 * laid left→right in this round's frozen order; the active one is big + glows.
 * The rail auto-scrolls to keep the active combatant centered (no scrollbar);
 * off-screen combatants fade at the edges.
 */
export function InitiativeLine({ state, dm, onSelect }: Props) {
  const ordered = orderedCombatants(state);
  const activeId = state.activeCombatantId;
  const trackRef = useRef<HTMLDivElement>(null);
  const activeRef = useRef<HTMLDivElement>(null);

  // Keep the active medallion centered as turns advance / the order changes.
  // Deferred via double rAF so it runs after Framer Motion's layout/size springs
  // have updated positions; scrollIntoView clamps correctly at the ends.
  useEffect(() => {
    if (!activeRef.current) return;
    const raf1 = requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        activeRef.current?.scrollIntoView({
          behavior: 'smooth',
          inline: 'center',
          block: 'nearest',
        });
      });
    });
    return () => cancelAnimationFrame(raf1);
  }, [activeId, ordered.length]);

  return (
    <div className="ini-rail">
      <LayoutGroup>
        <div className="ini-track" ref={trackRef}>
          <AnimatePresence mode="popLayout" initial={false}>
            {ordered.map((c) => (
              <div
                key={c.id}
                ref={c.id === activeId ? activeRef : undefined}
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
        </div>
      </LayoutGroup>
    </div>
  );
}
