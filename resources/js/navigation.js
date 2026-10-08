import { Squares2X2Icon, Cog6ToothIcon, RectangleStackIcon, InboxArrowDownIcon, LightBulbIcon, EllipsisHorizontalIcon } from '@heroicons/vue/24/outline';

// Add destinations here as their screens become available.
export const navigation = [
    { label: 'Chart', route: 'dashboard', icon: Squares2X2Icon },
    { label: 'Bench', route: 'bench', icon: RectangleStackIcon },
    { label: 'Intake', route: 'intake', icon: InboxArrowDownIcon },
    { label: 'Ideas', route: 'ideas', icon: LightBulbIcon },
    { label: 'Settings', route: 'profile.show', icon: Cog6ToothIcon },
];

export const mobileNavigation = [
    ...navigation.slice(0, 3),
    { label: 'More', route: 'more', icon: EllipsisHorizontalIcon },
];
