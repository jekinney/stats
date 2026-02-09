import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import WeaponStatistics from './WeaponStatistics.vue';

describe('WeaponStatistics', () => {
    const mockWeapons = [
        {
            code: 'ak47',
            name: 'AK-47',
            kills: 1500,
            deaths: 800,
            accuracy: 42.5,
            headshot_percentage: 38.2,
        },
        {
            code: 'awp',
            name: 'AWP',
            kills: 1200,
            deaths: 400,
            accuracy: 65.3,
            headshot_percentage: 55.8,
        },
    ];

    it('renders weapon statistics table', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: mockWeapons },
        });

        expect(wrapper.find('table').exists()).toBe(true);
        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
    });

    it('displays weapon names', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: mockWeapons },
        });

        expect(wrapper.text()).toContain('AK-47');
        expect(wrapper.text()).toContain('AWP');
    });

    it('displays kill and death counts', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: mockWeapons },
        });

        expect(wrapper.text()).toContain('1,500');
        expect(wrapper.text()).toContain('800');
    });

    it('displays accuracy percentage', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: mockWeapons },
        });

        expect(wrapper.text()).toContain('42.5%');
    });

    it('displays headshot percentage', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: mockWeapons },
        });

        expect(wrapper.text()).toContain('38.2%');
    });

    it('sorts by kills descending by default', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: mockWeapons },
        });

        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].text()).toContain('AK-47'); // Higher kills first
    });

    it('shows empty state when no weapons', () => {
        const wrapper = mount(WeaponStatistics, {
            props: { weapons: [] },
        });

        expect(wrapper.text()).toContain('No weapon statistics');
    });
});
