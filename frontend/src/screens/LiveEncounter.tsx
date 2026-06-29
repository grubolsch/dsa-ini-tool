import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import {
  addCombatant,
  addStatusEffect,
  deleteStatusEffect,
  nextRound,
  nextTurn,
  outOfCombat,
  patchCombatant,
  resurrect,
} from '../api';
import type { LiveEncounter as LiveState, StatusEffectRequest } from '../types';
import { useLiveEncounter } from '../hooks/useLiveEncounter';
import { activeCombatant } from '../logic';
import { InitiativeLine } from '../components/InitiativeLine';
import { StatusEffectList } from '../components/StatusEffectList';
import { EndOfRoundScreen } from '../components/EndOfRoundScreen';
import { AddStatusEffectModal } from '../components/AddStatusEffectModal';
import { AddCharacterModal } from '../components/AddCharacterModal';
import { Portrait } from '../components/Portrait';
import { IconButton } from '../components/IconButton';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
  faCheck,
  faHeartPulse,
  faQrcode,
  faSkullCrossbones,
  faUserShield,
} from '@fortawesome/free-solid-svg-icons';
import { QrCodeModal } from '../components/QrCodeModal';

interface Props {
  dm: boolean;
}

export function LiveEncounter({ dm }: Props) {
  const { code = '' } = useParams();
  const { state, setState, error, loading, connected } = useLiveEncounter(code, dm);
  const [busy, setBusy] = useState(false);
  const [actionError, setActionError] = useState<string | null>(null);
  const [showStatus, setShowStatus] = useState(false);
  const [showAddChar, setShowAddChar] = useState(false);
  const [showQr, setShowQr] = useState(false);
  // When the status modal is opened via "to self", holds the active combatant id
  // to pre-select; null when opened for the general "Add status effect".
  const [statusPreselectId, setStatusPreselectId] = useState<number | null>(null);

  // Edit-active-combatant inline form
  const [editIni, setEditIni] = useState('');
  const [editLe, setEditLe] = useState('');

  const run = async (fn: () => Promise<LiveState>) => {
    setBusy(true);
    setActionError(null);
    try {
      setState(await fn());
    } catch (e) {
      setActionError(e instanceof Error ? e.message : 'Action failed');
    } finally {
      setBusy(false);
    }
  };

  // DM shortcut: pressing Enter advances the turn — but only on the DM screen,
  // when no modal is open, the fight isn't at the end-of-round screen, and the
  // focus isn't in a form field/button (so editing INI/LE or activating another
  // control isn't hijacked, and the focused End-of-turn button can't double-fire).
  useEffect(() => {
    if (!dm) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key !== 'Enter') return;
      if (showStatus || showAddChar || showQr) return;
      if (busy || !state || state.phase === 'END_OF_ROUND') return;
      const t = e.target as HTMLElement | null;
      const tag = t?.tagName;
      if (
        t?.isContentEditable ||
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        tag === 'BUTTON'
      ) {
        return;
      }
      e.preventDefault();
      void run(() => nextTurn(code));
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [dm, showStatus, showAddChar, showQr, busy, state, code]);

  if (loading && !state) {
    return (
      <div className="page center">
        <h1 className="banner-title">Summoning the fight…</h1>
      </div>
    );
  }
  if (error && !state) {
    return (
      <div className="page stack center">
        <div className="error-banner">{error}</div>
        <Link className="btn" to="/">
          ← Home
        </Link>
      </div>
    );
  }
  if (!state) return null;

  const active = activeCombatant(state);
  const isEnd = state.phase === 'END_OF_ROUND';

  const submitStatus = async (req: StatusEffectRequest) => {
    await run(() => addStatusEffect(code, req));
  };

  return (
    <div className="live-page">
      <div className="live-top">
        <Link className="btn btn-ghost btn-sm" to="/">
          ← Home
        </Link>
        <div className="row" style={{ gap: '0.6rem' }}>
          <span className="round-pill">Round {state.round}</span>
          <span className="round-pill" title="Connection">
            {connected ? '🟢 live' : '🔴 offline'}
          </span>
          <span className="code-chip" title="Share code">
            {state.code}
          </span>
          <IconButton
            icon={faQrcode}
            label="Show QR code to join"
            className="btn-sm"
            onClick={() => setShowQr(true)}
          />
        </div>
      </div>

      <div className="live-atmosphere live-atmosphere-compact">
        {state.encounter.atmospherePicture && (
          <img src={state.encounter.atmospherePicture} alt={state.encounter.name} />
        )}
        <div className="overlay">
          <h1>{state.encounter.name}</h1>
        </div>
      </div>

      {actionError && <div className="error-banner">{actionError}</div>}

      {/* THE HERO FEATURE */}
      <InitiativeLine state={state} dm={dm} />

      {dm && (
        <div className="dm-bar">
          <button
            className="btn btn-primary"
            disabled={busy || isEnd}
            onClick={() => run(() => nextTurn(code))}
          >
            End of turn ▸
          </button>
          <button
            className="btn"
            disabled={busy}
            onClick={() => {
              setStatusPreselectId(null);
              setShowStatus(true);
            }}
          >
            ✦ Add status effect
          </button>
          <button
            className="btn"
            disabled={busy || !active}
            onClick={() => {
              setStatusPreselectId(active ? active.id : null);
              setShowStatus(true);
            }}
          >
            <FontAwesomeIcon icon={faUserShield} /> Add status effect to self
          </button>
          <button
            className="btn"
            disabled={busy}
            onClick={() => setShowAddChar(true)}
          >
            ＋ Add character
          </button>
        </div>
      )}

      <div className="live-grid">
        <div className="parchment active-panel">
          {active ? (
            <div className="active-row">
              <Portrait
                src={active.picture}
                name={active.displayName}
                className="active-portrait"
              />
              <div className="active-body">
                <h2 style={{ marginBottom: 0 }}>{active.displayName}</h2>
                <div className="active-ini">Initiative {active.initiative}</div>
                <p className="muted" style={{ marginTop: '0.2rem' }}>
                  {active.side === 'PARTY' ? 'Hero' : 'Enemy'}
                  {active.isOutOfCombat && ' · out of combat'}
                </p>
                {dm && active.description && (
                  <p style={{ textAlign: 'left' }}>{active.description}</p>
                )}

                {dm && (
                  <div className="stack" style={{ marginTop: '0.6rem' }}>
                    <div className="row">
                      <div className="field" style={{ flex: 1, margin: 0 }}>
                        <label>Set INI (next round)</label>
                        <input
                          type="number"
                          value={editIni}
                          placeholder={String(active.initiative)}
                          onChange={(e) => setEditIni(e.target.value)}
                        />
                      </div>
                      {active.side === 'ENEMIES' && (
                        <div className="field" style={{ flex: 1, margin: 0 }}>
                          <label>Set LE</label>
                          <input
                            type="number"
                            value={editLe}
                            placeholder={active.le != null ? String(active.le) : ''}
                            onChange={(e) => setEditLe(e.target.value)}
                          />
                        </div>
                      )}
                    </div>
                    <IconButton
                      icon={faCheck}
                      label="Apply changes"
                      text="Apply changes"
                      className="btn-primary apply-changes-btn"
                      disabled={busy || (editIni === '' && editLe === '')}
                      onClick={() => {
                        const body: { initiative?: number; le?: number } = {};
                        if (editIni !== '') body.initiative = Number(editIni);
                        if (editLe !== '') body.le = Number(editLe);
                        void run(() => patchCombatant(code, active.id, body)).then(
                          () => {
                            setEditIni('');
                            setEditLe('');
                          },
                        );
                      }}
                    />

                    {active.side === 'PARTY' &&
                      (active.isOutOfCombat ? (
                        <IconButton
                          icon={faHeartPulse}
                          label="Resurrect"
                          text="Resurrect"
                          className="btn-sm btn-primary"
                          onClick={() => run(() => resurrect(code, active.id))}
                        />
                      ) : (
                        <IconButton
                          icon={faSkullCrossbones}
                          label="Mark out of combat"
                          text="Out of combat"
                          className="btn-sm"
                          onClick={() => run(() => outOfCombat(code, active.id))}
                        />
                      ))}

                    {/* Enemy shortcut: "out of combat" sets LE to 0 (removes it). */}
                    {active.side === 'ENEMIES' && (
                      <IconButton
                        icon={faSkullCrossbones}
                        label="Out of combat (set LE to 0)"
                        text="Out of combat"
                        className="btn-sm btn-danger"
                        disabled={busy}
                        onClick={() =>
                          run(() => patchCombatant(code, active.id, { le: 0 }))
                        }
                      />
                    )}
                  </div>
                )}
              </div>
            </div>
          ) : (
            <p className="muted">No active combatant.</p>
          )}
        </div>

        <div className="parchment stack">
          <h2 style={{ margin: 0 }}>Status effects</h2>
          <StatusEffectList
            effects={active?.statusEffects ?? []}
            dm={dm}
            onRemove={
              dm ? (id) => run(() => deleteStatusEffect(code, id)) : undefined
            }
          />
        </div>
      </div>

      <EndOfRoundScreen
        show={isEnd}
        round={state.round}
        effects={state.roundEndEffects}
        dm={dm}
        busy={busy}
        onNextRound={() => run(() => nextRound(code))}
      />

      {dm && (
        <>
          <AddStatusEffectModal
            open={showStatus}
            onClose={() => setShowStatus(false)}
            combatants={state.combatants}
            onSubmit={submitStatus}
            preselectCombatantId={statusPreselectId}
          />
          <AddCharacterModal
            open={showAddChar}
            busy={busy}
            onClose={() => setShowAddChar(false)}
            onSubmit={async (fd) => {
              await run(() => addCombatant(code, fd));
            }}
          />
        </>
      )}

      <QrCodeModal open={showQr} onClose={() => setShowQr(false)} code={state.code} />
    </div>
  );
}
