import { lazy, Suspense, useEffect } from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate, useNavigate } from 'react-router-dom';
import useAuthStore from './store/authStore';
import { setNavigate } from './services/api';
import { ToastContainer } from './utils/toast';
import { hasAnyRole } from './utils/roles';
import ErrorBoundary from './components/ErrorBoundary';
import AccessDenied from './components/AccessDenied';
import Layout from './components/Layout';

const Login = lazy(() => import('./pages/Login'));
const ForgotPassword = lazy(() => import('./pages/ForgotPassword'));
const ResetPassword = lazy(() => import('./pages/ResetPassword'));
const TwoFactorSetup = lazy(() => import('./pages/TwoFactorSetup'));
const TwoFactorVerify = lazy(() => import('./pages/TwoFactorVerify'));
const PhoneVerification = lazy(() => import('./pages/PhoneVerification'));
const VerifyEmail = lazy(() => import('./pages/VerifyEmail'));
const ForcePasswordChange = lazy(() => import('./pages/ForcePasswordChange'));
const Dashboard = lazy(() => import('./pages/Dashboard'));
const Teachers = lazy(() => import('./pages/Teachers'));
const Ufrs = lazy(() => import('./pages/Ufrs'));
const Subjects = lazy(() => import('./pages/Subjects'));
const Classrooms = lazy(() => import('./pages/Classrooms'));
const Schedules = lazy(() => import('./pages/Schedules'));
const Homeworks = lazy(() => import('./pages/Homeworks'));
const Attendance = lazy(() => import('./pages/Attendance'));
const Leaves = lazy(() => import('./pages/Leaves'));
const Payroll = lazy(() => import('./pages/Payroll'));
const Users = lazy(() => import('./pages/Users'));
const Notifications = lazy(() => import('./pages/Notifications'));
const Profile = lazy(() => import('./pages/Profile'));
const Documents = lazy(() => import('./pages/Documents'));

const PageLoader = () => (
    <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
    </div>
);

const NavigateSetter = () => {
    const nav = useNavigate();
    useEffect(() => { setNavigate(nav); }, [nav]);
    return null;
};

const ProtectedRoute = ({ children, allowedRoles = [] }) => {
    const { isAuthenticated, user } = useAuthStore();
    if (!isAuthenticated) return <Navigate to="/login" replace />;
    if (allowedRoles.length > 0 && !hasAnyRole(user, allowedRoles)) {
        return <Navigate to="/access-denied" state={{ requiredRoles: allowedRoles }} replace />;
    }
    return children;
};

const GuestRoute = ({ children }) => {
    const { isAuthenticated } = useAuthStore();
    if (isAuthenticated) return <Navigate to="/dashboard" replace />;
    return children;
};

const TempTokenRoute = ({ children, tokenKey }) => {
    const token = localStorage.getItem(tokenKey);
    if (!token) return <Navigate to="/login" replace />;
    return children;
};

function App() {
    return (
        <Router>
            <NavigateSetter />
            <ToastContainer />
            <ErrorBoundary>
            <Suspense fallback={<PageLoader />}>
            <Routes>
                <Route path="/login" element={<GuestRoute><Login /></GuestRoute>} />
                <Route path="/forgot-password" element={<GuestRoute><ForgotPassword /></GuestRoute>} />
                <Route path="/reset-password" element={<ResetPassword />} />
                <Route path="/verify-email" element={<VerifyEmail />} />
                <Route path="/2fa-verify" element={<TempTokenRoute tokenKey="temp_2fa_token"><TwoFactorVerify /></TempTokenRoute>} />
                <Route path="/force-password-change" element={<TempTokenRoute tokenKey="temp_force_pw_token"><ForcePasswordChange /></TempTokenRoute>} />
                <Route path="/2fa-setup" element={<ProtectedRoute><TwoFactorSetup /></ProtectedRoute>} />
                <Route path="/phone-verify" element={<ProtectedRoute><PhoneVerification /></ProtectedRoute>} />
                <Route path="/access-denied" element={<AccessDenied />} />
                <Route element={<Layout />}>
                    <Route path="/dashboard" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
                    <Route path="/teachers" element={<ProtectedRoute allowedRoles={['admin', 'hr_manager', 'secretary', 'academic_manager', 'accountant']}><Teachers /></ProtectedRoute>} />
                    <Route path="/ufrs" element={<ProtectedRoute allowedRoles={['admin', 'academic_manager']}><Ufrs /></ProtectedRoute>} />
                    <Route path="/subjects" element={<ProtectedRoute allowedRoles={['admin', 'academic_manager']}><Subjects /></ProtectedRoute>} />
                    <Route path="/classrooms" element={<ProtectedRoute allowedRoles={['admin', 'academic_manager']}><Classrooms /></ProtectedRoute>} />
                    <Route path="/users" element={<ProtectedRoute allowedRoles={['admin', 'hr_manager', 'secretary']}><Users /></ProtectedRoute>} />
                    <Route path="/notifications" element={<ProtectedRoute><Notifications /></ProtectedRoute>} />
                    <Route path="/schedules" element={<ProtectedRoute allowedRoles={['admin', 'academic_manager', 'teacher', 'hr_manager', 'secretary']}><Schedules /></ProtectedRoute>} />
                    <Route path="/homeworks" element={<ProtectedRoute allowedRoles={['admin', 'academic_manager', 'teacher', 'hr_manager', 'secretary']}><Homeworks /></ProtectedRoute>} />
                    <Route path="/attendance" element={<ProtectedRoute allowedRoles={['admin', 'hr_manager', 'secretary', 'teacher', 'accountant', 'academic_manager']}><Attendance /></ProtectedRoute>} />
                    <Route path="/leaves" element={<ProtectedRoute allowedRoles={['admin', 'hr_manager', 'secretary', 'academic_manager', 'accountant']}><Leaves /></ProtectedRoute>} />
                    <Route path="/payroll" element={<ProtectedRoute allowedRoles={['admin', 'accountant', 'teacher', 'hr_manager', 'secretary']}><Payroll /></ProtectedRoute>} />
                    <Route path="/documents" element={<ProtectedRoute allowedRoles={['admin', 'hr_manager', 'secretary', 'accountant', 'teacher']}><Documents /></ProtectedRoute>} />
                    <Route path="/profile" element={<ProtectedRoute><Profile /></ProtectedRoute>} />
                    <Route path="*" element={<Navigate to="/dashboard" replace />} />
                </Route>
                <Route path="/" element={<Navigate to="/dashboard" />} />
                <Route path="*" element={<Navigate to="/login" replace />} />
            </Routes>
            </Suspense>
            </ErrorBoundary>
        </Router>
    );
}

export default App;
