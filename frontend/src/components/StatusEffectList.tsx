import { AnimatePresence, motion } from 'framer-motion';
import { faTrash } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import type { StatusEffect } from '../types';
import { liveStatusEffects } from '../logic';

interface Props {
  effects: StatusEffect[];
  dm: boolean;
  onRemove?: (id: number) => void;
}

function groupLabel(tag: StatusEffect['groupTag']): string | null {
  if (tag === 'ALL_ENEMIES') return 'All enemies';
  if (tag === 'ALL_HEROES') return 'All heroes';
  return null;
}

/** Big bulleted list of the active character's status effects, with rounds left. */
export function StatusEffectList({ effects, dm, onRemove }: Props) {
  const visible = liveStatusEffects(effects);
  if (visible.length === 0) {
    return <p className="muted">No active effects.</p>;
  }
  return (
    <ul className="status-list">
      <AnimatePresence initial={false}>
        {visible.map((e) => (
          <motion.li
            key={e.id}
            layout
            initial={{ opacity: 0, scale: 0.6, x: -20 }}
            animate={{ opacity: 1, scale: 1, x: 0 }}
            exit={{ opacity: 0, scale: 0.6 }}
            transition={{ type: 'spring', stiffness: 320, damping: 24 }}
          >
            {dm && onRemove && (
              <button
                className="se-delete"
                title={`Remove ${e.name}`}
                aria-label={`Remove ${e.name}`}
                onClick={() => onRemove(e.id)}
              >
                <FontAwesomeIcon icon={faTrash} />
              </button>
            )}
            <span className="se-rounds">
              {e.durationRounds} {e.durationRounds === 1 ? 'round' : 'rounds'}
            </span>
            <div className="se-name">{e.name}</div>
            {e.description && <div>{e.description}</div>}
            <div className="se-tag">
              {e.triggerAtRoundEnd && '⏳ triggers at round end'}
              {groupLabel(e.groupTag) && (
                <> {e.triggerAtRoundEnd ? '· ' : ''}({groupLabel(e.groupTag)})</>
              )}
            </div>
          </motion.li>
        ))}
      </AnimatePresence>
    </ul>
  );
}
