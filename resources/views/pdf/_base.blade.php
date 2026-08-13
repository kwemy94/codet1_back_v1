{{-- Gabarit commun des états édités par le comité --}}
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>@yield('titre')</title>
<style>
  @page { margin: 22mm 12mm 18mm; }
  * { box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a2233; margin: 0; }

  .entete { border-bottom: 2px solid #1e3462; padding-bottom: 8px; margin-bottom: 14px; }
  .entete__sigle { font-size: 16pt; font-weight: bold; color: #1e3462; margin: 0; letter-spacing: -0.02em; }
  .entete__nom { font-size: 9.5pt; color: #1a2233; margin: 2px 0 4px; }
  .entete__mentions { font-size: 7.2pt; color: #55607a; line-height: 1.45; margin: 0; }
  .entete__droite { text-align: right; font-size: 7.2pt; color: #55607a; }

  .titre-etat { background: #1e3462; color: #fff; padding: 6px 10px; margin: 0 0 4px;
                font-size: 11pt; font-weight: bold; letter-spacing: 0.02em; }
  .sous-titre { font-size: 8pt; color: #55607a; margin: 0 0 12px; }
  .bandeau-filtre { background: #f2ede3; border-left: 3px solid #4d6a99; color: #1e3462;
                    font-size: 8pt; padding: 6px 9px; margin: 0 0 8px; }

  table { width: 100%; border-collapse: collapse; }
  thead { display: table-header-group; }          /* l'en-tête se répète à chaque page */
  tr { page-break-inside: avoid; }
  th { background: #e4e9f4; color: #1e3462; font-size: 7.5pt; text-transform: uppercase;
       letter-spacing: 0.05em; text-align: left; padding: 6px 7px; border-bottom: 1px solid #c8d3ea; }
  td { padding: 5px 7px; border-bottom: 1px solid #e8e4da; }
  .nombre { text-align: right; white-space: nowrap; }
  .paire { background: #f7f4ee; }
  .totaux td { background: #e4e9f4; font-weight: bold; border-top: 2px solid #1e3462; border-bottom: none; }

  .tenu { color: #69728a; font-size: 7.5pt; }
  .impaye { color: #9c4a2f; font-weight: bold; }
  .solde { color: #1e3462; font-weight: bold; }

  .encadre { border: 1px solid #c8d3ea; background: #f7f4ee; border-radius: 3px;
             padding: 10px 12px; margin-top: 14px; }
  .encadre--alerte { border-color: #e6cec4; background: #f6ebe6; }
  .encadre__titre { font-weight: bold; font-size: 9.5pt; margin: 0 0 6px; }

  .pied { position: fixed; bottom: -12mm; left: 0; right: 0;
          font-size: 7pt; color: #69728a; border-top: 1px solid #e8e4da; padding-top: 4px; }
  .signature { margin-top: 26px; font-size: 8pt; }
</style>
</head>
<body>

<table class="entete">
  <tr>
    <td style="border:none;padding:0;vertical-align:top">
      <p class="entete__sigle">{{ $mentions['sigle'] }}</p>
      <p class="entete__nom">{{ $mentions['nom_complet'] }}</p>
      <p class="entete__mentions">
        Village {{ $mentions['village'] }} — Groupement {{ $mentions['groupement'] }}<br>
        @if($mentions['recepisse']) Récépissé {{ $mentions['recepisse'] }}<br> @endif
        @if($mentions['telephone']) Tél. : {{ $mentions['telephone'] }} @endif
        @if($mentions['email']) — {{ $mentions['email'] }} @endif
      </p>
    </td>
    <td class="entete__droite" style="border:none;padding:0;vertical-align:top;width:38%">
      Édité le {{ now()->format('d/m/Y à H:i') }}<br>
      @if($mentions['site']) {{ $mentions['site'] }}<br> @endif
      Montants en {{ $mentions['devise'] }}
    </td>
  </tr>
</table>

@yield('contenu')

<div class="pied">
  {{ $mentions['sigle'] }} — @yield('titre') — document interne, édité depuis l'application de gestion.
</div>

</body>
</html>
