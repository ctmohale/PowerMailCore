import react from '@vitejs/plugin-react';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, loadEnv } from 'vite';

const webDir = path.dirname(fileURLToPath(import.meta.url));
const workspaceRoot = path.resolve(webDir, '..', '..');

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, workspaceRoot, '');

  return {
    envDir: workspaceRoot,
    plugins: [react()],
    server: {
      host: '127.0.0.1',
      hmr: {
        overlay: false,
      },
      port: Number(env.VITE_DEV_PORT || 5174),
      strictPort: true,
    },
  };
});
