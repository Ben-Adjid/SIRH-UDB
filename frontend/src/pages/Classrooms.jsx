import { useEffect, useState } from 'react';
import api from '../services/api';
import { Plus, Search, Edit, Trash2, MapPin, Monitor, Users } from 'lucide-react';
import toast from '../utils/toast';
import ConfirmModal from '../components/ConfirmModal';
import FormModal from '../components/FormModal';
import Pagination from '../components/Pagination';
import useAuthStore from '../store/authStore';
import { hasAnyRole } from '../utils/roles';

const TYPE_CONFIGS = {
    lab: { icon: Monitor, label: 'Laboratoire', gradient: 'from-purple-500 to-violet-600', bg: 'bg-purple-50', text: 'text-purple-600', border: 'border-purple-200', badge: 'badge-purple' },
    amphitheater: { icon: MapPin, label: 'Amphitheatre', gradient: 'from-amber-500 to-orange-600', bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-amber-200', badge: 'badge-amber' },
    classroom: { icon: MapPin, label: 'Salle de Classe', gradient: 'from-emerald-500 to-teal-600', bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-emerald-200', badge: 'badge-blue' },
};

const Classrooms = () => {
    const userAuth = useAuthStore((s) => s.user);
    const canManage = hasAnyRole(userAuth, ['admin', 'academic_manager']);
    const [classrooms, setClassrooms] = useState([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [isEditing, setIsEditing] = useState(false);
    const [selectedRoom, setSelectedRoom] = useState(null);
    const [searchTerm, setSearchTerm] = useState('');
    const [page, setPage] = useState(1);
    const perPage = 12;
    const [pagination, setPagination] = useState({ current_page: 1, last_page: 1, total: 0 });

    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [roomToDelete, setRoomToDelete] = useState(null);

    const [formData, setFormData] = useState({
        name: '', code: '', capacity: '', type: 'classroom',
    });

    const fetchClassrooms = async (pageNum = 1) => {
        setLoading(true);
        try {
            const params = { page: pageNum, per_page: perPage };
            if (searchTerm) params.search = searchTerm;
            const response = await api.get('/classrooms', { params });
            const data = response.data;
            setClassrooms(data.data || []);
            setPagination({ current_page: data.current_page, last_page: data.last_page, total: data.total });
        } catch {
            toast.error('Erreur chargement salles');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchClassrooms(page);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page, searchTerm]);

    useEffect(() => { setPage(1); }, [searchTerm]);

    const handleOpenAdd = () => {
        setIsEditing(false);
        setSelectedRoom(null);
        setFormData({ name: '', code: '', capacity: '', type: 'classroom' });
        setShowModal(true);
    };

    const handleOpenEdit = (room) => {
        setIsEditing(true);
        setSelectedRoom(room);
        setFormData({
            name: room.name,
            code: room.code ?? '',
            capacity: room.capacity ?? '',
            type: room.type || 'classroom'
        });
        setShowModal(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        try {
            if (isEditing) {
                await api.put(`/classrooms/${selectedRoom?.id}`, formData);
                toast.success('Salle mise a jour');
            } else {
                await api.post('/classrooms', formData);
                toast.success('Salle ajoutee');
            }
            setShowModal(false);
            setSelectedRoom(null);
            setSearchTerm('');
            setPage(1);
            fetchClassrooms(1);
        } catch (error) {
            const msg = error.response?.data?.message || error.response?.data?.errors?.name?.[0] || error.response?.data?.errors?.code?.[0] || 'Erreur de sauvegarde';
            toast.error(msg);
        }
    };

    const handleOpenDeleteConfirm = (room) => {
        setRoomToDelete(room);
        setIsConfirmOpen(true);
    };

    const handleDelete = async () => {
        try {
            const res = await api.delete(`/classrooms/${roomToDelete?.id}`);
            toast.success(res.data?.message || 'Salle supprimee');
            fetchClassrooms(page);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Suppression impossible');
        } finally {
            setIsConfirmOpen(false);
            setRoomToDelete(null);
        }
    };

    return (
        <div className="min-h-screen">
            <div className="max-w-[1400px] mx-auto space-y-6 px-2 sm:px-4">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pt-2">
                    <div>
                        <div className="flex items-center gap-3 mb-1">
                            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center shadow-lg shadow-emerald-500/25">
                                <MapPin size={20} className="text-white" />
                            </div>
                            <h1 className="text-2xl font-bold text-slate-900 tracking-tight">Gestion des Salles</h1>
                        </div>
                        <p className="text-slate-400 text-sm ml-[52px]">Hebergement des activites pedagogiques</p>
                    </div>
                    {canManage && (
                        <button onClick={handleOpenAdd}
                            className="flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-700 text-white rounded-xl font-semibold text-sm shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/40 transition-all duration-300 active:scale-[0.97]">
                            <Plus size={18} strokeWidth={2.5} />
                            <span>Ajouter une Salle</span>
                        </button>
                    )}
                </div>

                <div className="bg-white rounded-2xl border border-slate-100 p-3">
                    <div className="relative flex-1">
                        <Search className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-300" size={16} />
                        <input type="text" placeholder="Rechercher une salle, un amphi..."
                            className="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-100 rounded-xl text-sm text-slate-700 placeholder:text-slate-300 focus:outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-500/10 transition-all"
                            value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} />
                    </div>
                </div>

                {loading ? (
                    <div className="flex justify-center py-20">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600" />
                    </div>
                ) : classrooms.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-24">
                        <div className="w-16 h-16 bg-slate-100 rounded-2xl flex items-center justify-center mb-4">
                            <MapPin size={28} className="text-slate-300" />
                        </div>
                        <p className="text-base font-bold text-slate-400 mb-1">Aucune salle disponible</p>
                        <p className="text-xs text-slate-300">{searchTerm ? 'Essayez de modifier votre recherche' : 'Commencez par ajouter une salle'}</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-6">
                        {classrooms.map((room) => {
                            const tc = TYPE_CONFIGS[room.type] || TYPE_CONFIGS.classroom;
                            const Icon = tc.icon;
                            return (
                                <div key={room.id}
                                    className="group relative bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-lg hover:border-emerald-200 transition-all duration-300">
                                    <div className="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-teal-500 opacity-0 group-hover:opacity-100 transition-opacity rounded-t-2xl" />

                                    <div className="flex items-start justify-between mb-4">
                                        <div className={`w-11 h-11 rounded-xl bg-gradient-to-br ${tc.gradient} flex items-center justify-center text-white shadow-md`}>
                                            <Icon size={20} />
                                        </div>
                                        <div className="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            {canManage && (
                                            <>
                                            <button onClick={() => handleOpenEdit(room)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-emerald-500 hover:bg-emerald-50 transition-all">
                                                <Edit size={14} />
                                            </button>
                                            <button onClick={() => handleOpenDeleteConfirm(room)}
                                                className="p-1.5 rounded-lg text-slate-300 hover:text-red-500 hover:bg-red-50 transition-all">
                                                <Trash2 size={14} />
                                            </button>
                                            </>
                                            )}
                                        </div>
                                    </div>

                                    <h3 className="font-bold text-slate-900 text-sm group-hover:text-emerald-600 transition-colors mb-3 uppercase">{room.name}</h3>

                                    <div className="flex flex-wrap gap-1.5 mb-4">
                                        <span className={`inline-flex items-center px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider border ${tc.bg} ${tc.text} ${tc.border}`}>
                                            {tc.label}
                                        </span>
                                    </div>

                                    <div className="grid grid-cols-2 gap-3">
                                        <div className="bg-slate-50 p-3 rounded-xl text-center">
                                            <p className="text-[10px] font-bold text-slate-400 uppercase mb-1">Capacite</p>
                                            <div className="flex items-center justify-center gap-1.5">
                                                <Users size={14} className="text-emerald-500" />
                                                <p className="text-sm font-bold text-slate-900">{room.capacity ?? 'N/A'}</p>
                                            </div>
                                        </div>
                                        <div className="bg-slate-50 p-3 rounded-xl text-center">
                                            <p className="text-[10px] font-bold text-slate-400 uppercase mb-1">Equipement</p>
                                            <div className="flex items-center justify-center gap-1.5">
                                                <Monitor size={14} className="text-blue-500" />
                                                <p className="text-[10px] font-bold text-slate-900 uppercase">
                                                    {room.type === 'lab' ? 'Scientifique' : room.type === 'amphitheater' ? 'Audio/Vidéo' : 'Standard'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}

                <Pagination
                    page={pagination.current_page}
                    totalPages={pagination.last_page}
                    onPageChange={setPage}
                    totalItems={pagination.total}
                    perPage={perPage}
                />
            </div>

            <FormModal
                isOpen={showModal}
                onClose={() => setShowModal(false)}
                onSubmit={handleSubmit}
                title={isEditing ? 'Editer Salle' : 'Ajouter Salle'}
                subtitle="Configuration des espaces de cours"
                submitText="Confirmer Creation"
                submitClassName="px-6 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition-all disabled:opacity-40 shadow-lg shadow-emerald-200"
            >
                <div className="space-y-6">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div className="form-group">
                            <label className="form-label">Designation de la salle</label>
                            <input required type="text" className="input-field" placeholder="Ex: Amphi A, Salle 102..."
                                value={formData.name} onChange={e => setFormData({...formData, name: e.target.value})} />
                        </div>
                        <div className="form-group">
                            <label className="form-label">Code</label>
                            <input required type="text" className="input-field" placeholder="Ex: SAL-102, AM-A"
                                value={formData.code} onChange={e => setFormData({...formData, code: e.target.value})} />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div className="form-group">
                            <label className="form-label">Type d'espace</label>
                            <select required className="select-field"
                                value={formData.type} onChange={e => setFormData({...formData, type: e.target.value})}>
                                <option value="classroom">Salle de Classe</option>
                                <option value="lab">Laboratoire</option>
                                <option value="amphitheater">Amphitheatre</option>
                            </select>
                        </div>
                        <div className="form-group">
                            <label className="form-label">Capacite Max</label>
                            <input required type="number" className="input-field" placeholder="Ex: 50"
                                value={formData.capacity} onChange={e => setFormData({...formData, capacity: e.target.value === '' ? '' : Number(e.target.value)})} />
                        </div>
                    </div>
                </div>
            </FormModal>

            <ConfirmModal
                isOpen={isConfirmOpen}
                onClose={() => setIsConfirmOpen(false)}
                onConfirm={handleDelete}
                title="Suppression Salle"
                message={`Voulez-vous vraiment retirer la salle "${roomToDelete?.name}" de l'inventaire ?`}
                type="danger"
            />
        </div>
    );
};

export default Classrooms;
