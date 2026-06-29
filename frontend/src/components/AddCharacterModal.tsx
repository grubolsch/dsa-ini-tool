import { useEffect, useRef, useState } from 'react';
import { MonsterFormFields, type MonsterFormValues } from './MonsterFormFields';
import { Modal } from './Modal';
import { Portrait } from './Portrait';
import { searchMonsterTemplates } from '../api';
import type { MonsterTemplate } from '../types';

interface Props {
  open: boolean;
  onClose: () => void;
  onSubmit: (form: FormData) => Promise<void>;
  busy?: boolean;
}

/** Mid-fight "Add character" modal — same fields as the enemy form.
 *  Can also load an enemy from the library and add several at once. */
export function AddCharacterModal({ open, onClose, onSubmit, busy }: Props) {
  // library typeahead
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<MonsterTemplate[]>([]);
  const [selected, setSelected] = useState<MonsterTemplate | null>(null);
  const [quantity, setQuantity] = useState(1);
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  useEffect(() => {
    if (debounce.current) clearTimeout(debounce.current);
    if (!query.trim()) {
      setResults([]);
      return;
    }
    debounce.current = setTimeout(() => {
      searchMonsterTemplates(query.trim())
        .then(setResults)
        .catch(() => setResults([]));
    }, 250);
    return () => clearTimeout(debounce.current);
  }, [query]);

  const reset = () => {
    setQuery('');
    setResults([]);
    setSelected(null);
    setQuantity(1);
  };

  const close = () => {
    reset();
    onClose();
  };

  const handle = async (v: MonsterFormValues) => {
    const fd = new FormData();
    fd.set('name', v.name);
    fd.set('initiative', String(v.initiative));
    fd.set('le', String(v.le));
    fd.set('description', v.description);
    if (v.picture) fd.set('picture', v.picture);
    else if (v.galleryImage) fd.set('galleryImage', v.galleryImage);
    if (selected) fd.set('monsterTemplateId', String(selected.id));
    fd.set('quantity', String(quantity));
    await onSubmit(fd);
    close();
  };

  return (
    <Modal open={open} title="Add character to the fight" onClose={close}>
      <p className="muted" style={{ marginTop: 0 }}>
        Rolls initiative (+1–6) and joins next round per the initiative rule.
      </p>

      <div className="field" style={{ position: 'relative' }}>
        <label>Load from the enemy library</label>
        <input
          value={query}
          placeholder="goblin, dragon…"
          onChange={(e) => {
            setQuery(e.target.value);
            setSelected(null);
          }}
        />
        {results.length > 0 && (
          <div className="stack" style={{ marginTop: '0.5rem' }}>
            {results.map((t) => (
              <div className="list-row" key={t.id}>
                <Portrait src={t.picture} name={t.name} />
                <div style={{ flex: 1 }}>
                  <strong>{t.name}</strong>
                  <div className="muted">
                    INI {t.initiative} · LE {t.le}
                  </div>
                </div>
                <button
                  type="button"
                  className="btn btn-sm btn-primary"
                  onClick={() => {
                    setSelected(t);
                    setQuery(t.name);
                    setResults([]);
                  }}
                >
                  Select
                </button>
              </div>
            ))}
          </div>
        )}
        {selected && (
          <span className="muted">From enemy library: {selected.name}</span>
        )}
      </div>

      <div className="field">
        <label>Quantity</label>
        <input
          type="number"
          min={1}
          max={20}
          value={quantity}
          onChange={(e) => {
            const n = Math.min(20, Math.max(1, Math.floor(Number(e.target.value) || 1)));
            setQuantity(n);
          }}
        />
      </div>

      <MonsterFormFields
        key={selected?.id ?? 'new'}
        initial={
          selected
            ? {
                name: selected.name,
                initiative: selected.initiative,
                le: selected.le,
                description: selected.description,
                pictureUrl: selected.picture,
              }
            : undefined
        }
        showSaveTemplate={false}
        submitLabel="Add to fight"
        onSubmit={handle}
        busy={busy}
      />
    </Modal>
  );
}
