import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LiveStats from './LiveStats.vue';

describe('LiveStats', () => {
    const mockStats = {
        total_players: 1523,
        active_servers: 12,
        total_kills: 45678,
        kills_last_hour: 3456,
        top_player: {
            name: 'ProGamer',
            skill: 2500,
            kills: 1234,
        },
        recent_kills: [
            {
                id: 1,
                killer: 'Player1',
                victim: 'Player2',
                weapon: 'ak47',
                headshot: true,
            },
            {
                id: 2,
                killer: 'Player3',
                victim: 'Player4',
                weapon: 'awp',
                headshot: false,
            },
        ],
    };

    it('renders stat cards', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        expect(
            wrapper.findAll('[data-testid="stat-card"]').length,
        ).toBeGreaterThan(0);
    });

    it('displays total players count', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        expect(wrapper.text()).toContain('1,523');
    });

    it('displays active servers count', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        expect(wrapper.text()).toContain('12');
    });

    it('displays kills statistics', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        expect(wrapper.text()).toContain('45,678');
        expect(wrapper.text()).toContain('3,456');
    });

    it('displays top player information', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        expect(wrapper.text()).toContain('ProGamer');
        expect(wrapper.text()).toContain('2,500');
    });

    it('shows recent kills feed', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        expect(wrapper.find('[data-testid="recent-kills"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Player1');
        expect(wrapper.text()).toContain('Player2');
    });

    it('shows loading state', () => {
        const wrapper = mount(LiveStats, {
            props: {
                stats: null,
                loading: true,
            },
        });

        expect(wrapper.find('[data-testid="loading"]').exists()).toBe(true);
    });

    it('formats large numbers with separators', () => {
        const wrapper = mount(LiveStats, {
            props: { stats: mockStats },
        });

        // Should format 45678 as "45,678"
        const text = wrapper.text();
        expect(text).toMatch(/45[,\s]678/);
    });
});
