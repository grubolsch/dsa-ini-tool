import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { createHero, deleteHero, getHeroes, updateHero } from '../api';
import type { Hero } from '../types';
import { Portrait } from '../components/Portrait';
import { IconButton } from '../components/IconButton';
import { faPen, faTrash } from '@fortawesome/free-solid-svg-icons';

export function ManageParty() {
  const [heroes, setHeroes] = useState<Hero[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [editing, setEditing] = useState<Hero | null>(null);

  // form
  const [name, setName] = useState('');
  const [initiative, setInitiative] = useState(10);
  const [picture, setPicture] = useState<File | null>(null);
  const [busy, setBusy] = useState(false);

  const reload = () =>
    getHeroes()
      .then(setHeroes)
      .catch((e) => setError(e.message));

  useEffect(() => {
    reload();
  }, []);

  const resetForm = () => {
    setEditing(null);
    setName('');
    setInitiative(10);
    setPicture(null);
  };

  const startEdit = (h: Hero) => {
    setEditing(h);
    setName(h.name);
    setInitiative(h.initiative);
    setPicture(null);
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    const fd = new FormData();
    fd.set('name', name.trim());
    fd.set('initiative', String(initiative));
    if (picture) fd.set('picture', picture);
    setBusy(true);
    setError(null);
    try {
      if (editing) await updateHero(editing.id, fd);
      else await createHero(fd);
      resetForm();
      await reload();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save hero');
    } finally {
      setBusy(false);
    }
  };

  const remove = async (h: Hero) => {
    if (!confirm(`Remove ${h.name} from the party?`)) return;
    try {
      await deleteHero(h.id);
      if (editing?.id === h.id) resetForm();
      await reload();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to delete');
    }
  };

  return (
    <div className="page stack">
      <div className="row spread">
        <h1 className="banner-title" style={{ textAlign: 'left' }}>
          The Party
        </h1>
        <Link className="btn btn-ghost" to="/">
          ← Home
        </Link>
      </div>

      {error && <div className="error-banner">{error}</div>}

      <div className="parchment stack">
        <h2>{editing ? `Edit ${editing.name}` : 'Add a hero'}</h2>
        <form className="stack" onSubmit={submit}>
          <div className="field">
            <label>Name</label>
            <input value={name} onChange={(e) => setName(e.target.value)} required />
          </div>
          <div className="field">
            <label>Initiative (base)</label>
            <input
              type="number"
              value={initiative}
              onChange={(e) => setInitiative(Number(e.target.value))}
            />
          </div>
          <div className="field">
            <label>Picture</label>
            <input
              type="file"
              accept="image/*"
              onChange={(e) => setPicture(e.target.files?.[0] ?? null)}
            />
          </div>
          <div className="row">
            <button className="btn btn-primary" type="submit" disabled={busy}>
              {busy ? 'Saving…' : editing ? 'Save changes' : 'Add hero'}
            </button>
            {editing && (
              <button type="button" className="btn" onClick={resetForm}>
                Cancel
              </button>
            )}
          </div>
        </form>
      </div>

      <div className="parchment stack">
        <h2>Heroes ({heroes.length})</h2>
        {heroes.length === 0 && <p className="muted">No heroes yet.</p>}
        {heroes.map((h) => (
          <div className="list-row" key={h.id}>
            <Portrait src={h.picture} name={h.name} />
            <div style={{ flex: 1 }}>
              <strong>{h.name}</strong>
              <div className="muted">INI {h.initiative}</div>
            </div>
            <IconButton
              icon={faPen}
              label={`Edit ${h.name}`}
              className="btn-sm"
              onClick={() => startEdit(h)}
            />
            <IconButton
              icon={faTrash}
              label={`Delete ${h.name}`}
              className="btn-sm btn-danger"
              onClick={() => remove(h)}
            />
          </div>
        ))}
      </div>
    </div>
  );
}
