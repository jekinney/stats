import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import KillFeed from './KillFeed.vue';

describe('KillFeed', () => {
    it('displays kill events', () => {
        const wrapper = mount(KillFeed, {
            props: {
                kills: [
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
            },
        });

        expect(wrapper.text()).toContain('Player1');
        expect(wrapper.text()).toContain('Player2');
        expect(wrapper.text()).toContain('ak47');
    });

    it('shows headshot icon for headshot kills', () => {
        const wrapper = mount(KillFeed, {
            props: {
                kills: [
                    {
                        id: 1,
                        killer: 'Player1',
                        victim: 'Player2',
                        weapon: 'ak47',
                        headshot: true,
                    },
                ],
            },
        });

        expect(wrapper.find('[data-testid="headshot"]').exists()).toBe(true);
    });

    it('limits display to max items', () => {
        const manyKills = Array.from({ length: 20 }, (_, i) => ({
            id: i,
            killer: `Player${i}`,
            victim: `Victim${i}`,
            weapon: 'ak47',
            headshot: false,
        }));

        const wrapper = mount(KillFeed, {
            props: {
                kills: manyKills,
                maxItems: 10,
            },
        });

        expect(wrapper.findAll('[data-testid="kill-event"]')).toHaveLength(10);
    });

    it('shows empty state when no kills', () => {
        const wrapper = mount(KillFeed, {
            props: {
                kills: [],
            },
        });

        expect(wrapper.text()).toContain('No recent kills');
    });

    it('displays kills in correct order', () => {
        const wrapper = mount(KillFeed, {
            props: {
                kills: [
                    {
                        id: 1,
                        killer: 'First',
                        victim: 'Player1',
                        weapon: 'ak47',
                        headshot: false,
                    },
                    {
                        id: 2,
                        killer: 'Second',
                        victim: 'Player2',
                        weapon: 'awp',
                        headshot: false,
                    },
                ],
            },
        });

        const killItems = wrapper.findAll('[data-testid="kill-event"]');
        expect(killItems[0].text()).toContain('First');
        expect(killItems[1].text()).toContain('Second');
    });
});
