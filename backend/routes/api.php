<?php

use App\Http\Controllers\Api\ActeController;
use App\Http\Controllers\Api\ActivationCompteController;
use App\Http\Controllers\Api\AnneeUniversitaireController;
use App\Http\Controllers\Api\ApiPubliqueController;
use App\Http\Controllers\Api\AuthentificationController;
use App\Http\Controllers\Api\CandidatController;
use App\Http\Controllers\Api\CompteController;
use App\Http\Controllers\Api\DeuxFacteursController;
use App\Http\Controllers\Api\DoctorantController;
use App\Http\Controllers\Api\EquipeAccueilController;
use App\Http\Controllers\Api\FraisController;
use App\Http\Controllers\Api\ImportExportDoctorantsController;
use App\Http\Controllers\Api\InscriptionController;
use App\Http\Controllers\Api\LienTelechargementController;
use App\Http\Controllers\Api\MembreEquipeController;
use App\Http\Controllers\Api\ModeleActeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaiementController;
use App\Http\Controllers\Api\PhotoDoctorantController;
use App\Http\Controllers\Api\PhotoUtilisateurController;
use App\Http\Controllers\Api\PieceJointeController;
use App\Http\Controllers\Api\ProgrammeDoctoralController;
use App\Http\Controllers\Api\ReglagesActesController;
use App\Http\Controllers\Api\ReglagesApplicationController;
use App\Http\Controllers\Api\ReglagesMatriculeController;
use App\Http\Controllers\Api\SeanceController;
use App\Http\Controllers\Api\TableauDeBordController;
use App\Http\Controllers\Api\TypeFraisController;
use App\Http\Controllers\ApiPublique\DoctorantController as DoctorantPublicController;
use App\Http\Controllers\ApiPublique\EquipeController as EquipePublicController;
use App\Http\Controllers\ApiPublique\TheseController as ThesePublicController;
use App\Http\Middleware\ModeMaintenance;
use App\Http\Middleware\VerifierJetonApiPublique;
use Illuminate\Support\Facades\Route;

/*
 * API de l'interface Nuxt (session Sanctum). Les droits sont vérifiés par les policies.
 */
Route::middleware('throttle:6,1')->group(function (): void {
    Route::post('/connexion', [AuthentificationController::class, 'connexion'])->name('connexion');
    Route::post('/connexion/deux-facteurs', [AuthentificationController::class, 'deuxFacteurs'])->name('connexion.deux-facteurs');
    Route::post('/mot-de-passe/oublie', [AuthentificationController::class, 'motDePasseOublie'])->name('mot-de-passe.oublie');
    Route::post('/mot-de-passe/reinitialiser', [AuthentificationController::class, 'reinitialiserMotDePasse'])->name('mot-de-passe.reinitialiser');
});

/* « Créer mon accès » : code à 4 chiffres par e-mail ou WhatsApp, puis mot de passe (comptes déjà enregistrés par la scolarité) */
Route::prefix('activation')->name('activation.')->group(function (): void {
    Route::middleware('throttle:6,1')->group(function (): void {
        Route::post('/demande', [ActivationCompteController::class, 'demander'])->name('demande');
        Route::post('/renvoyer', [ActivationCompteController::class, 'renvoyer'])->name('renvoyer');
    });
    Route::middleware('throttle:15,1')->group(function (): void {
        Route::post('/verifier', [ActivationCompteController::class, 'verifier'])->name('verifier');
        Route::post('/terminer', [ActivationCompteController::class, 'terminer'])->name('terminer');
    });
});

/* Nom, logos, maintenance et apparence : lus par l'interface avant la connexion */
Route::get('/application', [ReglagesApplicationController::class, 'public'])->name('application');

Route::middleware(['auth:sanctum', ModeMaintenance::class])->group(function (): void {
    Route::post('/deconnexion', [AuthentificationController::class, 'deconnexion'])->name('deconnexion');
    Route::get('/moi', [AuthentificationController::class, 'moi'])->name('moi');
    Route::post('/moi/photo', [PhotoUtilisateurController::class, 'enregistrerMienne'])->name('moi.photo');
    Route::delete('/moi/photo', [PhotoUtilisateurController::class, 'supprimerMienne'])->name('moi.photo.supprimer');
    Route::get('/moi/deux-facteurs', [DeuxFacteursController::class, 'etat'])->name('deux-facteurs.etat');
    Route::post('/moi/deux-facteurs/preparer', [DeuxFacteursController::class, 'preparer'])->name('deux-facteurs.preparer');
    Route::post('/moi/deux-facteurs/confirmer', [DeuxFacteursController::class, 'confirmer'])->name('deux-facteurs.confirmer');
    Route::post('/moi/deux-facteurs/codes-secours', [DeuxFacteursController::class, 'regenererCodes'])->name('deux-facteurs.codes');
    Route::delete('/moi/deux-facteurs', [DeuxFacteursController::class, 'desactiver'])->name('deux-facteurs.desactiver');
    Route::delete('/comptes/{compte}/deux-facteurs', [DeuxFacteursController::class, 'reinitialiserCompte'])->name('comptes.deux-facteurs');
    Route::post('/liens-telechargement', LienTelechargementController::class)->middleware('throttle:60,1')->name('liens-telechargement');
    Route::put('/moi/mot-de-passe', [AuthentificationController::class, 'modifierMotDePasse'])->name('moi.mot-de-passe');

    Route::get('/tableau-de-bord', TableauDeBordController::class)->name('tableau-de-bord');

    Route::post('/doctorants/import', [ImportExportDoctorantsController::class, 'importer'])->name('doctorants.import');
    Route::get('/doctorants/compteurs', [DoctorantController::class, 'compteurs'])->name('doctorants.compteurs');
    Route::apiResource('doctorants', DoctorantController::class)->except('destroy');
    Route::post('/doctorants/{doctorant}/inscriptions', [InscriptionController::class, 'store'])->name('inscriptions.store');
    Route::post('/doctorants/{doctorant}/photo', [PhotoDoctorantController::class, 'store'])->name('doctorants.photo.store');
    Route::delete('/doctorants/{doctorant}/photo', [PhotoDoctorantController::class, 'destroy'])->name('doctorants.photo.destroy');
    Route::get('/doctorants/{doctorant}/pieces', [PieceJointeController::class, 'index'])->name('pieces.index');
    Route::post('/doctorants/{doctorant}/pieces', [PieceJointeController::class, 'store'])->name('pieces.store');
    Route::delete('/pieces/{piece}', [PieceJointeController::class, 'destroy'])->name('pieces.destroy');
    Route::put('/inscriptions/{inscription}', [InscriptionController::class, 'update'])->name('inscriptions.update');
    Route::delete('/inscriptions/{inscription}', [InscriptionController::class, 'destroy'])->name('inscriptions.destroy');

    Route::apiResource('equipes', EquipeAccueilController::class)
        ->parameters(['equipes' => 'equipe']);
    Route::post('/equipes/{equipe}/programmes', [ProgrammeDoctoralController::class, 'store'])->name('programmes.store');
    Route::put('/programmes/{programme}', [ProgrammeDoctoralController::class, 'update'])->name('programmes.update');
    Route::delete('/programmes/{programme}', [ProgrammeDoctoralController::class, 'destroy'])->name('programmes.destroy');
    Route::post('/equipes/{equipe}/membres', [MembreEquipeController::class, 'store'])->name('membres-equipe.store');
    Route::put('/membres-equipe/{membre}', [MembreEquipeController::class, 'update'])->name('membres-equipe.update');
    Route::delete('/membres-equipe/{membre}', [MembreEquipeController::class, 'destroy'])->name('membres-equipe.destroy');
    Route::apiResource('annees', AnneeUniversitaireController::class)->only(['index', 'store', 'update']);

    // Actes administratifs
    Route::get('/modeles-actes/disponibles', [ModeleActeController::class, 'disponibles'])->name('modeles-actes.disponibles');
    Route::post('/modeles-actes/apercu', [ModeleActeController::class, 'apercu'])->name('modeles-actes.apercu');
    Route::apiResource('modeles-actes', ModeleActeController::class)->parameters(['modeles-actes' => 'modele']);
    Route::get('/candidats', [CandidatController::class, 'index'])->name('candidats.index');
    Route::get('/candidats/compteurs', [CandidatController::class, 'compteurs'])->name('candidats.compteurs');
    Route::post('/doctorants/{doctorant}/admettre', [CandidatController::class, 'admettre'])->name('candidats.admettre');
    Route::put('/passages/{passage}/decision', [SeanceController::class, 'decider'])->name('passages.decision');
    Route::post('/seances/{seance}/admettre', [SeanceController::class, 'admettre'])->name('seances.admettre');
    Route::post('/seances/{seance}/informer', [SeanceController::class, 'informer'])->name('seances.informer');
    Route::apiResource('seances', SeanceController::class);
    Route::post('/seances/{seance}/annuler', [SeanceController::class, 'annuler'])->name('seances.annuler');
    Route::get('/seances/{seance}/candidats', [SeanceController::class, 'candidats'])->name('seances.candidats');
    Route::post('/seances/{seance}/passages', [SeanceController::class, 'ajouterPassages'])->name('seances.passages');
    Route::post('/seances/{seance}/replanifier', [SeanceController::class, 'replanifierPassages'])->name('seances.replanifier');
    Route::get('/seances/{seance}/disponibilites', [SeanceController::class, 'disponibilites'])->name('seances.disponibilites');
    Route::post('/seances/{seance}/intervenants', [SeanceController::class, 'affecter'])->name('seances.affecter');
    Route::post('/seances/{seance}/convoquer', [SeanceController::class, 'convoquer'])->name('seances.convoquer');
    Route::put('/passages/{passage}', [SeanceController::class, 'modifierPassage'])->name('passages.update');
    Route::delete('/passages/{passage}', [SeanceController::class, 'supprimerPassage'])->name('passages.destroy');
    Route::delete('/passages/{passage}/intervenants/{membre}', [SeanceController::class, 'retirerIntervenant'])->name('passages.retirer-intervenant');
    Route::get('/reglages-matricule', [ReglagesMatriculeController::class, 'show'])->name('reglages-matricule.show');
    Route::post('/reglages-matricule', [ReglagesMatriculeController::class, 'update'])->name('reglages-matricule.update');
    Route::post('/reglages-matricule/apercu', [ReglagesMatriculeController::class, 'apercu'])->name('reglages-matricule.apercu');
    Route::get('/reglages-application', [ReglagesApplicationController::class, 'show'])->name('reglages-application.show');
    Route::post('/reglages-application', [ReglagesApplicationController::class, 'update'])->name('reglages-application.update');
    Route::get('/api-publique', [ApiPubliqueController::class, 'show'])->name('api-publique.show');
    Route::post('/api-publique/jeton', [ApiPubliqueController::class, 'generer'])->name('api-publique.generer');
    Route::delete('/api-publique/jeton', [ApiPubliqueController::class, 'revoquer'])->name('api-publique.revoquer');
    Route::get('/reglages-actes', [ReglagesActesController::class, 'show'])->name('reglages-actes.show');
    Route::post('/reglages-actes', [ReglagesActesController::class, 'update'])->name('reglages-actes.update');
    Route::get('/actes', [ActeController::class, 'index'])->name('actes.index');
    Route::post('/actes', [ActeController::class, 'store'])->name('actes.store');
    Route::post('/actes/lot', [ActeController::class, 'lot'])->name('actes.lot');
    Route::post('/actes/{acte}/annuler', [ActeController::class, 'annuler'])->name('actes.annuler');

    // Paiements et frais
    Route::get('/types-frais', [TypeFraisController::class, 'index'])->name('types-frais.index');
    Route::post('/types-frais', [TypeFraisController::class, 'store'])->name('types-frais.store');
    Route::put('/types-frais/{type}', [TypeFraisController::class, 'update'])->name('types-frais.update');
    Route::delete('/types-frais/{type}', [TypeFraisController::class, 'destroy'])->name('types-frais.destroy');
    Route::get('/doctorants/{doctorant}/finances', [FraisController::class, 'finances'])->name('doctorants.finances');
    Route::post('/doctorants/{doctorant}/paiements', [PaiementController::class, 'store'])->name('paiements.store');
    Route::post('/frais', [FraisController::class, 'store'])->name('frais.store');
    Route::put('/frais/{frais}', [FraisController::class, 'update'])->name('frais.update');
    Route::post('/frais/{frais}/annuler', [FraisController::class, 'annuler'])->name('frais.annuler');
    Route::get('/paiements', [PaiementController::class, 'index'])->name('paiements.index');
    Route::get('/paiements/situation', [PaiementController::class, 'situation'])->name('paiements.situation');
    Route::post('/paiements/rappels', [PaiementController::class, 'rappeler'])->name('paiements.rappels');
    Route::get('/paiements/statistiques', [PaiementController::class, 'statistiques'])->name('paiements.statistiques');
    Route::post('/paiements/{paiement}/annuler', [PaiementController::class, 'annuler'])->name('paiements.annuler');
    Route::post('/paiements/{paiement}/justificatif', [PaiementController::class, 'justificatif'])->name('paiements.justificatif');

    // Cloche
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/tout-lu', [NotificationController::class, 'toutLu'])->name('notifications.tout-lu');
    Route::post('/notifications/{id}/lue', [NotificationController::class, 'lue'])->name('notifications.lue');

    Route::apiResource('comptes', CompteController::class)->only(['index', 'store', 'update']);
    Route::post('/comptes/{compte}/photo', [PhotoUtilisateurController::class, 'enregistrerCompte'])->name('comptes.photo');
    Route::delete('/comptes/{compte}/photo', [PhotoUtilisateurController::class, 'supprimerCompte'])->name('comptes.photo.supprimer');
    Route::post('/comptes/{compte}/lien-acces', [CompteController::class, 'lienAcces'])->name('comptes.lien-acces');
});

/*
 * API publique en lecture pour le site vitrine (jeton « Bearer » créé dans les paramètres).
 * Contrat : .agents/skills/edgvm-api-publique/SKILL.md
 */
Route::prefix('public/v1')->name('public.')->middleware([VerifierJetonApiPublique::class, 'throttle:120,1'])->group(function (): void {
    Route::get('/equipes', [EquipePublicController::class, 'index'])->name('equipes.index');
    Route::get('/equipes/{sigle}', [EquipePublicController::class, 'show'])->name('equipes.show');
    Route::get('/doctorants', [DoctorantPublicController::class, 'index'])->name('doctorants.index');
    Route::get('/theses', [ThesePublicController::class, 'index'])->name('theses.index');
    Route::get('/theses/{id}', [ThesePublicController::class, 'show'])->name('theses.show');
});
