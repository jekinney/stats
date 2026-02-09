// Vitest setup file
import { afterEach } from 'vitest';
import { cleanup } from '@vue/test-utils';

// Cleanup after each test
afterEach(() => {
    cleanup();
});
