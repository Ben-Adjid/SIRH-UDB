<div style="font-family: sans-serif; padding: 20px;">
    <h2>Bonjour {{ $user->name }},</h2>
    <p>Le statut de votre demande de congé du <strong>{{ \Carbon\Carbon::parse($leave['start_date'])->format('d/m/Y') }}</strong> au <strong>{{ \Carbon\Carbon::parse($leave['end_date'])->format('d/m/Y') }}</strong> a été mis à jour.</p>
    <p>Nouveau statut : <strong style="color: {{ ($leave['status'] ?? null) === 'approved' ? 'green' : 'red' }}">{{ strtoupper($leave['status'] ?? '') }}</strong></p>
    @if(!empty($leave['rejection_reason']))
        <p>Motif du refus : {{ $leave['rejection_reason'] }}</p>
    @endif
    <hr>
    <p>Ceci est un message automatique de votre SIRH Dakar.</p>
</div>
