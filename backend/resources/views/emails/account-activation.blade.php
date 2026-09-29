<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Activation de compte</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h1 style="color: #2c3e50;">Bienvenue sur le SIRH UDB</h1>

        <p>Bonjour <strong>{{ $user->name }}</strong>,</p>

        <p>Un compte a été créé pour vous sur le système de gestion des ressources humaines de l'Université Dakar Bourguiba.</p>

        <p style="text-align: center; margin: 30px 0;">
            <a href="{{ $activationLink }}" style="display: inline-block; padding: 12px 30px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Activer mon compte
            </a>
        </p>

        <h2 style="color: #2c3e50; font-size: 16px;">Informations importantes:</h2>
        <ul>
            <li><strong>Lien valable:</strong> 48 heures</li>
            <li><strong>Usage unique:</strong> Ce lien ne peut être utilisé qu'une seule fois</li>
            <li><strong>Sécurité:</strong> Si vous n'êtes pas concerné(e), ignorez ce message</li>
        </ul>

        <h2 style="color: #2c3e50; font-size: 16px;">À savoir lors de l'activation:</h2>
        <p>Votre mot de passe doit contenir:</p>
        <ul>
            <li>Au minimum 12 caractères</li>
            <li>Au moins une lettre majuscule</li>
            <li>Au moins une lettre minuscule</li>
            <li>Au moins un chiffre</li>
            <li>Au moins un symbole spécial (@$!%*?&#)</li>
        </ul>

        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">

        <p style="color: #666; font-size: 14px;">
            Cordialement,<br>
            <strong>SIRH - Université Dakar Bourguiba</strong>
        </p>
    </div>
</body>
</html>
