@component('mail::message')
# Bienvenue sur le SIRH UDB

Bonjour {{ $user->name }},

Un compte a été créé pour vous sur le système de gestion des ressources humaines de l'Université Dakar Bourguiba.

Pour activer votre compte et définir votre mot de passe, veuillez cliquer sur le bouton ci-dessous:

@component('mail::button', ['url' => $activationLink])
Activer mon compte
@endcomponent

## Informations importantes:

- **Lien valable:** 48 heures
- **Usage unique:** Ce lien ne peut être utilisé qu'une seule fois
- **Sécurité:** Si vous n'êtes pas concerné(e), ignorez ce message

## À savoir lors de l'activation:

Votre mot de passe doit contenir:
- Au minimum 12 caractères
- Au moins une lettre majuscule
- Au moins une lettre minuscule
- Au moins un chiffre
- Au moins un symbole spécial (@$!%*?&#)

---

Cordialement,

**SIRH - Université Dakar Bourguiba**
