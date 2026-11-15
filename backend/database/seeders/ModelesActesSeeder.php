<?php

namespace Database\Seeders;

use App\Models\ModeleActe;
use Illuminate\Database\Seeder;

/**
 * Modèles de départ, repris des lettres de l'École. Ils restent modifiables dans l'interface ;
 * relancer le seeder ne remplace pas un modèle déjà modifié.
 */
class ModelesActesSeeder extends Seeder
{
    public function run(): void
    {
        $modeles = [
            [
                'code' => 'attestation-inscription',
                'nom' => "Attestation d'inscription",
                'titre' => "ATTESTATION D'INSCRIPTION",
                'exige_inscription' => true,
                'ordre' => 1,
                'champs' => [],
                'corps' => <<<'HTML'
<p style="text-align: justify">Le Directeur de l'École Doctorale Génie du Vivant et Modélisation de l'Université de Mahajanga certifie que :</p>
<p style="text-align: justify"><strong>{{civilite}} {{nom_complet}}</strong>, {{ne}} le {{date_naissance}} est régulièrement {{inscrit}} en thèse, niveau {{niveau}} pour l'année universitaire {{annee_universitaire}}.</p>
<p style="text-align: justify">Titre de la thèse : <strong>« {{sujet_these}} »</strong></p>
<p>Équipe d'Accueil Doctorale : {{equipe}}</p>
<p>Directeur de thèse : <strong>{{directeur_these}}</strong></p>
<p>Co-Directeur : <strong>{{co_directeur}}</strong></p>
HTML,
            ],
            [
                'code' => 'admission-doctorat',
                'nom' => 'Admission en doctorat',
                'titre' => 'ADMISSION EN DOCTORAT',
                'exige_inscription' => false,
                'ordre' => 2,
                'champs' => [
                    ['cle' => 'date_presentation', 'libelle' => 'Date de présentation du projet de thèse', 'type' => 'date', 'obligatoire' => true],
                ],
                'corps' => <<<'HTML'
<p style="text-align: justify"><strong>{{civilite}} {{nom_complet}}</strong>, à la suite de votre présentation du projet de thèse en date du {{date_presentation}}, l'École Doctorale Génie du Vivant et Modélisation a le plaisir de vous informer que votre candidature en doctorat a été retenue et vous êtes bien {{admis}} au sein de notre École Doctorale pour suivre une formation doctorale de trois ans qui se déroulera de {{annee_debut}} à {{annee_fin}}.</p>
<p style="text-align: justify">À ce titre, vous êtes autorisés à vous inscrire en D1 et invités à régler vos droits d'inscription. Nous vous remercions de bien vouloir prendre contact avec notre secrétariat principal le plus rapidement possible afin de procéder à votre inscription.</p>
<p style="text-align: justify">Une attestation d'inscription vous sera délivrée dès la fin du processus de votre admission.</p>
<p style="text-align: justify">L'École Doctorale Génie du Vivant et Modélisation vous souhaite la bienvenue et une belle réussite pour votre projet de thèse.</p>
HTML,
            ],
            [
                'code' => 'fiche-evaluation-candidature',
                'nom' => "Fiche d'évaluation de candidature",
                'titre' => "FICHE D'EVALUATION POUR CANDIDATURE EN DOCTORAT",
                'exige_inscription' => false,
                'ordre' => 3,
                'champs' => [
                    ['cle' => 'date_audition', 'libelle' => "Date d'audition", 'type' => 'date', 'obligatoire' => true],
                    ['cle' => 'heure_audition', 'libelle' => "Heure d'audition", 'type' => 'heure', 'obligatoire' => false],
                    ['cle' => 'examinateur_1', 'libelle' => 'Examinateur 1', 'type' => 'texte', 'obligatoire' => true],
                    ['cle' => 'examinateur_2', 'libelle' => 'Examinateur 2', 'type' => 'texte', 'obligatoire' => false],
                    ['cle' => 'examinateur_3', 'libelle' => 'Examinateur 3', 'type' => 'texte', 'obligatoire' => false],
                    ['cle' => 'decision_examinateurs', 'libelle' => 'Décision des examinateurs', 'type' => 'texte_long', 'obligatoire' => true],
                    ['cle' => 'decision_conseil', 'libelle' => "Décision du Conseil de l'École Doctorale", 'type' => 'choix', 'options' => ['Admission', 'Admission sous réserve', 'Ajournement', 'Refus'], 'obligatoire' => true],
                    ['cle' => 'modalite_audition', 'libelle' => "Modalité de l'audition", 'type' => 'choix', 'options' => ['PRESENTIEL', 'EN LIGNE'], 'obligatoire' => true],
                ],
                'corps' => <<<'HTML'
<p style="text-align: center">(Cette fiche sera jointe au dossier du Doctorant)</p>
<p>Candidat : <strong>{{nom_complet}}</strong></p>
<p>Titre de la thèse : {{sujet_these}}</p>
<p>Nom du directeur de thèse : <strong>{{directeur_these}}</strong></p>
<p>Nom du co-directeur (éventuel) : <strong>{{co_directeur}}</strong></p>
<p>Nom du co-encadrant (éventuel) : {{co_encadrant}}</p>
<p>Équipe d'Accueil Doctorale : {{equipe}}</p>
<p>Date d'audition : {{date_audition}}</p>
<p>Heure d'audition : {{heure_audition}}</p>
<p><strong>Examinateurs :</strong></p>
<p>Examinateur 1 : {{examinateur_1}}</p>
<p>Examinateur 2 : {{examinateur_2}}</p>
<p>Examinateur 3 : {{examinateur_3}}</p>
<p>Décision des examinateurs : <strong>{{decision_examinateurs}}</strong></p>
<p>Décision du Conseil de l'École Doctorale : <strong>{{decision_conseil}}</strong></p>
<p>L'audition s'est déroulée en {{modalite_audition}}</p>
HTML,
            ],
        ];

        foreach ($modeles as $modele) {
            ModeleActe::query()->firstOrCreate(['code' => $modele['code']], $modele);
        }
    }
}
