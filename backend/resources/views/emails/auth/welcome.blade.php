<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur SIRH Dakar</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #2563eb, #4f46e5); padding: 40px 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: 800;">SIRH Dakar</h1>
                            <p style="color: rgba(255,255,255,0.8); margin: 8px 0 0; font-size: 12px; letter-spacing: 2px; text-transform: uppercase;">Bienvenue</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px 30px;">
                            <h2 style="color: #1e293b; margin: 0 0 16px; font-size: 18px;">Bienvenue, {{ $user->name }} !</h2>
                            <p style="color: #475569; margin: 0 0 24px; line-height: 1.6;">
                                Votre compte a été créé sur le système SIRH Dakar.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px;">
                                        <p style="color: #64748b; margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Votre adresse email (identifiant)</p>
                                        <p style="color: #1e293b; margin: 0; font-size: 16px; font-weight: 700;">{{ $user->email }}</p>
                                    </td>
                                </tr>
                            </table>


                            @if($pinCode)
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 12px; padding: 20px;">
                                        <p style="color: #075985; margin: 0 0 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">Votre code PIN de pointage</p>
                                        <p style="color: #1e293b; margin: 0; font-size: 20px; font-weight: 800; letter-spacing: 3px; font-family: monospace;">{{ $pinCode }}</p>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 24px;">
                                <tr>
                                    <td style="background-color: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 12px; padding: 16px 20px;">
                                        <p style="color: #075985; margin: 0; font-size: 13px; line-height: 1.6;">
                                            <strong>Note :</strong> Ce fichier est conservé pour compatibilité. Le nouveau flux utilise AccountActivationMail.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="color: #94a3b8; margin: 32px 0 0; font-size: 12px; line-height: 1.6;">
                                Ce fichier ne contient plus d'informations sensibles. Veuillez consulter l'email d'activation pour activer votre compte.
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
