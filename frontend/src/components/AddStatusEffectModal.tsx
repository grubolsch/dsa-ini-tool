import { useEffect, useState } from 'react';
import type { Combatant, StatusEffectRequest } from '../types';
import { Modal } from './Modal';

interface Props {
  open: boolean;
  onClose: () => void;
  combatants: Combatant[];
  onSubmit: (req: StatusEffectRequest) => Promise<void> | void;
  /** When set and the modal opens, this combatant starts pre-selected. */
  preselectCombatantId?: number | null;
}

/**
 * DM modal to add a status effect. Character picker shown in TWO COLUMNS
 * (heroes | enemies) with per-character checkboxes plus group checkboxes
 * "All heroes" / "All enemies" laid out in those same two columns.
 */
export function AddStatusEffectModal({
  open,
  onClose,
  combatants,
  onSubmit,
  preselectCombatantId,
}: Props) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [durationRounds, setDurationRounds] = useState(3);
  const [triggerAtRoundEnd, setTriggerAtRoundEnd] = useState(false);
  const [allHeroes, setAllHeroes] = useState(false);
  const [allEnemies, setAllEnemies] = useState(false);
  const [picked, setPicked] = useState<Set<number>>(new Set());
  const [busy, setBusy] = useState(false);

  // When opening, seed the picker with the pre-selected combatant (if any).
  useEffect(() => {
    if (open) {
      setPicked(
        preselectCombatantId != null
          ? new Set([preselectCombatantId])
          : new Set(),
      );
    }
  }, [open, preselectCombatantId]);

  const heroes = combatants.filter((c) => c.side === 'PARTY' && !c.isDead);
  const enemies = combatants.filter((c) => c.side === 'ENEMIES' && !c.isDead);

  const toggle = (id: number) => {
    setPicked((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const reset = () => {
    setName('');
    setDescription('');
    setDurationRounds(3);
    setTriggerAtRoundEnd(false);
    setAllHeroes(false);
    setAllEnemies(false);
    setPicked(new Set());
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    const req: StatusEffectRequest = {
      name: name.trim(),
      description: description.trim(),
      durationRounds,
      triggerAtRoundEnd,
      targets: {
        combatantIds: [...picked],
        allEnemies,
        allHeroes,
      },
    };
    setBusy(true);
    try {
      await onSubmit(req);
      reset();
      onClose();
    } finally {
      setBusy(false);
    }
  };

  const nothingPicked = picked.size === 0 && !allHeroes && !allEnemies;

  return (
    <Modal open={open} title="Add status effect" onClose={onClose}>
      <form onSubmit={submit} className="stack">
        <div className="field">
          <label>Name</label>
          <input value={name} onChange={(e) => setName(e.target.value)} required />
        </div>
        <div className="field">
          <label>Description</label>
          <textarea
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            style={{ minHeight: 70 }}
          />
        </div>
        <div className="row">
          <div className="field" style={{ flex: 1 }}>
            <label>Duration (rounds)</label>
            <input
              type="number"
              min={1}
              value={durationRounds}
              onChange={(e) => setDurationRounds(Number(e.target.value))}
            />
          </div>
          <div className="field-inline field" style={{ flex: 1 }}>
            <input
              id="trigger"
              type="checkbox"
              checked={triggerAtRoundEnd}
              onChange={(e) => setTriggerAtRoundEnd(e.target.checked)}
            />
            <label htmlFor="trigger">Trigger at round end</label>
          </div>
        </div>

        <div className="two-col">
          <div>
            <div className="col-heading">Heroes</div>
            <label className="picker-item">
              <input
                type="checkbox"
                checked={allHeroes}
                onChange={(e) => setAllHeroes(e.target.checked)}
              />
              <strong>All heroes</strong>
            </label>
            {heroes.map((c) => (
              <label key={c.id} className="picker-item">
                <input
                  type="checkbox"
                  checked={picked.has(c.id)}
                  disabled={allHeroes}
                  onChange={() => toggle(c.id)}
                />
                {c.displayName}
              </label>
            ))}
          </div>
          <div>
            <div className="col-heading">Enemies</div>
            <label className="picker-item">
              <input
                type="checkbox"
                checked={allEnemies}
                onChange={(e) => setAllEnemies(e.target.checked)}
              />
              <strong>All enemies</strong>
            </label>
            {enemies.map((c) => (
              <label key={c.id} className="picker-item">
                <input
                  type="checkbox"
                  checked={picked.has(c.id)}
                  disabled={allEnemies}
                  onChange={() => toggle(c.id)}
                />
                {c.displayName}
              </label>
            ))}
          </div>
        </div>

        <button
          className="btn btn-primary"
          type="submit"
          disabled={busy || nothingPicked}
        >
          {busy ? 'Applying…' : 'Apply effect'}
        </button>
        {nothingPicked && <span className="dm-hint">Pick at least one target.</span>}
      </form>
    </Modal>
  );
}
