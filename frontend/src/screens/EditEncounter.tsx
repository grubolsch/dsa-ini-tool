import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  addEncounterMonster,
  createEncounter,
  deleteEncounterMonster,
  getEncounter,
  searchMonsterTemplates,
  startEncounter,
  updateEncounter,
  updateEncounterMonster,
} from '../api';
import type { EncounterDetail, EncounterMonster, MonsterTemplate } from '../types';
import { MonsterFormFields, type MonsterFormValues } from '../components/MonsterFormFields';
import { Portrait } from '../components/Portrait';
import { IconButton } from '../components/IconButton';
import { faPen, faTrash } from '@fortawesome/free-solid-svg-icons';
import { storeLoadedEncounterId } from './Home';

export function EditEncounter() {
  const { id } = useParams();
  const navigate = useNavigate();
  const encounterId = id ? Number(id) : null;

  const [detail, setDetail] = useState<EncounterDetail | null>(null);
  const [name, setName] = useState('');
  const [atmosphere, setAtmosphere] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [editingMonster, setEditingMonster] = useState<EncounterMonster | null>(null);

  // typeahead
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<MonsterTemplate[]>([]);
  const debounce = useRef<ReturnType<typeof setTimeout>>();

  useEffect(() => {
    if (encounterId == null) return;
    getEncounter(encounterId)
      .then((d) => {
        setDetail(d);
        setName(d.name);
      })
      .catch((e) => setError(e.message));
  }, [encounterId]);

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

  const saveEncounterMeta = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    const fd = new FormData();
    fd.set('name', name.trim());
    if (atmosphere) fd.set('atmospherePicture', atmosphere);
    setBusy(true);
    setError(null);
    try {
      const saved =
        encounterId == null
          ? await createEncounter(fd)
          : await updateEncounter(encounterId, fd);
      setDetail(saved);
      storeLoadedEncounterId(saved.id);
      if (encounterId == null) navigate(`/encounter/${saved.id}`, { replace: true });
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save encounter');
    } finally {
      setBusy(false);
    }
  };

  const refreshDetail = async () => {
    if (detail) setDetail(await getEncounter(detail.id));
  };

  const submitMonster = async (v: MonsterFormValues) => {
    if (!detail) {
      setError('Save the encounter first (give it a name).');
      return;
    }
    const fd = new FormData();
    fd.set('name', v.name);
    fd.set('initiative', String(v.initiative));
    fd.set('le', String(v.le));
    fd.set('description', v.description);
    fd.set('saveTemplate', v.saveTemplate ? '1' : '0');
    if (v.picture) fd.set('picture', v.picture);
    else if (v.galleryImage) fd.set('galleryImage', v.galleryImage);
    setBusy(true);
    setError(null);
    try {
      if (editingMonster) {
        await updateEncounterMonster(detail.id, editingMonster.id, fd);
        setEditingMonster(null);
      } else {
        await addEncounterMonster(detail.id, fd);
      }
      await refreshDetail();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save enemy');
    } finally {
      setBusy(false);
    }
  };

  const addFromTemplate = async (t: MonsterTemplate) => {
    if (!detail) return;
    const fd = new FormData();
    fd.set('name', t.name);
    fd.set('initiative', String(t.initiative));
    fd.set('le', String(t.le));
    fd.set('description', t.description ?? '');
    fd.set('monsterTemplateId', String(t.id));
    try {
      await addEncounterMonster(detail.id, fd);
      setQuery('');
      setResults([]);
      await refreshDetail();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to add template');
    }
  };

  const removeMonster = async (m: EncounterMonster) => {
    if (!detail) return;
    if (!confirm(`Remove ${m.name}?`)) return;
    try {
      await deleteEncounterMonster(detail.id, m.id);
      if (editingMonster?.id === m.id) setEditingMonster(null);
      await refreshDetail();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to remove');
    }
  };

  const start = async () => {
    if (!detail) return;
    try {
      const live = await startEncounter(detail.id);
      navigate(`/run/${live.code}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to start');
    }
  };

  return (
    <div className="page stack">
      <div className="row spread">
        <h1 className="banner-title" style={{ textAlign: 'left' }}>
          {encounterId == null ? 'Create encounter' : 'Edit encounter'}
        </h1>
        <Link className="btn btn-ghost" to="/">
          ← Home
        </Link>
      </div>

      {error && <div className="error-banner">{error}</div>}

      <form className="parchment stack" onSubmit={saveEncounterMeta}>
        <div className="field">
          <label>Encounter name (unique)</label>
          <input value={name} onChange={(e) => setName(e.target.value)} required />
        </div>
        <div className="field">
          <label>Atmosphere picture</label>
          <input
            type="file"
            accept="image/*"
            onChange={(e) => setAtmosphere(e.target.files?.[0] ?? null)}
          />
        </div>
        {detail?.atmospherePicture && (
          <img className="atmosphere" src={detail.atmospherePicture} alt={detail.name} />
        )}
        <div className="row">
          <button className="btn btn-primary" type="submit" disabled={busy}>
            {encounterId == null ? 'Create encounter' : 'Save encounter'}
          </button>
          {detail && (
            <button type="button" className="btn" onClick={start}>
              ⚔ Start this encounter
            </button>
          )}
        </div>
      </form>

      {detail && (
        <>
          <div className="parchment stack">
            <h2>Add an existing enemy</h2>
            <div className="field" style={{ position: 'relative' }}>
              <label>Search templates</label>
              <input
                value={query}
                placeholder="goblin, dragon…"
                onChange={(e) => setQuery(e.target.value)}
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
                        className="btn btn-sm btn-primary"
                        onClick={() => addFromTemplate(t)}
                      >
                        Add
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </div>

          <div className="parchment stack">
            <h2>{editingMonster ? `Edit ${editingMonster.name}` : 'Add a new enemy'}</h2>
            <MonsterFormFields
              key={editingMonster?.id ?? 'new'}
              initial={
                editingMonster
                  ? {
                      name: editingMonster.name,
                      initiative: editingMonster.initiative,
                      le: editingMonster.le,
                      description: editingMonster.description,
                      pictureUrl: editingMonster.picture,
                    }
                  : undefined
              }
              submitLabel={editingMonster ? 'Save enemy' : 'Add enemy'}
              onSubmit={submitMonster}
              busy={busy}
            />
            {editingMonster && (
              <button className="btn" onClick={() => setEditingMonster(null)}>
                Cancel edit
              </button>
            )}
          </div>

          <div className="parchment stack">
            <h2>Enemies in this encounter ({detail.monsters.length})</h2>
            {detail.monsters.length === 0 && (
              <p className="muted">No enemies yet.</p>
            )}
            {detail.monsters.map((m) => (
              <div className="list-row" key={m.id}>
                <Portrait src={m.picture} name={m.name} />
                <div style={{ flex: 1 }}>
                  <strong>{m.name}</strong>
                  <div className="muted">
                    INI {m.initiative} · LE {m.le}
                    {m.monsterTemplateId != null && ' · from template'}
                  </div>
                </div>
                <IconButton
                  icon={faPen}
                  label={`Edit ${m.name}`}
                  className="btn-sm"
                  onClick={() => setEditingMonster(m)}
                />
                <IconButton
                  icon={faTrash}
                  label={`Remove ${m.name}`}
                  className="btn-sm btn-danger"
                  onClick={() => removeMonster(m)}
                />
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
