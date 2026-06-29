import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { getGallery } from '../api';
import type { GalleryImage } from '../types';
import { Modal } from './Modal';

interface Props {
  open: boolean;
  onClose: () => void;
  /** Called with the chosen gallery image (filename + renderable url). */
  onSelect: (image: GalleryImage) => void;
  selectedName?: string | null;
}

/** Modal grid of the images in public/gallery. Click one to pick it as a picture. */
export function GalleryPicker({ open, onClose, onSelect, selectedName }: Props) {
  const [images, setImages] = useState<GalleryImage[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [query, setQuery] = useState('');

  useEffect(() => {
    if (!open) return;
    setLoading(true);
    setError(null);
    getGallery()
      .then(setImages)
      .catch((e) => setError(e instanceof Error ? e.message : 'Failed to load gallery'))
      .finally(() => setLoading(false));
  }, [open]);

  const filtered = query.trim()
    ? images.filter((i) => i.name.toLowerCase().includes(query.trim().toLowerCase()))
    : images;

  return (
    <Modal open={open} title="Choose an image from the gallery" onClose={onClose}>
      <div className="field" style={{ marginBottom: '0.75rem' }}>
        <input
          placeholder={`Search ${images.length} images…`}
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          autoFocus
        />
      </div>
      {loading && <p className="muted">Summoning the gallery…</p>}
      {error && <p className="error-text">{error}</p>}
      {!loading && !error && filtered.length === 0 && (
        <p className="muted">No images found.</p>
      )}
      <div className="gallery-grid">
        {filtered.map((img) => (
          <motion.button
            key={img.name}
            type="button"
            className={`gallery-cell${selectedName === img.name ? ' selected' : ''}`}
            title={img.name}
            onClick={() => {
              onSelect(img);
              onClose();
            }}
            whileHover={{ scale: 1.06 }}
            whileTap={{ scale: 0.95 }}
          >
            <img src={img.url} alt={img.name} loading="lazy" />
          </motion.button>
        ))}
      </div>
    </Modal>
  );
}
