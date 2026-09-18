import { memo } from 'react';

const SkeletonCard = memo(({ className = '' }) => (
    <div className={`glass rounded-[2rem] p-6 border border-white/50 ${className}`}>
        <div className="flex justify-between items-start">
            <div className="skeleton w-12 h-12 rounded-2xl" />
            <div className="skeleton w-16 h-4 rounded-lg" />
        </div>
        <div className="mt-8 space-y-2">
            <div className="skeleton w-20 h-3 rounded-lg" />
            <div className="skeleton w-28 h-8 rounded-xl" />
        </div>
    </div>
));

const SKELETON_CHART_WIDTHS = [60, 75, 90, 65, 80, 70, 55, 85];

const SkeletonChart = memo(({ className = '' }) => (
    <div className={`glass rounded-[2rem] p-6 border border-white/50 ${className}`}>
        <div className="skeleton w-40 h-5 rounded-lg mb-6" />
        <div className="space-y-3">
            {[...Array(6)].map((_, i) => (
                <div key={i} className="flex items-center gap-4">
                    <div className="skeleton w-8 h-3 rounded" />
                    <div className="skeleton flex-1 h-8 rounded-xl" style={{ width: `${SKELETON_CHART_WIDTHS[i % SKELETON_CHART_WIDTHS.length]}%` }} />
                </div>
            ))}
        </div>
    </div>
));

const SkeletonActivityList = memo(({ className = '' }) => (
    <div className={`glass rounded-[2rem] p-6 border border-white/50 ${className}`}>
        <div className="skeleton w-36 h-5 rounded-lg mb-6" />
        <div className="space-y-3">
            {[...Array(5)].map((_, i) => (
                <div key={i} className="flex items-center gap-4 p-4 bg-gray-50/50 rounded-2xl">
                    <div className="skeleton w-10 h-10 rounded-xl shrink-0" />
                    <div className="flex-1 space-y-2">
                        <div className="skeleton w-32 h-3 rounded-lg" />
                        <div className="skeleton w-20 h-2 rounded-lg" />
                    </div>
                    <div className="skeleton w-12 h-2 rounded-lg" />
                </div>
            ))}
        </div>
    </div>
));

const SkeletonTable = memo(({ rows = 5, cols = 4, className = '' }) => (
    <div className={`glass rounded-[2rem] border border-white/50 ${className}`}>
        <div className="px-8 py-6 border-b border-gray-50">
            <div className="flex items-center gap-4">
                {[...Array(cols)].map((_, i) => (
                    <div key={i} className="skeleton h-3 rounded-lg flex-1" />
                ))}
            </div>
        </div>
        <div className="divide-y divide-gray-50">
            {[...Array(rows)].map((_, i) => (
                <div key={i} className="px-8 py-6">
                    <div className="flex items-center gap-4">
                        {[...Array(cols)].map((_, j) => (
                            <div key={j} className="skeleton h-4 rounded-lg flex-1" style={{ opacity: 1 - j * 0.15 }} />
                        ))}
                    </div>
                </div>
            ))}
        </div>
    </div>
));

const SkeletonSchedule = memo(({ className = '' }) => (
    <div className={`glass rounded-[2rem] p-6 border border-white/50 ${className}`}>
        <div className="flex items-center gap-4 mb-8">
            {[...Array(5)].map((_, i) => (
                <div key={i} className="skeleton flex-1 h-10 rounded-xl" />
            ))}
        </div>
        <div className="space-y-4">
            {[...Array(4)].map((_, i) => (
                <div key={i} className="flex gap-4">
                    <div className="skeleton w-16 h-16 rounded-xl shrink-0" />
                    <div className="flex-1 space-y-2">
                        <div className="skeleton w-3/4 h-4 rounded-lg" />
                        <div className="skeleton w-1/2 h-3 rounded-lg" />
                        <div className="skeleton w-2/3 h-12 rounded-xl" />
                    </div>
                </div>
            ))}
        </div>
    </div>
));

const SkeletonGrid = memo(({ cards = 6, className = '' }) => (
    <div className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 ${className}`}>
        {[...Array(cards)].map((_, i) => (
            <div key={i} className="glass rounded-[2rem] p-6 border border-white/50">
                <div className="flex items-start justify-between mb-4">
                    <div className="skeleton w-14 h-14 rounded-2xl" />
                    <div className="skeleton w-16 h-5 rounded-lg" />
                </div>
                <div className="skeleton w-3/4 h-5 rounded-lg mb-2" />
                <div className="skeleton w-1/2 h-3 rounded-lg mb-3" />
                <div className="skeleton w-full h-12 rounded-xl mb-3" />
                <div className="flex gap-2">
                    <div className="skeleton w-8 h-8 rounded-lg" />
                    <div className="skeleton w-8 h-8 rounded-lg" />
                </div>
            </div>
        ))}
    </div>
));

export { SkeletonCard, SkeletonChart, SkeletonActivityList, SkeletonTable, SkeletonSchedule, SkeletonGrid };