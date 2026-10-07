<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activation de compte - SIRH UDB</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #003d82 0%, #004fa3 100%); color: white; padding: 40px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; }
        .header p { margin: 10px 0 0 0; font-size: 14px; opacity: 0.9; }
        .content { padding: 40px 30px; }
        .welcome { font-size: 18px; color: #003d82; margin-bottom: 20px; }
        .user-name { color: #004fa3; font-weight: bold; }
        .intro-text { color: #555; margin-bottom: 30px; }
        .cta-button { display: inline-block; padding: 14px 40px; background: linear-gradient(135deg, #003d82 0%, #004fa3 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 30px 0; text-align: center; }
        .cta-container { text-align: center; }
        .info-box { background-color: #f0f4f8; border-left: 4px solid #003d82; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .info-box h3 { margin-top: 0; color: #003d82; }
        .info-box ul { margin: 10px 0; padding-left: 20px; }
        .info-box li { margin: 8px 0; }
        .requirements { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .requirements h3 { margin-top: 0; color: #856404; }
        .requirements ul { margin: 10px 0; padding-left: 20px; }
        .requirements li { margin: 6px 0; }
        .footer { background-color: #f9f9f9; padding: 20px 30px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
        .footer-text { margin: 0; }
        .divider { border: none; border-top: 1px solid #ddd; margin: 30px 0; }
        .icon { font-size: 40px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="icon">🎓</div>
            <h1>Bienvenue sur le SIRH</h1>
            <p>Système d'Information des Ressources Humaines</p>
            <p>Université Dakar Bourguiba</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="welcome">Bonjour <span class="user-name">{{ $user->name }}</span>,</p>

            <p class="intro-text">
                Nous sommes heureux de vous accueillir sur la plateforme SIRH de l'Université Dakar Bourguiba.
                Un compte utilisateur a été créé pour vous afin de vous permettre d'accéder aux services de gestion
                des ressources humaines, incluant la gestion des paies, des présences, des congés, et bien d'autres fonctionnalités.
            </p>

            <!-- CTA Button -->
            <div class="cta-container">
                <a href="{{ $activationLink }}" class="cta-button">✓ Activer mon compte</a>
            </div>

            <p style="text-align: center; color: #666; font-size: 12px;">
                Si le bouton ne fonctionne pas, copiez ce lien:<br>
                <code style="background: #f5f5f5; padding: 5px 10px; border-radius: 3px; word-break: break-all;">{{ $activationLink }}</code>
            </p>

            <!-- Important Info -->
            <div class="info-box">
                <h3>📋 Informations importantes</h3>
                <ul>
                    <li><strong>Validité du lien:</strong> 48 heures à partir de la réception de cet email</li>
                    <li><strong>Usage unique:</strong> Ce lien ne peut être utilisé qu'une seule fois pour activer votre compte</li>
                    <li><strong>Confidentialité:</strong> Ne partagez jamais ce lien avec d'autres personnes</li>
                    <li><strong>Non demandé:</strong> Si vous n'avez pas demandé la création de ce compte, ignorez cet email</li>
                </ul>
            </div>

            <!-- Password Requirements -->
            <div class="requirements">
                <h3>🔐 Critères de sécurité pour votre mot de passe</h3>
                <p>Lors de l'activation, votre mot de passe doit contenir:</p>
                <ul>
                    <li>✓ Minimum <strong>12 caractères</strong></li>
                    <li>✓ Au moins <strong>une lettre majuscule</strong> (A-Z)</li>
                    <li>✓ Au moins <strong>une lettre minuscule</strong> (a-z)</li>
                    <li>✓ Au moins <strong>un chiffre</strong> (0-9)</li>
                    <li>✓ Au moins <strong>un symbole spécial</strong> (@$!%*?&#)</li>
                </ul>
            </div>

            <!-- Next Steps -->
            <div class="info-box">
                <h3>📝 Prochaines étapes</h3>
                <ul>
                    <li><strong>Étape 1:</strong> Cliquez sur "Activer mon compte"</li>
                    <li><strong>Étape 2:</strong> Choisissez un mot de passe sécurisé respectant les critères</li>
                    <li><strong>Étape 3:</strong> Confirmez votre mot de passe</li>
                    <li><strong>Étape 4:</strong> Connectez-vous avec vos identifiants</li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="info-box">
                <h3>❓ Questions?</h3>
                <p>
                    Si vous rencontrez des problèmes lors de l'activation ou avez des questions,
                    contactez l'équipe d'administration du SIRH.
                </p>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                <strong>SIRH - Université Dakar Bourguiba</strong><br>
                "De la ténèbre vers la lumière"<br>
                Dakar, Sénégal<br>
                © 2026 - Tous droits réservés
            </p>
            <p class="footer-text" style="margin-top: 15px; border-top: 1px solid #ddd; padding-top: 15px;">
                <em>Cet email a été envoyé automatiquement. Veuillez ne pas y répondre directement.</em>
            </p>
        </div>
    </div>
</body>
</html>
