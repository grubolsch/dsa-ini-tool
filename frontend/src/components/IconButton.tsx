import type { ButtonHTMLAttributes } from 'react';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';

interface Props extends ButtonHTMLAttributes<HTMLButtonElement> {
  icon: IconDefinition;
  /** Accessible name + tooltip (icon-only buttons have no visible text). */
  label: string;
  /** Optional visible text after the icon. */
  text?: string;
}

/** Compact icon button. Icon-only by default (with aria-label + title tooltip),
 *  or icon + text when `text` is given. */
export function IconButton({ icon, label, text, className = '', ...rest }: Props) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      className={`btn btn-icon ${text ? '' : 'btn-icon-only'} ${className}`.trim()}
      {...rest}
    >
      <FontAwesomeIcon icon={icon} />
      {text && <span>{text}</span>}
    </button>
  );
}
