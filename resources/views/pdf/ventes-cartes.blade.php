@extends('pdf._base')

@section('titre', "Ventes de cartes {$exercice->annee}")

@section('contenu')
  <p class="titre-etat">HISTORIQUE DES VENTES DE CARTES — EXERCICE {{ $exercice->annee }}</p>

  @if(!empty($filtres))
    {{-- Un état filtré ne doit jamais pouvoir être pris pour l'état complet --}}
    <p class="bandeau-filtre">
      État partiel — filtre appliqué : {{ implode(', ', $filtres) }}.
      Les totaux ci-dessous ne portent que sur les lignes retenues.
    </p>
  @endif

  <p class="sous-titre">
    Montants effectivement encaissés et ventilés, issus des paiements validés.
    {{ $totaux['cartes'] }} carte(s) {{ empty($filtres) ? 'émise(s)' : 'retenue(s)' }},
    dont {{ $totaux['soldees'] }} intégralement réglée(s).
    Exercice {{ $exercice->statut === 'ouvert' ? 'en cours' : 'clôturé' }}.
  </p>

  <table>
    <thead>
      <tr>
        <th style="width:16%">Noms &amp; prénoms</th>
        <th style="width:9%">Matricule</th>
        <th style="width:13%">Ville de résidence</th>
        <th style="width:14%">Type de carte</th>
        <th class="nombre" style="width:10%">Groupement</th>
        <th class="nombre" style="width:10%">Village</th>
        <th class="nombre" style="width:10%">Congrès</th>
        @if($totaux['autres'] > 0)<th class="nombre" style="width:8%">Autres</th>@endif
        <th class="nombre" style="width:10%">Total réglé</th>
        <th class="nombre" style="width:9%">Reste dû</th>
      </tr>
    </thead>
    <tbody>
      @forelse($lignes as $index => $ligne)
        <tr @class(['paire' => $index % 2])>
          <td>{{ $ligne['nom_complet'] }}</td>
          <td class="tenu">{{ $ligne['matricule'] }}</td>
          <td>{{ $ligne['ville'] }}@if($ligne['pays'] && $ligne['pays'] !== 'Cameroun')<span class="tenu"> ({{ $ligne['pays'] }})</span>@endif</td>
          <td class="tenu">{{ $ligne['type_carte'] }}</td>
          <td class="nombre">{{ number_format($ligne['parts']['GROUPEMENT'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($ligne['parts']['VILLAGE'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($ligne['parts']['CONGRES'], 0, ',', ' ') }}</td>
          @if($totaux['autres'] > 0)<td class="nombre">{{ number_format($ligne['autres'], 0, ',', ' ') }}</td>@endif
          <td class="nombre">{{ number_format($ligne['total'], 0, ',', ' ') }}</td>
          <td class="nombre">
            @if($ligne['solde'] > 0)
              <span class="impaye">{{ number_format($ligne['solde'], 0, ',', ' ') }}</span>
            @else
              <span class="solde">soldée</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="10" style="text-align:center;padding:22px" class="tenu">
          @if(!empty($filtres))
            Aucune carte ne correspond au filtre appliqué sur cet exercice.
          @else
            Aucune carte n'a encore été émise pour cet exercice.
          @endif
        </td></tr>
      @endforelse
    </tbody>
    @if($lignes->isNotEmpty())
      <tfoot>
        <tr class="totaux">
          <td colspan="4">TOTAUX — {{ $totaux['cartes'] }} carte(s)</td>
          <td class="nombre">{{ number_format($totaux['parts']['GROUPEMENT'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($totaux['parts']['VILLAGE'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($totaux['parts']['CONGRES'], 0, ',', ' ') }}</td>
          @if($totaux['autres'] > 0)<td class="nombre">{{ number_format($totaux['autres'], 0, ',', ' ') }}</td>@endif
          <td class="nombre">{{ number_format($totaux['total'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($totaux['solde'], 0, ',', ' ') }}</td>
        </tr>
      </tfoot>
    @endif
  </table>

  @if($lignes->isNotEmpty())
    <div class="encadre">
      <p class="encadre__titre">Rapprochement</p>
      @if(!empty($filtres))<em>Sur les lignes retenues par le filtre.</em><br>@endif
      Montant attendu sur les cartes {{ empty($filtres) ? 'émises' : 'retenues' }} :
      <strong>{{ number_format($totaux['attendu'], 0, ',', ' ') }} {{ $mentions['devise'] }}</strong> —
      encaissé : <strong>{{ number_format($totaux['total'], 0, ',', ' ') }}</strong> —
      reste à recouvrer : <strong>{{ number_format($totaux['solde'], 0, ',', ' ') }}</strong>.
      La somme des trois postes est égale au total encaissé : chaque franc reçu est affecté.
      @if(!empty($filtres))<br><em>Pour l'état complet de l'exercice, éditez le document sans filtre.</em>@endif
    </div>

    <table class="signature">
      <tr>
        <td style="border:none;width:50%">Le Trésorier</td>
        <td style="border:none;text-align:right">Le Président — {{ $mentions['president'] }}</td>
      </tr>
    </table>
  @endif
@endsection
