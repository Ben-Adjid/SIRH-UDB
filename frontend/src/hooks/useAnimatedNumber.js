import { useState, useEffect, useRef } from 'react';

export default function useAnimatedNumber(target, duration = 800, enabled = true) {
    const [value, setValue] = useState(0);
    const frameRef = useRef(null);
    const startTimeRef = useRef(null);
    const startValueRef = useRef(0);

    useEffect(() => {
        if (!enabled) return;

        startValueRef.current = 0;
        startTimeRef.current = null;

        const step = (timestamp) => {
            if (!startTimeRef.current) startTimeRef.current = timestamp;
            const elapsed = timestamp - startTimeRef.current;
            const progress = Math.min(elapsed / duration, 1);

            const easeOut = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(startValueRef.current + (target - startValueRef.current) * easeOut);
            setValue(current);

            if (progress < 1) {
                frameRef.current = requestAnimationFrame(step);
            } else {
                setValue(target);
            }
        };

        frameRef.current = requestAnimationFrame(step);
        return () => { if (frameRef.current) cancelAnimationFrame(frameRef.current); };
    }, [target, duration, enabled]);

    return enabled ? value : target;
}
