import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ServerBrowser from './ServerBrowser.vue';

describe('ServerBrowser', () => {
    const mockServers = [
        {
            id: 1,
            name: 'Official Server #1',
            game: 'csgo',
            map: 'de_dust2',
            players: 18,
            max_players: 24,
            address: '192.168.1.1:27015',
            online: true,
        },
        {
            id: 2,
            name: 'Community Server',
            game: 'csgo',
            map: 'de_mirage',
            players: 12,
            max_players: 20,
            address: '192.168.1.2:27015',
            online: true,
        },
        {
            id: 3,
            name: 'Offline Server',
            game: 'csgo',
            map: 'de_inferno',
            players: 0,
            max_players: 16,
            address: '192.168.1.3:27015',
            online: false,
        },
    ];

    it('renders server list', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: mockServers },
        });

        expect(wrapper.findAll('[data-testid="server-item"]')).toHaveLength(3);
    });

    it('displays server names', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: mockServers },
        });

        expect(wrapper.text()).toContain('Official Server #1');
        expect(wrapper.text()).toContain('Community Server');
    });

    it('displays current map', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: mockServers },
        });

        expect(wrapper.text()).toContain('de_dust2');
        expect(wrapper.text()).toContain('de_mirage');
    });

    it('displays player count', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: mockServers },
        });

        expect(wrapper.text()).toContain('18/24');
        expect(wrapper.text()).toContain('12/20');
    });

    it('shows online status indicator', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: mockServers },
        });

        const onlineIndicators = wrapper.findAll(
            '[data-testid="online-indicator"]',
        );
        expect(onlineIndicators.length).toBeGreaterThan(0);
    });

    it('shows offline servers differently', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: mockServers },
        });

        const offlineIndicators = wrapper.findAll(
            '[data-testid="offline-indicator"]',
        );
        expect(offlineIndicators).toHaveLength(1);
    });

    it('filters online servers only', () => {
        const wrapper = mount(ServerBrowser, {
            props: {
                servers: mockServers,
                showOffline: false,
            },
        });

        expect(wrapper.findAll('[data-testid="server-item"]')).toHaveLength(2);
    });

    it('shows empty state when no servers', () => {
        const wrapper = mount(ServerBrowser, {
            props: { servers: [] },
        });

        expect(wrapper.text()).toContain('No servers available');
    });
});
