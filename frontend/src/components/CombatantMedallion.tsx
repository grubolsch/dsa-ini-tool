import { motion } from 'framer-motion';
import type { Combatant } from '../types';
import { healthBandLabel } from '../logic';
import { Portrait } from './Portrait';

interface Props {
  combatant: Combatant;
  active: boolean;
  dm: boolean;
}

const INACTIVE = 64;
const ACTIVE = 104;

/**
 * A single framed portrait medallion on the initiative rail. Active = big +
 * glow; uses layout animation so reordering/removal animates smoothly. The
 * exit (death) animation is dramatic for enemies (shatter/fall) and gentle for
 * heroes leaving.
 */
export function CombatantMedallion({ combatant, active, dm }: Props) {
  const size = active ? ACTIVE : INACTIVE;
  const isEnemy = combatant.side === 'ENEMIES';
  const band = combatant.healthBand;

  return (
    <motion.div
      layout
      layoutId={`medallion-${combatant.id}`}
      className={[
        'medallion',
        `side-${combatant.side}`,
        active ? 'active' : '',
        combatant.isOutOfCombat ? 'out' : '',
      ]
        .join(' ')
        .trim()}
      initial={{ opacity: 0, scale: 0.4 }}
      animate={{ opacity: 1, scale: 1 }}
      exit={
        isEnemy
          ? { opacity: 0, scale: 0.3, rotate: 35, y: 90, filter: 'blur(4px)' }
          : { opacity: 0, scale: 0.5 }
      }
      transition={{ type: 'spring', stiffness: 320, damping: 30 }}
    >
      <motion.div
        className="frame"
        layout
        animate={{ width: size, height: size }}
        transition={{ type: 'spring', stiffness: 300, damping: 28 }}
      >
        <span className="ini-badge" title="Initiative">
          {combatant.initiative}
        </span>
        <Portrait src={combatant.picture} name={combatant.displayName} className="" />
      </motion.div>

      <div className="name">{combatant.displayName}</div>

      {/* Enemy health: band only (players); DM also gets raw numbers. */}
      {isEnemy && band && (
        <div className={`health-band band-${band}`}>
          {healthBandLabel(band)}
          {dm && combatant.le != null && combatant.maxLe != null && (
            <span style={{ color: 'var(--parchment)' }}>
              {' '}
              ({combatant.le}/{combatant.maxLe})
            </span>
          )}
        </div>
      )}

      {dm && combatant.iniChangedThisRound && (
        <div className="pending-flag">INI ↻ next round</div>
      )}
      {combatant.isOutOfCombat && (
        <div className="pending-flag" style={{ color: 'var(--iron-light)' }}>
          out of combat
        </div>
      )}
    </motion.div>
  );
}
