import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MapStatistics from './MapStatistics.vue';

describe('MapStatistics', () => {
    const mockMaps = [
        {
            code: 'de_dust2',
            name: 'Dust II',
            total_rounds: 5000,
            ct_wins: 2800,
            t_wins: 2200,
            average_round_time: 145,
            popularity: 85.5,
        },
        {
            code: 'de_mirage',
            name: 'Mirage',
            total_rounds: 4200,
            ct_wins: 2100,
            t_wins: 2100,
            average_round_time: 160,
            popularity: 78.3,
        },
    ];

    it('renders map statistics list', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        expect(wrapper.findAll('[data-testid="map-item"]')).toHaveLength(2);
    });

    it('displays map names', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        expect(wrapper.text()).toContain('Dust II');
        expect(wrapper.text()).toContain('Mirage');
    });

    it('displays total rounds played', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        expect(wrapper.text()).toContain('5,000');
        expect(wrapper.text()).toContain('4,200');
    });

    it('displays win percentages', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        // CT win rate for dust2: 2800/5000 = 56%
        expect(wrapper.text()).toContain('56%');
    });

    it('displays average round time', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        expect(wrapper.text()).toContain('145');
        expect(wrapper.text()).toContain('160');
    });

    it('displays popularity score', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        expect(wrapper.text()).toContain('85.5%');
    });

    it('sorts by popularity by default', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: mockMaps },
        });

        const items = wrapper.findAll('[data-testid="map-item"]');
        expect(items[0].text()).toContain('Dust II'); // Higher popularity first
    });

    it('shows empty state when no maps', () => {
        const wrapper = mount(MapStatistics, {
            props: { maps: [] },
        });

        expect(wrapper.text()).toContain('No map statistics');
    });
});
