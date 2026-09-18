import React from 'react';
import { AlertCircle, RefreshCw } from 'lucide-react';

class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null };
    }

    static getDerivedStateFromError(error) {
        if (error?.name === 'NotFoundError' && error?.message?.includes('removeChild')) {
            return null;
        }
        return { hasError: true, error };
    }

    render() {
        if (this.state.hasError) {
            return (
                <div className="flex flex-col items-center justify-center min-h-[400px] p-8">
                    <div className="glass p-8 rounded-[2rem] border border-red-100 bg-white/80 text-center max-w-md w-full">
                        <div className="w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <AlertCircle className="text-red-500" size={32} />
                        </div>
                        <h2 className="text-lg font-black text-gray-900 mb-2">Une erreur est survenue</h2>
                        <p className="text-sm text-gray-500 mb-6">
                            Une erreur inattendue s'est produite. Veuillez réessayer.
                        </p>
                        <button
                            onClick={() => { window.location.reload(); }}
                            className="bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black text-xs uppercase tracking-widest hover:bg-indigo-700 transition-all inline-flex items-center gap-2"
                        >
                            <RefreshCw size={14} />
                            Recharger la page
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
