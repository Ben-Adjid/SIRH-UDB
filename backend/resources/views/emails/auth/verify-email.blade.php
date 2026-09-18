<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de votre adresse email</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #059669, #10b981); padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800;">SIRH Dakar</h1>
                            <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 12px; letter-spacing: 2px; text-transform: uppercase;">Vérification d'email</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #1e293b; margin: 0 0 16px; font-size: 18px;">Bienvenue, {{ $user->name }} !</h2>
                            <p style="color: #475569; margin: 0 0 24px; line-height: 1.6;">
                                Merci pour votre inscription. Veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous.
                            </p>
                            <p style="color: #475569; margin: 0 0 32px; line-height: 1.6;">
                                Ce lien de vérification expirera dans <strong>24 heures</strong>.
                            </p>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center">
                                        <a href="{{ url('/verify-email?token=' . $token . '&email=' . urlencode($email)) }}"
                                           style="display: inline-block; background: linear-gradient(135deg, #059669, #10b981); color: #ffffff; text-decoration: none; padding: 14px 40px; border-radius: 12px; font-weight: 700; font-size: 14px; letter-spacing: 0.5px;">
                                            Vérifier mon adresse email
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="color: #94a3b8; margin: 32px 0 0; font-size: 12px; line-height: 1.6;">
                                Si vous n'avez pas créé de compte sur SIRH Dakar, ignorez cet email.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="color: #94a3b8; margin: 0; font-size: 11px;">
                                © {{ date('Y') }} Université Dakar Bourguiba — SIRH Dakar
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
