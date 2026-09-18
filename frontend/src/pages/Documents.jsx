import { useEffect, useRef, useState } from 'react';
import { FileText, Download, Search, File, Image, Upload, Trash2, Edit, User } from 'lucide-react';
import api from '../services/api';
import toast from '../utils/toast';
import FormModal from '../components/FormModal';
import ConfirmModal from '../components/ConfirmModal';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';

const TYPE_LABELS = { cv: 'CV', contract: 'Contrat', diploma: 'Diplome', other: 'Autre' };
const FILE_ICONS = { 'application/pdf': { icon: FileText, color: 'text-red-500', bg: 'bg-red-50' }, 'image/jpeg': { icon: Image, color: 'text-blue-500', bg: 'bg-blue-50' }, 'image/png': { icon: Image, color: 'text-green-500', bg: 'bg-green-50' } };

const Documents = () => {
    const user = useAuthStore((state) => state.user);
    const canSelectTeacher = hasAnyRole(user, ['admin', 'hr_manager', 'secretary', 'accountant']);
    const fileInput = useRef(null);
    const [teachers, setTeachers] = useState([]);
    const [teacherId, setTeacherId] = useState(user?.teacher?.id || '');
    const [documents, setDocuments] = useState([]);
    const [search, setSearch] = useState('');
    const [type, setType] = useState('');
    const [loading, setLoading] = useState(true);
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [form, setForm] = useState({ name: '', type: 'other', file: null });
    const [deleteDocument, setDeleteDocument] = useState(null);

    useEffect(() => {
        if (!canSelectTeacher) return;
        api.get('/teachers', { params: { per_page: 200 } }).then((response) => {
            const data = response.data;
            const list = Array.isArray(data) ? data : (data.data || []);
            setTeachers(list);
            if (!teacherId && list[0]) setTeacherId(list[0].id);
        }).catch(() => toast.error('Impossible de charger les enseignants'));
    }, [canSelectTeacher]);

    const loadDocuments = async () => {
        if (!teacherId) { setLoading(false); return; }
        setLoading(true);
        try {
            const response = await api.get(`/teachers/${teacherId}/documents`, { params: { per_page: 100, search: search || undefined, type: type || undefined } });
            const data = response.data;
            setDocuments(Array.isArray(data) ? data : (data.data || []));
        } catch (error) {
            toast.error(error.response?.data?.message || 'Erreur lors du chargement des documents');
        } finally { setLoading(false); }
    };

    useEffect(() => { loadDocuments(); }, [teacherId, search, type]);

    const openCreate = () => { setEditing(null); setForm({ name: '', type: 'other', file: null }); setShowForm(true); };
    const openEdit = (document) => { setEditing(document); setForm({ name: document.name, type: document.type || 'other', file: null }); setShowForm(true); };

    const submit = async (event) => {
        event.preventDefault();
        try {
            const payload = new FormData();
            payload.append('name', form.name);
            payload.append('type', form.type);
            if (form.file) payload.append('file', form.file);
            if (editing) await api.post(`/documents/${editing.id}?_method=PUT`, payload);
            else { if (!form.file) { toast.error('Selectionnez un fichier'); return; } await api.post(`/teachers/${teacherId}/documents`, payload); }
            toast.success(editing ? 'Document modifie' : 'Document uploade');
            setShowForm(false);
            setEditing(null);
            setForm({ name: '', type: 'other', file: null });
            loadDocuments();
        } catch (error) { toast.error(error.response?.data?.message || 'Operation impossible'); }
    };

    const download = async (document) => {
        try {
            const response = await api.get(`/documents/${document.id}/download`, { responseType: 'blob' });
            const url = URL.createObjectURL(response.data); const link = window.document.createElement('a');
            link.href = url; link.download = document.name; link.click(); URL.revokeObjectURL(url);
        } catch (error) { toast.error(error.response?.data?.message || 'Telechargement impossible'); }
    };

    const remove = async () => {
        try {
            await api.delete(`/documents/${deleteDocument.id}`);
            toast.success('Document supprime');
            loadDocuments();
        } catch (error) {
            toast.error(error.response?.data?.message || 'Suppression impossible');
        } finally {
            setDeleteDocument(null);
        }
    };

    const selectedTeacher = teachers.find((teacher) => String(teacher.id) === String(teacherId));
    return <div className="min-h-screen"><div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2"><div><div className="flex items-center gap-3 mb-1"><div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-500/25"><FileText size={20} className="text-white" /></div><h1 className="text-2xl font-bold text-slate-900">Documents</h1></div><p className="text-slate-400 text-sm ml-[52px] flex items-center gap-1"><User size={12} /> {selectedTeacher?.user?.name || user?.teacher?.user?.name || user?.name || 'Mes documents'}</p></div><button onClick={openCreate} disabled={!teacherId} className="flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white rounded-xl font-semibold text-sm disabled:opacity-40"><Upload size={18} /> Uploader un document</button></div>
        {canSelectTeacher && <select value={teacherId} onChange={(event) => setTeacherId(event.target.value)} className="input-field"><option value="">Selectionner un enseignant</option>{teachers.map((teacher) => <option key={teacher.id} value={teacher.id}>{teacher.user?.name || teacher.name}</option>)}</select>}
        <div className="bg-white rounded-2xl border border-slate-100 p-3 flex flex-col sm:flex-row gap-3"><div className="relative flex-1"><Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Rechercher un document..." className="w-full pl-10 pr-4 py-2.5 bg-slate-50 rounded-xl text-sm focus:outline-none" /></div><select value={type} onChange={(event) => setType(event.target.value)} className="input-field sm:w-48"><option value="">Tous les types</option>{Object.entries(TYPE_LABELS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></div>
        {loading ? <div className="flex justify-center py-20"><div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" /></div> : documents.length === 0 ? <div className="text-center py-20 text-slate-400">Aucun document</div> : <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">{documents.map((document) => { const config = FILE_ICONS[document.mime_type] || { icon: File, color: 'text-slate-500', bg: 'bg-slate-50' }; const Icon = config.icon; return <div key={document.id} className="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm"><div className="flex items-start justify-between"><div className={`w-11 h-11 rounded-xl ${config.bg} flex items-center justify-center`}><Icon size={20} className={config.color} /></div><div className="flex gap-1"><button onClick={() => openEdit(document)} className="p-2 text-slate-400 hover:text-blue-600" title="Modifier"><Edit size={15} /></button><button onClick={() => setDeleteDocument(document)} className="p-2 text-slate-400 hover:text-red-600" title="Supprimer"><Trash2 size={15} /></button><button onClick={() => download(document)} className="p-2 text-slate-400 hover:text-blue-600" title="Telecharger"><Download size={15} /></button></div></div><h3 className="font-bold text-slate-900 text-sm mt-4 truncate">{document.name}</h3><p className="text-xs text-slate-400 mt-2">{TYPE_LABELS[document.type] || document.type} · {document.file_size ? `${Math.round(document.file_size / 1024)} Ko` : '—'}</p></div>; })}</div>}
    </div><FormModal isOpen={showForm} onClose={() => setShowForm(false)} onSubmit={submit} title={editing ? 'Modifier le document' : 'Uploader un document'} submitText={editing ? 'Enregistrer' : 'Uploader'}><div className="space-y-4"><div><label className="form-label">Nom</label><input required className="input-field" value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></div><div><label className="form-label">Type</label><select className="input-field" value={form.type} onChange={(event) => setForm({ ...form, type: event.target.value })}>{Object.entries(TYPE_LABELS).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></div><div><label className="form-label">Fichier {editing ? '(optionnel)' : ''}</label><input ref={fileInput} required={!editing} type="file" accept=".pdf,.doc,.docx,.jpeg,.png" className="input-field" onChange={(event) => setForm({ ...form, file: event.target.files?.[0] || null })} /></div></div></FormModal><ConfirmModal isOpen={Boolean(deleteDocument)} onClose={() => setDeleteDocument(null)} onConfirm={remove} title="Supprimer le document" message={`Voulez-vous supprimer « ${deleteDocument?.name || ''} » ?`} /></div>;
};

export default Documents;