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
 * The rail auto-scrolls to keep the active combatant centered; off-screen
 * combatants fade at the edges.
 */
export function InitiativeLine({ state, dm, onSelect }: Props) {
  const ordered = orderedCombatants(state);
  const activeId = state.activeCombatantId;
  const trackRef = useRef<HTMLDivElement>(null);

  // Keep the active medallion centered as turns advance / the order changes.
  // We look the element up by data-cid (rather than a conditional ref, which is
  // fragile across Framer Motion's layout commits) and scroll the track directly.
  // Deferred via double rAF so it runs after the layout/size springs settle.
  useEffect(() => {
    const track = trackRef.current;
    if (!track || activeId == null) return;
    const raf1 = requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        const el = track.querySelector<HTMLElement>(`[data-cid="${activeId}"]`);
        if (!el) return;
        const left = el.offsetLeft - track.clientWidth / 2 + el.offsetWidth / 2;
        track.scrollTo({ left: Math.max(0, left), behavior: 'smooth' });
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
                data-cid={c.id}
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
