interface Props {
  src: string | null;
  name: string;
  className?: string;
}

/** Round portrait that falls back to the first initial when no image. */
export function Portrait({ src, name, className }: Props) {
  const initial = name.trim().charAt(0).toUpperCase() || '?';
  if (src) {
    return <img className={className ?? 'thumb'} src={src} alt={name} />;
  }
  return (
    <div className={`${className ?? 'thumb'} placeholder`} aria-label={name}>
      {initial}
    </div>
  );
}
