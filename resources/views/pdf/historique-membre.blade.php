@extends('pdf._base')

@section('titre', "Historique — {$membre->matricule}")

@section('contenu')
  <p class="titre-etat">HISTORIQUE DU MEMBRE — TOUS EXERCICES</p>

  <table style="margin-bottom:14px">
    <tr>
      <td style="border:none;padding:0;width:60%">
        <strong style="font-size:12pt">{{ $membre->nom_complet }}</strong><br>
        <span class="tenu">{{ $membre->matricule }} — {{ $membre->categorie?->libelle }}</span><br>
        <span class="tenu">
          {{ $membre->ville?->libelle ?? 'Ville non renseignée' }}@if($membre->ville?->pays), {{ $membre->ville->pays->libelle }}@endif
          — Tél. {{ $membre->telephone }}
        </span>
      </td>
      <td style="border:none;padding:0;text-align:right" class="tenu">
        Adhésion : {{ $membre->date_adhesion?->format('d/m/Y') }}<br>
        Statut : {{ ucfirst($membre->statut) }}<br>
        {{ $exercices->count() }} exercice(s) cotisé(s)
      </td>
    </tr>
  </table>

  <table>
    <thead>
      <tr>
        <th style="width:9%">Exercice</th>
        <th style="width:20%">Carte</th>
        <th class="nombre" style="width:12%">Groupement</th>
        <th class="nombre" style="width:12%">Village</th>
        <th class="nombre" style="width:12%">Congrès</th>
        <th class="nombre" style="width:12%">Montant dû</th>
        <th class="nombre" style="width:12%">Réglé</th>
        <th class="nombre" style="width:11%">Reste dû</th>
      </tr>
    </thead>
    <tbody>
      @forelse($exercices as $index => $ligne)
        <tr @class(['paire' => $index % 2])>
          <td><strong>{{ $ligne['annee'] }}</strong>
              @if($ligne['statut_exercice'] === 'cloture')<span class="tenu"> (clôturé)</span>@endif</td>
          <td>{{ $ligne['type_carte'] }}<br><span class="tenu">{{ $ligne['numero_carte'] }}</span></td>
          <td class="nombre">{{ number_format($ligne['parts']['GROUPEMENT'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($ligne['parts']['VILLAGE'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($ligne['parts']['CONGRES'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($ligne['montant_du'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($ligne['montant_regle'], 0, ',', ' ') }}</td>
          <td class="nombre">
            @if($ligne['solde'] > 0)
              <span class="impaye">{{ number_format($ligne['solde'], 0, ',', ' ') }}</span>
            @else
              <span class="solde">—</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8" style="text-align:center;padding:22px" class="tenu">
          Aucune carte n'a été émise pour ce membre.
        </td></tr>
      @endforelse
    </tbody>
    @if($exercices->isNotEmpty())
      <tfoot>
        <tr class="totaux">
          <td colspan="5">CUMUL SUR TOUS LES EXERCICES</td>
          <td class="nombre">{{ number_format($totaux['du'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($totaux['regle'], 0, ',', ' ') }}</td>
          <td class="nombre">{{ number_format($totaux['solde'], 0, ',', ' ') }}</td>
        </tr>
      </tfoot>
    @endif
  </table>

  @if($totaux['dons'] > 0)
    <div class="encadre">
      <p class="encadre__titre">Contributions volontaires</p>
      Ce membre a par ailleurs versé
      <strong>{{ number_format($totaux['dons'], 0, ',', ' ') }} {{ $mentions['devise'] }}</strong>
      de dons encaissés ou reçus, hors carte annuelle.
    </div>
  @endif

  {{-- Situation des impayés : c'est l'information que le trésorier cherche en premier --}}
  @if($impayes->isNotEmpty())
    <div class="encadre encadre--alerte">
      <p class="encadre__titre">Situation : {{ $impayes->count() }} exercice(s) avec impayé</p>
      <table style="margin-top:4px">
        <thead>
          <tr>
            <th style="width:20%">Exercice</th>
            <th style="width:45%">Carte</th>
            <th class="nombre">Reste dû</th>
          </tr>
        </thead>
        <tbody>
          @foreach($impayes as $ligne)
            <tr>
              <td><strong>{{ $ligne['annee'] }}</strong>
                  @if($ligne['statut_exercice'] === 'cloture')
                    <span class="tenu"> — exercice clôturé, régularisation à décider par le bureau</span>
                  @endif</td>
              <td>{{ $ligne['type_carte'] }}</td>
              <td class="nombre impaye">{{ number_format($ligne['solde'], 0, ',', ' ') }}</td>
            </tr>
          @endforeach
          <tr class="totaux">
            <td colspan="2">TOTAL DES IMPAYÉS</td>
            <td class="nombre">{{ number_format($totaux['solde'], 0, ',', ' ') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  @elseif($exercices->isNotEmpty())
    <div class="encadre">
      <p class="encadre__titre">Situation : à jour</p>
      Aucun impayé n'est constaté sur les {{ $exercices->count() }} exercice(s) figurant ci-dessus.
    </div>
  @endif

  <table class="signature">
    <tr>
      <td style="border:none;width:50%">Le Trésorier</td>
      <td style="border:none;text-align:right">Le Président — {{ $mentions['president'] }}</td>
    </tr>
  </table>
@endsection
