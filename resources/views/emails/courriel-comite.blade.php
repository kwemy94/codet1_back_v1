<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $objet }}</title>
</head>
{{-- Styles en ligne : les clients de messagerie ignorent les feuilles externes --}}
<body style="margin:0;padding:0;background:#f7f4ee;font-family:Helvetica,Arial,sans-serif;color:#1a2233;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f4ee;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
               style="max-width:600px;background:#fffdf9;border:1px solid #e2dccf;border-radius:10px;overflow:hidden;">

          {{-- En-tête : indigo profond, charte du comité --}}
          <tr>
            <td style="background:#1e3462;padding:20px 24px;">
              <p style="margin:0;font-size:19px;font-weight:bold;color:#ffffff;letter-spacing:-0.3px;">
                {{ $mentions['sigle'] }}
              </p>
              <p style="margin:4px 0 0;font-size:12px;color:#b8c6e2;line-height:1.5;">
                {{ $mentions['nom_complet'] }}<br>
                Village {{ $mentions['village'] }} — Groupement {{ $mentions['groupement'] }}
              </p>
            </td>
          </tr>

          {{-- Bande de ventilation : la signature visuelle du comité --}}
          <tr>
            <td style="font-size:0;line-height:0;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                  <td width="48%" height="5" style="background:#14224a;"></td>
                  <td width="24%" height="5" style="background:#3c5c96;"></td>
                  <td width="28%" height="5" style="background:#8aa3ca;"></td>
                </tr>
              </table>
            </td>
          </tr>

          <tr>
            <td style="padding:26px 24px;">
              <p style="margin:0 0 18px;font-size:15px;">
                Bonjour {{ trim($membre->nom.' '.($membre->prenom ?? '')) }},
              </p>

              <div style="font-size:15px;line-height:1.6;white-space:pre-wrap;">{{ $contenu }}</div>

              <p style="margin:24px 0 0;font-size:15px;">
                Le bureau du {{ $mentions['sigle'] }}
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:16px 24px;background:#f2ede3;border-top:1px solid #e2dccf;">
              <p style="margin:0;font-size:11.5px;color:#55607a;line-height:1.6;">
                Vous recevez ce message en tant que ressortissant enregistré du village
                {{ $mentions['village'] }}, matricule {{ $membre->matricule }}.<br>
                @if($mentions['telephone']) Secrétariat : {{ $mentions['telephone'] }} @endif
                @if($mentions['email']) — {{ $mentions['email'] }} @endif
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
