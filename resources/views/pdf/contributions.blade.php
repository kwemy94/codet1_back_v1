@extends('pdf._base')

@section('titre', "Contributions et dons {$exercice->annee}")

@section('contenu')
  <p class="titre-etat">CONTRIBUTIONS ET DONS — EXERCICE {{ $exercice->annee }}</p>

  @if(!empty($filtres))
    <p class="bandeau-filtre">
      État partiel — filtre appliqué : {{ implode(', ', $filtres) }}.
      Les totaux ci-dessous ne portent que sur les lignes retenues.
    </p>
  @endif

  <p class="sous-titre">
    {{ $totaux['nombre'] }} contribution(s) — {{ $totaux['membres'] }} de membres,
    {{ $totaux['externes'] }} de donateurs externes.
    Exercice {{ $exercice->statut === 'ouvert' ? 'en cours' : 'clôturé' }}.
  </p>

  <table>
    <thead>
      <tr>
        <th style="width:11%">Référence</th>
        <th style="width:19%">Origine</th>
        <th style="width:10%">Nature</th>
        <th style="width:26%">Objet</th>
        <th class="nombre" style="width:12%">Montant</th>
        <th style="width:11%">Date</th>
        <th style="width:11%">Statut</th>
      </tr>
    </thead>
    <tbody>
      @forelse($lignes as $index => $contribution)
        <tr @class(['paire' => $index % 2])>
          <td class="tenu">{{ $contribution->reference }}</td>
          <td>
            {{ $contribution->membre?->nom_complet ?? $contribution->donateur?->denomination ?? '—' }}
            <br><span class="tenu">{{ $contribution->membre ? 'Membre '.$contribution->membre->matricule : 'Donateur externe' }}</span>
          </td>
          <td>
            {{ ['financier' => 'Financier', 'materiel' => 'Matériel', 'service' => 'Services'][$contribution->nature] ?? $contribution->nature }}
          </td>
          <td>
            {{ $contribution->designation ?: ($contribution->motif ?: '—') }}
            @if($contribution->type)<br><span class="tenu">{{ $contribution->type->libelle }}</span>@endif
          </td>
          <td class="nombre">
            {{ number_format($contribution->montant, 0, ',', ' ') }}
            @if($contribution->nature !== 'financier')<br><span class="tenu">valeur estimée</span>@endif
          </td>
          <td class="tenu">{{ $contribution->date_contribution?->format('d/m/Y') }}</td>
          <td>
            @php
              $libelles = ['attendue' => 'Attendue', 'encaissee' => 'Encaissée', 'recue' => 'Reçue', 'annulee' => 'Annulée'];
            @endphp
            @if($contribution->statut === 'annulee')
              <span class="impaye">{{ $libelles[$contribution->statut] }}</span>
            @elseif($contribution->statut === 'attendue')
              <span style="color:#4d6a99;font-weight:bold">{{ $libelles[$contribution->statut] }}</span>
            @else
              <span class="solde">{{ $libelles[$contribution->statut] }}</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="7" style="text-align:center;padding:22px" class="tenu">
          @if(!empty($filtres))
            Aucune contribution ne correspond au filtre appliqué sur cet exercice.
          @else
            Aucune contribution n'a été enregistrée pour cet exercice.
          @endif
        </td></tr>
      @endforelse
    </tbody>
  </table>

  @if($lignes->isNotEmpty())
    <div class="encadre">
      <p class="encadre__titre">Récapitulatif</p>
      <table style="margin-top:2px">
        <tbody>
          <tr>
            <td style="border:none">Dons financiers encaissés — entrés en caisse</td>
            <td class="nombre" style="border:none"><strong>{{ number_format($totaux['financier_acquis'], 0, ',', ' ') }} {{ $mentions['devise'] }}</strong></td>
          </tr>
          <tr>
            <td style="border:none">Dons en nature et en services — valeur estimée, hors caisse</td>
            <td class="nombre" style="border:none"><strong>{{ number_format($totaux['nature_acquis'], 0, ',', ' ') }} {{ $mentions['devise'] }}</strong></td>
          </tr>
          <tr>
            <td style="border:none">Contributions annoncées, non encore acquises</td>
            <td class="nombre" style="border:none">{{ number_format($totaux['attendu'], 0, ',', ' ') }}</td>
          </tr>
          @if($totaux['annule'] > 0)
            <tr>
              <td style="border:none" class="tenu">Contributions annulées (pour mémoire)</td>
              <td class="nombre" style="border:none" class="tenu">{{ number_format($totaux['annule'], 0, ',', ' ') }}</td>
            </tr>
          @endif
        </tbody>
      </table>
      <p style="margin:8px 0 0" class="tenu">
        Les deux premiers montants ne s'additionnent pas : seuls les dons financiers
        constituent une recette de trésorerie.
      </p>
    </div>

    <table class="signature">
      <tr>
        <td style="border:none;width:50%">Le Trésorier</td>
        <td style="border:none;text-align:right">Le Président — {{ $mentions['president'] }}</td>
      </tr>
    </table>
  @endif
@endsection
