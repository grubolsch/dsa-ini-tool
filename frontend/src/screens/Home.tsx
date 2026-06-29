import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { getEncounters, getHeroes, startEncounter } from '../api';
import type { EncounterSummary } from '../types';

const LOADED_KEY = 'dsa.loadedEncounterId';

export function loadLoadedEncounterId(): number | null {
  const raw = localStorage.getItem(LOADED_KEY);
  if (!raw) return null;
  const n = Number(raw);
  return Number.isFinite(n) ? n : null;
}

export function storeLoadedEncounterId(id: number | null) {
  if (id == null) localStorage.removeItem(LOADED_KEY);
  else localStorage.setItem(LOADED_KEY, String(id));
}

export function Home() {
  const navigate = useNavigate();
  const [encounters, setEncounters] = useState<EncounterSummary[]>([]);
  const [heroCount, setHeroCount] = useState(0);
  const [loadedId, setLoadedId] = useState<number | null>(loadLoadedEncounterId());
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [starting, setStarting] = useState(false);

  useEffect(() => {
    getEncounters()
      .then(setEncounters)
      .catch((e) => setError(e.message));
    getHeroes()
      .then((h) => setHeroCount(h.length))
      .catch(() => setHeroCount(0));
  }, []);

  // Drop the loaded id if that encounter no longer exists.
  useEffect(() => {
    if (loadedId != null && encounters.length > 0) {
      if (!encounters.some((e) => e.id === loadedId)) {
        setLoadedId(null);
        storeLoadedEncounterId(null);
      }
    }
  }, [encounters, loadedId]);

  const loaded = encounters.find((e) => e.id === loadedId) ?? null;
  const canStart = loaded != null && heroCount >= 1;

  const handleLoad = (id: number) => {
    const v = id || null;
    setLoadedId(v);
    storeLoadedEncounterId(v);
  };

  const handleStart = async () => {
    if (!loaded) return;
    setStarting(true);
    setError(null);
    try {
      const live = await startEncounter(loaded.id);
      navigate(`/run/${live.code}`);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Failed to start');
      setStarting(false);
    }
  };

  const handleJoin = (e: React.FormEvent) => {
    e.preventDefault();
    const c = code.trim().toUpperCase();
    if (/^[A-Z0-9]{8}$/.test(c)) navigate(`/play/${c}`);
    else setError('Code must be 8 letters/digits.');
  };

  return (
    <div className="page stack">
      <h1 className="banner-title">Tavern of Initiative</h1>
      <p className="center muted">A turn-tracker for pen &amp; paper combat.</p>

      {error && <div className="error-banner">{error}</div>}

      {loaded && (
        <div className="parchment stack">
          <h2 style={{ margin: 0 }}>Loaded encounter: {loaded.name}</h2>
          {loaded.atmospherePicture && (
            <img
              className="atmosphere"
              src={loaded.atmospherePicture}
              alt={loaded.name}
            />
          )}
          <p className="muted">{loaded.monsterCount} monster(s) prepared.</p>
        </div>
      )}

      <div className="parchment stack">
        <button
          className="btn btn-primary"
          disabled={!canStart || starting}
          onClick={handleStart}
        >
          {starting ? 'Rolling initiative…' : '⚔ Start encounter'}
        </button>
        {!canStart && (
          <span className="muted">
            {loaded == null
              ? 'Load an encounter first.'
              : 'Add at least one hero in Manage party.'}
          </span>
        )}

        <div className="row">
          <button className="btn" onClick={() => navigate('/party')}>
            Manage party
          </button>
          <button
            className="btn"
            onClick={() =>
              navigate(loaded ? `/encounter/${loaded.id}` : '/encounter')
            }
          >
            {loaded ? 'Edit encounter' : 'Create encounter'}
          </button>
        </div>

        <div className="field">
          <label>Load encounter (newest first)</label>
          <select
            value={loadedId ?? ''}
            onChange={(e) => handleLoad(Number(e.target.value))}
          >
            <option value="">— choose —</option>
            {encounters.map((e) => (
              <option key={e.id} value={e.id}>
                {e.name} ({e.monsterCount} monsters)
              </option>
            ))}
          </select>
        </div>
      </div>

      <form className="parchment stack" onSubmit={handleJoin}>
        <h2 style={{ margin: 0 }}>Join as a player</h2>
        <p className="muted" style={{ margin: 0 }}>
          Enter the 8-character code your DM shared.
        </p>
        <div className="row">
          <input
            value={code}
            maxLength={8}
            placeholder="A1B2C3D4"
            style={{ letterSpacing: '0.3em', textTransform: 'uppercase', flex: 1 }}
            onChange={(e) => setCode(e.target.value.toUpperCase())}
          />
          <button className="btn btn-primary" type="submit">
            Join
          </button>
        </div>
      </form>
    </div>
  );
}
