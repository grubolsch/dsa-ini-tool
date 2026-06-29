import { AnimatePresence, motion } from 'framer-motion';
import type { RoundEndEffect } from '../types';

interface Props {
  show: boolean;
  round: number;
  effects: RoundEndEffect[];
  dm: boolean;
  onNextRound?: () => void;
  busy?: boolean;
}

/**
 * Dramatic full-screen "End of round" banner. Lists round-end effects using the
 * server-provided label (already "All enemies"/"All heroes"/a name). DM gets a
 * "Begin next round" button.
 */
export function EndOfRoundScreen({
  show,
  round,
  effects,
  dm,
  onNextRound,
  busy,
}: Props) {
  return (
    <AnimatePresence>
      {show && (
        <motion.div
          className="eor-backdrop"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
        >
          <motion.div
            className="parchment eor-card"
            initial={{ scale: 0.7, rotateX: 40, opacity: 0 }}
            animate={{ scale: 1, rotateX: 0, opacity: 1 }}
            exit={{ scale: 0.7, opacity: 0 }}
            transition={{ type: 'spring', stiffness: 200, damping: 22 }}
          >
            <motion.div
              className="eor-title"
              initial={{ y: -30, opacity: 0 }}
              animate={{ y: 0, opacity: 1 }}
              transition={{ delay: 0.15 }}
            >
              End of round {round}
            </motion.div>

            {effects.length > 0 ? (
              <>
                <p className="display">Effects trigger at round&rsquo;s end:</p>
                <ul className="eor-effects">
                  {effects.map((e, i) => (
                    <motion.li
                      key={`${e.label}-${e.name}-${i}`}
                      initial={{ x: -40, opacity: 0 }}
                      animate={{ x: 0, opacity: 1 }}
                      transition={{ delay: 0.25 + i * 0.08 }}
                    >
                      <span className="label">{e.label}:</span>
                      <strong>{e.name}</strong>
                      {e.description && <> — {e.description}</>}
                      <span className="muted"> ({e.durationRounds} left)</span>
                    </motion.li>
                  ))}
                </ul>
              </>
            ) : (
              <p className="muted">The dust settles. No lingering effects.</p>
            )}

            {dm ? (
              <button
                className="btn btn-primary"
                onClick={onNextRound}
                disabled={busy}
                style={{ fontSize: '1.2rem', padding: '0.9rem 2rem' }}
              >
                {busy ? 'Advancing…' : 'Begin next round ⚔'}
              </button>
            ) : (
              <p className="muted">Waiting for the Dungeon Master…</p>
            )}
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
