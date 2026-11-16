<?php

namespace App\Notifications;

use App\Enums\DecisionCandidature as Decision;
use App\Models\Passage;
use App\Support\GabaritActe;
use App\Support\TableauCourriel;
use Illuminate\Support\HtmlString;

/**
 * Au candidat ajourné ou non admis, par e-mail seulement (il n'a pas encore accès à la plateforme).
 */
class DecisionCandidature extends NotificationPlateforme
{
    protected bool $parEmail = true;

    protected bool $avecBouton = false;

    public function __construct(public Passage $passage) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail'];
    }

    protected function titre(): string
    {
        return 'Résultat de votre candidature';
    }

    protected function message(): string
    {
        return $this->passage->decision === Decision::Ajourne
            ? 'Le jury a ajourné votre candidature : vous pourrez présenter à nouveau votre projet lors d\'une prochaine séance.'
            : 'Le jury n\'a pas retenu votre candidature.';
    }

    protected function introductionCourriel(): HtmlString
    {
        return TableauCourriel::phrase(
            $this->passage->decision === Decision::Ajourne
                ? 'À l\'issue de la présentation de votre projet de thèse le :date, le jury a :decision votre candidature. Vous pourrez présenter à nouveau votre projet lors d\'une prochaine séance ; la scolarité vous en informera.'
                : 'À l\'issue de la présentation de votre projet de thèse le :date, le jury n\'a :decision votre candidature. Nous vous remercions de l\'intérêt porté à l\'École.',
            [
                'date' => GabaritActe::dateLongue($this->passage->debut),
                'decision' => $this->passage->decision === Decision::Ajourne ? 'ajourné' : 'pas retenu',
            ],
        );
    }

    /**
     * @return array<string, ?string>
     */
    protected function detailsCourriel(): array
    {
        return ['Observation du jury' => $this->passage->decision_observation];
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
