<?php

namespace App\Notifications;

use App\Models\Acte;
use App\Models\Doctorant;
use App\Models\User;
use App\Support\GenerateurPdfActes;
use App\Support\TableauCourriel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/** Au candidat admis : il devient doctorant ; l'attestation d'admission est jointe à l'e-mail. */
class AdmissionPrononcee extends NotificationPlateforme
{
    protected bool $parEmail = true;

    public function __construct(public Doctorant $doctorant, public ?Acte $acte, public int $droits) {}

    protected function titre(): string
    {
        return 'Admission en doctorat';
    }

    protected function message(): string
    {
        return 'Félicitations : le jury vous a déclaré admis(e). Vous êtes inscrit(e) en D1 (matricule '.$this->doctorant->matricule.')'
            .($this->droits > 0 ? ', droits d\'inscription à régler : '.self::ar($this->droits) : '').'.';
    }

    protected function introductionCourriel(): HtmlString
    {
        return TableauCourriel::phrase('Félicitations : après la présentation de votre projet de thèse, le jury vous a déclaré :admis. Vous êtes désormais doctorant(e) de l\'École, inscrit(e) en :niveau.', [
            'admis' => 'admis(e)',
            'niveau' => 'première année (D1)',
        ]);
    }

    /**
     * @return array<string, ?string>
     */
    protected function detailsCourriel(): array
    {
        return [
            'Matricule' => $this->doctorant->matricule,
            'Équipe d\'accueil' => $this->doctorant->equipeAccueil?->sigle,
            'Attestation d\'admission' => $this->acte ? 'N° '.$this->acte->numero.' (jointe à cet e-mail)' : null,
            'Droits d\'inscription' => $this->droits > 0 ? self::ar($this->droits).', à régler auprès de la scolarité' : null,
        ];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $courriel = parent::toMail($notifiable)
            ->line('Un second e-mail vous permet de choisir votre mot de passe pour accéder à la plateforme : votre dossier, vos paiements et vos actes.');

        if ($this->acte) {
            $courriel->attachData(GenerateurPdfActes::generer(collect([$this->acte])), 'attestation-admission-'.str_replace('/', '-', $this->acte->numero).'.pdf', ['mime' => 'application/pdf']);
        }

        return $courriel;
    }

    protected function lien(): string
    {
        return '/';
    }

    protected function categorie(): string
    {
        return 'admission';
    }
}
