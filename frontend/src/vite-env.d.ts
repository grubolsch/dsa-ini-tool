/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_MERCURE_URL?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
