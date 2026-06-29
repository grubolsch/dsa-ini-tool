import { useCallback, useEffect, useRef, useState } from 'react';
import { getLive } from '../api';
import type { LiveEncounter } from '../types';

const MERCURE_URL =
  import.meta.env.VITE_MERCURE_URL ?? 'http://localhost:8080/.well-known/mercure';

interface UseLiveResult {
  state: LiveEncounter | null;
  error: string | null;
  loading: boolean;
  connected: boolean;
  /** Replace local state (e.g. with the response of a DM mutation). */
  setState: (s: LiveEncounter) => void;
  /** Re-fetch from REST. */
  refresh: () => void;
}

/**
 * Hydrates a live encounter via REST then subscribes to its Mercure topic
 * (dm or player). Every SSE message replaces the state wholesale.
 */
export function useLiveEncounter(code: string, dm: boolean): UseLiveResult {
  const [state, setState] = useState<LiveEncounter | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [connected, setConnected] = useState(false);
  const esRef = useRef<EventSource | null>(null);

  const refresh = useCallback(() => {
    let cancelled = false;
    setLoading(true);
    getLive(code, dm)
      .then((s) => {
        if (!cancelled) {
          setState(s);
          setError(null);
        }
      })
      .catch((e) => {
        if (!cancelled) setError(e instanceof Error ? e.message : 'Failed to load');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => {
      cancelled = true;
    };
  }, [code, dm]);

  useEffect(() => {
    const cancel = refresh();
    return cancel;
  }, [refresh]);

  useEffect(() => {
    const topic = `encounter/${code}/${dm ? 'dm' : 'player'}`;
    const url = `${MERCURE_URL}?topic=${encodeURIComponent(topic)}`;
    const es = new EventSource(url);
    esRef.current = es;

    es.onopen = () => setConnected(true);
    es.onerror = () => setConnected(false);
    es.onmessage = (evt) => {
      try {
        const data = JSON.parse(evt.data) as LiveEncounter;
        if (data && data.code) setState(data);
      } catch {
        /* ignore malformed payloads */
      }
    };

    return () => {
      es.close();
      esRef.current = null;
      setConnected(false);
    };
  }, [code, dm]);

  return { state, error, loading, connected, setState, refresh };
}
