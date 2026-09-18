import { useState, useEffect, useRef } from 'react';

const SimpleChart = ({ data, color = 'bg-blue-500', title }) => {
    const hasData = data && data.length > 0;
    const maxVal = hasData ? Math.max(...data.map(d => d.value ?? 0)) : 0;
    const [animated, setAnimated] = useState(false);
    const chartRef = useRef(null);

    useEffect(() => {
        const el = chartRef.current;
        if (!el) return;
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                requestAnimationFrame(() => setAnimated(true));
                observer.disconnect();
            }
        }, { threshold: 0.1 });
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    const getGradient = () => {
        if (color.includes('indigo')) return ['#6366f1', '#4338ca'];
        if (color.includes('emerald')) return ['#10b981', '#047857'];
        if (color.includes('amber')) return ['#f59e0b', '#b45309'];
        if (color.includes('red')) return ['#ef4444', '#b91c1c'];
        if (color.includes('purple')) return ['#a855f7', '#7e22ce'];
        return ['#3b82f6', '#1d4ed8'];
    };

    const grad = getGradient();

    return (
        <div ref={chartRef} className="bg-white/80 backdrop-blur-lg p-6 rounded-[2.5rem] shadow-2xl shadow-slate-200/30 border border-white/60 flex flex-col h-full card-hover">
            <div className="flex justify-between items-center mb-8">
                <div>
                    <h3 className="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">{title}</h3>
                    {hasData && (
                        <p className="text-[9px] font-bold text-gray-300 mt-1">
                            Total: {data.reduce((s, d) => s + (d.value || 0), 0).toLocaleString('fr-FR')}
                        </p>
                    )}
                </div>
                <div className="flex space-x-1.5">
                    <span className="w-2 h-2 rounded-full" style={{ backgroundColor: grad[0] }}></span>
                    <span className="w-2 h-2 rounded-full" style={{ backgroundColor: grad[1] }}></span>
                </div>
            </div>
            
            <div className={`flex-1 flex items-end space-x-2 md:space-x-4 h-48 px-2 ${!hasData ? 'justify-center items-center' : ''}`}>
                {hasData ? data.map((d, i) => {
                    const heightPercent = maxVal > 0 ? (d.value / maxVal) * 100 : 0;
                    const displayHeight = animated ? heightPercent : 0;
                    const safeValue = d.value !== undefined && d.value !== null ? d.value : 0;
                    const safeLabel = d.label !== undefined && d.label !== null ? d.label : '';
                    return (
                        <div key={i} className="flex-1 flex flex-col items-center group relative h-full justify-end">
                            <div className="absolute -top-10 scale-0 group-hover:scale-100 transition-all duration-300 z-10">
                                <div className="bg-gray-900 text-white text-[10px] font-black py-1.5 px-3 rounded-full whitespace-nowrap shadow-xl shadow-gray-900/30">
                                    {safeValue.toLocaleString('fr-FR')}
                                    <div className="absolute -bottom-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-gray-900 rotate-45"></div>
                                </div>
                            </div>

                            <div 
                                className="w-full max-w-[24px] rounded-full transition-all duration-1000 ease-out flex flex-col justify-end overflow-hidden group-hover:brightness-110 group-hover:scale-105 group-hover:shadow-lg origin-bottom cursor-pointer relative"
                                style={{ 
                                    height: `${displayHeight}%`, 
                                    minHeight: displayHeight > 0 ? '6px' : '0px',
                                    background: `linear-gradient(180deg, ${grad[0]} 0%, ${grad[1]} 100%)`,
                                    transitionDelay: `${i * 0.05}s`,
                                }}
                            >
                                <div className="w-full h-1/3 bg-white/10 rounded-full"></div>
                                {displayHeight > 20 && (
                                    <div className="absolute inset-x-0 top-0 h-1/2 bg-white/5 rounded-full"></div>
                                )}
                            </div>
                            
                            <span className="text-[9px] font-black text-gray-400 mt-4 uppercase tracking-tighter truncate w-full text-center">
                                {safeLabel}
                            </span>
                        </div>
                    );
                }) : (
                    <div className="flex flex-col items-center justify-center text-center opacity-40">
                        <div className="w-14 h-14 bg-gray-100/50 rounded-2xl mb-4 flex items-center justify-center border border-gray-200/50">
                            <svg className="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>
                        </div>
                        <p className="text-[10px] font-black uppercase tracking-widest text-gray-400">Aucune donnée disponible</p>
                    </div>
                )}
            </div>
        </div>
    );
};

export default SimpleChart;
