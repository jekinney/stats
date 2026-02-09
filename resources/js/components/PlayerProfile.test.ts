import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PlayerProfile from './PlayerProfile.vue';

describe('PlayerProfile', () => {
    const mockPlayer = {
        id: 1,
        name: 'TestPlayer',
        skill: 2000,
        kills: 500,
        deaths: 250,
        kd_ratio: 2.0,
        headshot_percentage: 45.5,
        favorite_weapon: 'ak47',
        weapon_stats: [
            { weapon: 'ak47', kills: 200, deaths: 50 },
            { weapon: 'awp', kills: 150, deaths: 30 },
        ],
        recent_kills: [
            { id: 1, victim: 'Enemy1', weapon: 'ak47', headshot: true },
            { id: 2, victim: 'Enemy2', weapon: 'awp', headshot: false },
        ],
    };

    it('renders player name', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(wrapper.text()).toContain('TestPlayer');
    });

    it('displays skill points', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(wrapper.text()).toContain('2000');
    });

    it('displays KD ratio', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(wrapper.text()).toContain('2.00');
    });

    it('displays headshot percentage', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(wrapper.text()).toContain('45.5%');
    });

    it('renders weapon statistics table', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(wrapper.find('[data-testid="weapon-stats"]').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('ak47');
        expect(wrapper.text()).toContain('awp');
    });

    it('shows favorite weapon badge', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(
            wrapper.find('[data-testid="favorite-weapon"]').text(),
        ).toContain('ak47');
    });

    it('displays recent kills feed', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        const killFeed = wrapper.find('[data-testid="recent-kills"]');
        expect(killFeed.exists()).toBe(true);
        expect(killFeed.text()).toContain('Enemy1');
        expect(killFeed.text()).toContain('Enemy2');
    });

    it('shows headshot icon for headshot kills', () => {
        const wrapper = mount(PlayerProfile, {
            props: { player: mockPlayer },
        });

        expect(wrapper.find('[data-testid="headshot-icon"]').exists()).toBe(
            true,
        );
    });
});
