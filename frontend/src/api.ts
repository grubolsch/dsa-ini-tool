// Typed fetch helpers for every REST endpoint in CONTRACT.md.

import type {
  EncounterDetail,
  EncounterSummary,
  GalleryImage,
  Hero,
  LiveEncounter,
  MonsterTemplate,
  StatusEffectRequest,
} from './types';

const BASE = '/api';

async function handle<T>(res: Response): Promise<T> {
  if (!res.ok) {
    let message = `Request failed (${res.status})`;
    try {
      const body = await res.json();
      if (body && typeof body.error === 'string') message = body.error;
    } catch {
      /* non-JSON error body */
    }
    throw new ApiError(message, res.status);
  }
  if (res.status === 204) return undefined as T;
  return (await res.json()) as T;
}

export class ApiError extends Error {
  status: number;
  constructor(message: string, status: number) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
  }
}

async function getJson<T>(url: string): Promise<T> {
  return handle<T>(await fetch(url, { headers: { Accept: 'application/json' } }));
}

async function sendForm<T>(
  url: string,
  method: string,
  form: FormData,
): Promise<T> {
  return handle<T>(await fetch(url, { method, body: form }));
}

async function sendJson<T>(url: string, method: string, body: unknown): Promise<T> {
  return handle<T>(
    await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    }),
  );
}

// ---- Gallery ----

export const getGallery = () => getJson<GalleryImage[]>(`${BASE}/gallery`);

// ---- Heroes / party ----

export const getHeroes = () => getJson<Hero[]>(`${BASE}/heroes`);

export const createHero = (form: FormData) =>
  sendForm<Hero>(`${BASE}/heroes`, 'POST', form);

export const updateHero = (id: number, form: FormData) =>
  sendForm<Hero>(`${BASE}/heroes/${id}`, 'PUT', form);

export const deleteHero = (id: number) =>
  sendJson<void>(`${BASE}/heroes/${id}`, 'DELETE', undefined);

// ---- Monster templates ----

export const searchMonsterTemplates = (q = '') =>
  getJson<MonsterTemplate[]>(
    `${BASE}/monster-templates${q ? `?q=${encodeURIComponent(q)}` : ''}`,
  );

export const createMonsterTemplate = (form: FormData) =>
  sendForm<MonsterTemplate>(`${BASE}/monster-templates`, 'POST', form);

export const updateMonsterTemplate = (id: number, form: FormData) =>
  sendForm<MonsterTemplate>(`${BASE}/monster-templates/${id}`, 'PUT', form);

export const deleteMonsterTemplate = (id: number) =>
  sendJson<void>(`${BASE}/monster-templates/${id}`, 'DELETE', undefined);

// ---- Encounters ----

export const getEncounters = () =>
  getJson<EncounterSummary[]>(`${BASE}/encounters`);

export const getEncounter = (id: number) =>
  getJson<EncounterDetail>(`${BASE}/encounters/${id}`);

export const createEncounter = (form: FormData) =>
  sendForm<EncounterDetail>(`${BASE}/encounters`, 'POST', form);

export const updateEncounter = (id: number, form: FormData) =>
  sendForm<EncounterDetail>(`${BASE}/encounters/${id}`, 'PUT', form);

export const deleteEncounter = (id: number) =>
  sendJson<void>(`${BASE}/encounters/${id}`, 'DELETE', undefined);

export const addEncounterMonster = (encounterId: number, form: FormData) =>
  sendForm<EncounterMonsterResponse>(
    `${BASE}/encounters/${encounterId}/monsters`,
    'POST',
    form,
  );

export const updateEncounterMonster = (
  encounterId: number,
  monsterId: number,
  form: FormData,
) =>
  sendForm<EncounterMonsterResponse>(
    `${BASE}/encounters/${encounterId}/monsters/${monsterId}`,
    'PUT',
    form,
  );

export const deleteEncounterMonster = (encounterId: number, monsterId: number) =>
  sendJson<void>(
    `${BASE}/encounters/${encounterId}/monsters/${monsterId}`,
    'DELETE',
    undefined,
  );

type EncounterMonsterResponse = import('./types').EncounterMonster;

// ---- Live encounter ----

export const startEncounter = (encounterId: number) =>
  sendJson<LiveEncounter>(`${BASE}/encounters/${encounterId}/start`, 'POST', {});

export const getLive = (code: string, dm: boolean) =>
  getJson<LiveEncounter>(`${BASE}/live/${code}${dm ? '?dm=1' : ''}`);

export const nextTurn = (code: string) =>
  sendJson<LiveEncounter>(`${BASE}/live/${code}/next-turn`, 'POST', {});

export const nextRound = (code: string) =>
  sendJson<LiveEncounter>(`${BASE}/live/${code}/next-round`, 'POST', {});

export const patchCombatant = (
  code: string,
  cid: number,
  body: { initiative?: number; le?: number },
) => sendJson<LiveEncounter>(`${BASE}/live/${code}/combatants/${cid}`, 'PATCH', body);

export const outOfCombat = (code: string, cid: number) =>
  sendJson<LiveEncounter>(
    `${BASE}/live/${code}/combatants/${cid}/out-of-combat`,
    'POST',
    {},
  );

export const resurrect = (code: string, cid: number) =>
  sendJson<LiveEncounter>(
    `${BASE}/live/${code}/combatants/${cid}/resurrect`,
    'POST',
    {},
  );

export const addCombatant = (code: string, form: FormData) =>
  sendForm<LiveEncounter>(`${BASE}/live/${code}/combatants`, 'POST', form);

export const addStatusEffect = (code: string, body: StatusEffectRequest) =>
  sendJson<LiveEncounter>(`${BASE}/live/${code}/status-effects`, 'POST', body);

export const deleteStatusEffect = (code: string, seId: number) =>
  sendJson<LiveEncounter>(
    `${BASE}/live/${code}/status-effects/${seId}`,
    'DELETE',
    undefined,
  );
