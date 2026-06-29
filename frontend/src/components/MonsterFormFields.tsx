import { useState } from 'react';
import { GalleryPicker } from './GalleryPicker';
import type { GalleryImage } from '../types';

export interface MonsterFormValues {
  name: string;
  initiative: number;
  le: number;
  description: string;
  picture: File | null;
  /** Filename of a chosen gallery image (mutually exclusive with an upload). */
  galleryImage: string | null;
  saveTemplate: boolean;
}

interface Props {
  initial?: Partial<MonsterFormValues> & { picture?: never; pictureUrl?: string | null };
  showSaveTemplate?: boolean;
  submitLabel: string;
  onSubmit: (values: MonsterFormValues) => Promise<void> | void;
  busy?: boolean;
}

/** Shared add/edit monster (combatant) form fields — used by EditEncounter
 *  and the "Add character" live modal. A picture can be uploaded OR picked
 *  from the gallery (public/gallery). */
export function MonsterFormFields({
  initial,
  showSaveTemplate = true,
  submitLabel,
  onSubmit,
  busy,
}: Props) {
  const [name, setName] = useState(initial?.name ?? '');
  const [initiative, setInitiative] = useState(initial?.initiative ?? 5);
  const [le, setLe] = useState(initial?.le ?? 10);
  const [description, setDescription] = useState(initial?.description ?? '');
  const [picture, setPicture] = useState<File | null>(null);
  const [galleryImage, setGalleryImage] = useState<string | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(initial?.pictureUrl ?? null);
  const [galleryOpen, setGalleryOpen] = useState(false);
  const [saveTemplate, setSaveTemplate] = useState(initial?.saveTemplate ?? false);

  const chooseFile = (file: File | null) => {
    setPicture(file);
    setGalleryImage(null);
    setPreviewUrl(file ? URL.createObjectURL(file) : (initial?.pictureUrl ?? null));
  };

  const chooseGallery = (img: GalleryImage) => {
    setGalleryImage(img.name);
    setPicture(null);
    setPreviewUrl(img.url);
  };

  const reset = () => {
    setName(initial?.name ?? '');
    setInitiative(initial?.initiative ?? 5);
    setLe(initial?.le ?? 10);
    setDescription(initial?.description ?? '');
    setPicture(null);
    setGalleryImage(null);
    setPreviewUrl(initial?.pictureUrl ?? null);
    setSaveTemplate(initial?.saveTemplate ?? false);
  };

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    await onSubmit({
      name: name.trim(),
      initiative,
      le,
      description,
      picture,
      galleryImage,
      saveTemplate,
    });
    reset();
  };

  return (
    <form onSubmit={submit} className="stack">
      <div className="field">
        <label>Name</label>
        <input value={name} onChange={(e) => setName(e.target.value)} required />
      </div>
      <div className="row">
        <div className="field" style={{ flex: 1 }}>
          <label>Initiative (base)</label>
          <input
            type="number"
            value={initiative}
            onChange={(e) => setInitiative(Number(e.target.value))}
          />
        </div>
        <div className="field" style={{ flex: 1 }}>
          <label>LE (health)</label>
          <input
            type="number"
            min={1}
            value={le}
            onChange={(e) => setLe(Number(e.target.value))}
          />
        </div>
      </div>
      <div className="field">
        <label>Description / stats</label>
        <textarea
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Abilities, attacks, lore…"
        />
      </div>
      <div className="field">
        <label>Picture</label>
        <div className="row" style={{ alignItems: 'center', gap: '0.75rem' }}>
          {previewUrl ? (
            <img className="picture-preview" src={previewUrl} alt="Selected portrait" />
          ) : (
            <div className="picture-preview placeholder">⚔</div>
          )}
          <div className="stack" style={{ gap: '0.4rem', flex: 1 }}>
            <input
              type="file"
              accept="image/png,image/jpeg,image/gif,image/webp"
              onChange={(e) => chooseFile(e.target.files?.[0] ?? null)}
            />
            <button
              type="button"
              className="btn btn-sm"
              onClick={() => setGalleryOpen(true)}
            >
              Choose an image from the gallery
            </button>
            {galleryImage && <span className="muted">Gallery: {galleryImage}</span>}
          </div>
        </div>
      </div>
      {showSaveTemplate && (
        <div className="field-inline field">
          <input
            id="saveTemplate"
            type="checkbox"
            checked={saveTemplate}
            onChange={(e) => setSaveTemplate(e.target.checked)}
          />
          <label htmlFor="saveTemplate">Save this enemy template</label>
        </div>
      )}
      <button className="btn btn-primary" type="submit" disabled={busy}>
        {busy ? 'Saving…' : submitLabel}
      </button>

      <GalleryPicker
        open={galleryOpen}
        onClose={() => setGalleryOpen(false)}
        onSelect={chooseGallery}
        selectedName={galleryImage}
      />
    </form>
  );
}
