import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PlayerRankings from './PlayerRankings.vue';

describe('PlayerRankings', () => {
    it('renders player rankings table', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [
                    {
                        id: 1,
                        name: 'Player1',
                        skill: 2000,
                        kills: 100,
                        deaths: 50,
                        kd_ratio: 2.0,
                    },
                    {
                        id: 2,
                        name: 'Player2',
                        skill: 1500,
                        kills: 80,
                        deaths: 60,
                        kd_ratio: 1.33,
                    },
                ],
            },
        });

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('displays player name', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [
                    {
                        id: 1,
                        name: 'TestPlayer',
                        skill: 2000,
                        kills: 100,
                        deaths: 50,
                        kd_ratio: 2.0,
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('TestPlayer');
    });

    it('displays player skill', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [
                    {
                        id: 1,
                        name: 'Player1',
                        skill: 2000,
                        kills: 100,
                        deaths: 50,
                        kd_ratio: 2.0,
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('2000');
    });

    it('displays KD ratio formatted to 2 decimals', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [
                    {
                        id: 1,
                        name: 'Player1',
                        skill: 2000,
                        kills: 100,
                        deaths: 50,
                        kd_ratio: 2.0,
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('2.00');
    });

    it('emits player-selected event when row clicked', async () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [
                    {
                        id: 1,
                        name: 'Player1',
                        skill: 2000,
                        kills: 100,
                        deaths: 50,
                        kd_ratio: 2.0,
                    },
                ],
            },
        });

        await wrapper.find('tbody tr').trigger('click');

        expect(wrapper.emitted('player-selected')).toBeTruthy();
        expect(wrapper.emitted('player-selected')?.[0]).toEqual([1]);
    });

    it('shows loading state', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [],
                loading: true,
            },
        });

        expect(wrapper.find('[data-testid="loading-spinner"]').exists()).toBe(
            true,
        );
    });

    it('shows empty state when no players', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [],
                loading: false,
            },
        });

        expect(wrapper.text()).toContain('No players found');
    });

    it('sorts by skill descending by default', () => {
        const wrapper = mount(PlayerRankings, {
            props: {
                players: [
                    {
                        id: 1,
                        name: 'Player1',
                        skill: 1500,
                        kills: 80,
                        deaths: 60,
                        kd_ratio: 1.33,
                    },
                    {
                        id: 2,
                        name: 'Player2',
                        skill: 2000,
                        kills: 100,
                        deaths: 50,
                        kd_ratio: 2.0,
                    },
                ],
            },
        });

        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].text()).toContain('Player2'); // Higher skill first
        expect(rows[1].text()).toContain('Player1');
    });
});
