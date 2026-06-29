import { QRCodeSVG } from 'qrcode.react';
import { Modal } from './Modal';

/**
 * Build the player-view URL for a code. The host can be configured via the
 * VITE_PLAYER_BASE_URL env var (e.g. "http://192.168.1.20:5173") so a QR code
 * scanned on a phone points at a reachable LAN address instead of "localhost".
 * Falls back to the current origin when unset.
 */
export function playerUrl(code: string): string {
  const configured = (import.meta.env.VITE_PLAYER_BASE_URL ?? '').toString().trim();
  const base = (configured || window.location.origin).replace(/\/+$/, '');
  return `${base}/play/${code}`;
}

interface Props {
  open: boolean;
  onClose: () => void;
  code: string;
}

/** Modal with a QR code that opens the read-only player view when scanned. */
export function QrCodeModal({ open, onClose, code }: Props) {
  const url = playerUrl(code);
  return (
    <Modal open={open} title="Scan to join as a player" onClose={onClose}>
      <div className="qr-wrap">
        <div className="qr-frame">
          <QRCodeSVG value={url} size={240} level="M" />
        </div>
        <p className="muted" style={{ textAlign: 'center', margin: 0 }}>
          Scan with a phone camera to open the player view.
        </p>
        <a className="qr-url" href={url} target="_blank" rel="noreferrer">
          {url}
        </a>
      </div>
    </Modal>
  );
}
